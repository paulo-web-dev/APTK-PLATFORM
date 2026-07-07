<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\AppmaxException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\CartService;
use App\Models\Setting;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout com pagamento REAL via Appmax (Pix, cartão e boleto).
 *
 * Fluxo do store():
 *   1. valida endereço + método + CPF (+ cartão, se for o caso)
 *   2. cria o pedido local (OrderService — baixa estoque, limpa carrinho)
 *   3. Appmax: cria cliente → cria pedido → processa o pagamento
 *      - pix    → guarda QR/copia-e-cola; pedido fica pendente até o webhook
 *      - boleto → guarda linha/PDF; idem
 *      - cartão → aprovação na hora; recusa cancela o pedido local, devolve
 *        estoque, devolve o carrinho e volta ao checkout com o erro
 */
class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected OrderService $orders,
        protected AppmaxService $appmax,
    ) {
    }

    public function index(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('shop.checkout', [
            'items'             => $this->cart->items(),
            'total'             => $this->cart->total(),
            'maxInstallments'   => (int) config('appmax.max_installments', 12),
            // external_id da instalação — referenciado pela doc de tokenização (5.2.3).
            'appmaxExternalId'  => Setting::get('appmax_install_external_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate($this->rules($request), $this->messages());

        // 1) Pedido local (endereço + itens + baixa de estoque + limpa carrinho).
        $order = $this->orders->placeFromCart($request->user(), $data);

        try {
            // 2) Cliente e pedido na Appmax.
            // IP: o coletado pelo Appmax JS (doc 3.1) tem prioridade; se o
            // script falhou/foi bloqueado, cai no IP do request.
            $clientIp = $request->input('appmax_ip');
            if (! filter_var($clientIp, FILTER_VALIDATE_IP)) {
                $clientIp = (string) $request->ip();
            }

            $appmaxCustomerId = $this->appmax->createCustomer(
                array_merge($data, ['email' => $request->user()->email]),
                $clientIp,
            );

            $order->update(['appmax_customer_id' => $appmaxCustomerId]);
            $order->load('items');

            $appmaxOrderId = $this->appmax->createOrder($order, $appmaxCustomerId);
            $order->update(['appmax_order_id' => $appmaxOrderId]);
            $order->refresh();

            // 3) Pagamento conforme o método escolhido.
            return match ($data['payment_method']) {
                'pix'    => $this->handlePix($order, $data),
                'boleto' => $this->handleBoleto($order, $data),
                'cartao' => $this->handleCreditCard($order, $data),
            };
        } catch (AppmaxException $e) {
            // Falha no gateway: cancela o pedido local, devolve estoque e
            // devolve os itens ao carrinho pro cliente tentar de novo.
            $this->orders->cancelAndRestoreStock($order, 'failed');
            $this->orders->restoreCartFromOrder($order);

            return redirect()
                ->route('checkout.index')
                ->withInput($request->except(['card_number', 'card_cvv']))
                ->with('payment_error', $e->getMessage());
        }
    }

    protected function handlePix(Order $order, array $data): RedirectResponse
    {
        $pix = $this->appmax->payWithPix($order, $data['cpf']);

        $order->update([
            'payment_status'       => 'pending',
            'appmax_pay_reference' => $pix['pay_reference'],
            'payment_details'      => [
                'type'       => 'pix',
                'qrcode'     => $pix['qrcode'],
                'emv'        => $pix['emv'],
                'expiration' => $pix['expiration'],
            ],
        ]);

        return redirect()->route('checkout.success', $order);
    }

    protected function handleBoleto(Order $order, array $data): RedirectResponse
    {
        $boleto = $this->appmax->payWithBoleto($order, $data['cpf']);

        $order->update([
            'payment_status'       => 'pending',
            'appmax_pay_reference' => $boleto['pay_reference'],
            'payment_details'      => [
                'type'           => 'boleto',
                'digitable_line' => $boleto['digitable_line'],
                'pdf'            => $boleto['pdf'],
                'due_date'       => $boleto['due_date'],
            ],
        ]);

        return redirect()->route('checkout.success', $order);
    }

    protected function handleCreditCard(Order $order, array $data): RedirectResponse
    {
        [$month, $year] = $this->parseExpiry($data['card_expiry']);

        $request = request();

        // Token gerado pelo Appmax JS no navegador (doc 5.2.3). O nome exato
        // do campo injetado não está documentado — testamos os candidatos e
        // logamos as chaves recebidas (NUNCA valores) pra confirmar no sandbox.
        $token = collect(['token', 'appmax_token', 'card_token', 'payment_token'])
            ->map(fn ($k) => $request->input($k))
            ->first(fn ($v) => filled($v));

        \Illuminate\Support\Facades\Log::info('Appmax JS: campos do POST de cartão', [
            'keys'      => array_keys($request->except(['card_number', 'card_cvv', 'card_name', 'card_expiry', '_token'])),
            'tem_token' => filled($token),
        ]);

        $charge = $this->appmax->payWithCreditCard($order, [
            'token'           => $token,
            'number'          => $data['card_number'] ?? '',
            'name'            => $data['card_name'],
            'month'           => $month,
            'year'            => $year,
            'cvv'             => $data['card_cvv'] ?? '',
            'document_number' => $data['cpf'],
            'installments'    => (int) $data['installments'],
        ]);

        // Sem exceção = cobrança aceita pela Appmax (antifraude pode revisar
        // depois; o webhook ajusta o status se algo mudar).
        $order->update([
            'status'               => 'processing',
            'payment_status'       => 'paid',
            'paid_at'              => now(),
            'appmax_pay_reference' => $charge['pay_reference'],
            'payment_details'      => [
                'type'         => 'cartao',
                'installments' => (int) $data['installments'],
                'upsell_hash'  => $charge['upsell_hash'],
            ],
        ]);

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load('items');

        return view('shop.checkout-success', compact('order'));
    }

    /**
     * Status do pagamento em JSON — usado pelo polling da tela do Pix
     * (a página consulta a cada poucos segundos até o webhook confirmar).
     */
    public function paymentStatus(Order $order): JsonResponse
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return response()->json([
            'payment_status' => $order->payment_status,
            'paid'           => $order->isPaid(),
        ]);
    }

    /* -----------------------------------------------------------------
     | Validação
     ------------------------------------------------------------------ */

    protected function rules(Request $request): array
    {
        $rules = [
            'name'            => ['required', 'string', 'max:255'],
            'phone'           => ['required', 'string', 'max:20'],
            'cpf'             => ['required', 'string', 'regex:/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/'],
            'zipcode'         => ['required', 'string', 'max:9'],
            'street'          => ['required', 'string', 'max:255'],
            'number'          => ['required', 'string', 'max:20'],
            'complement'      => ['nullable', 'string', 'max:255'],
            'neighborhood'    => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:255'],
            'state'           => ['required', 'string', 'size:2'],
            'shipping_method' => ['nullable', 'string', 'max:50'],
            'payment_method'  => ['required', 'in:pix,cartao,boleto'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];

        if ($request->input('payment_method') === 'cartao') {
            // Se o Appmax JS tokenizou no navegador, os dados brutos do cartão
            // podem nem chegar (é o comportamento desejado) — só exigimos os
            // campos crus quando NÃO houver token.
            $hasToken = collect(['token', 'appmax_token', 'card_token', 'payment_token'])
                ->contains(fn ($k) => filled($request->input($k)));

            $rules += [
                'card_number'  => [$hasToken ? 'nullable' : 'required', 'string', 'regex:/^[\d\s]{16,19}$/'],
                'card_name'    => ['required', 'string', 'max:100'],
                'card_expiry'  => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?\d{2}$/'],
                'card_cvv'     => [$hasToken ? 'nullable' : 'required', 'string', 'regex:/^\d{3,4}$/'],
                'installments' => ['required', 'integer', 'min:1', 'max:'.config('appmax.max_installments', 12)],
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'cpf.regex'         => 'Informe um CPF válido (000.000.000-00).',
            'card_number.regex' => 'Informe o número do cartão (16 dígitos).',
            'card_expiry.regex' => 'Validade no formato MM/AA.',
            'card_cvv.regex'    => 'CVV com 3 ou 4 dígitos.',
        ];
    }

    /** "09/28" → [9, 2028] */
    protected function parseExpiry(string $expiry): array
    {
        $digits = preg_replace('/\D+/', '', $expiry);

        return [(int) substr($digits, 0, 2), 2000 + (int) substr($digits, 2, 2)];
    }
}
