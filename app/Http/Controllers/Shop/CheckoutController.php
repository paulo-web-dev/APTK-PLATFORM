<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\AppmaxException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\CartService;
use App\Models\Setting;
use App\Services\FreteService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
 *      - cartão → aprovação na hora; recusa CLARA cancela o pedido local,
 *        devolve estoque, devolve o carrinho e volta ao checkout com o erro.
 *        Timeout/rede/5xx na cobrança NÃO cancela (pode ter cobrado): o
 *        pedido fica pending "em verificação" até a consulta à Appmax
 *        (pedidos:cancelar-cartao-preso) ou o webhook resolver.
 *
 * Cartão SÓ com token do Appmax JS: número e CVV nunca passam por aqui
 * (sem PCI-DSS a doc não permite). Request que traga esses campos é
 * rejeitado e logado, sem os valores.
 */
class CheckoutController extends Controller
{
    /** Dados crus de cartão: nenhum deles pode chegar ao servidor. */
    protected const CAMPOS_CARTAO_PROIBIDOS = ['card_number', 'card_cvv', 'card-number', 'cvv'];

    public const MSG_CARTAO_NAO_VALIDADO = 'Não foi possível validar o cartão. Confira os dados e tente de novo, ou escolha Pix ou boleto.';

    public function __construct(
        protected CartService $cart,
        protected OrderService $orders,
        protected AppmaxService $appmax,
        protected FreteService $frete,
    ) {
    }

    public function index(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        return view('shop.checkout', [
            'items'               => $this->cart->items(),
            'total'               => $this->cart->total(),
            'maxInstallments'     => max(1, (int) config('appmax.max_installments', 3)),
            'minInstallmentValue' => (float) config('appmax.min_installment_value', 30.00),
            // External ID da loja: obrigatório pra tokenização (doc Appmax JS).
            'appmaxExternalId'    => AppmaxService::externalId(),
            // Build do script no ambiente da API — tem que casar com o external_id.
            'appmaxScriptUrl'     => AppmaxService::scriptUrl(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        // Dado cru de cartão no request: o front nunca manda (tokeniza no
        // navegador). Se chegou, é página velha ou request forjado — rejeita
        // e loga só QUAIS campos vieram, nunca os valores.
        $proibidos = array_values(array_filter(self::CAMPOS_CARTAO_PROIBIDOS, fn ($k) => filled($request->input($k))));
        if ($proibidos) {
            Log::warning('Checkout: request com dado cru de cartão rejeitado', [
                'campos'  => $proibidos,
                'user_id' => $request->user()?->id,
                'ip'      => $request->ip(),
            ]);

            return redirect()
                ->route('checkout.index')
                ->withInput($request->except([...self::CAMPOS_CARTAO_PROIBIDOS, 'appmax_token']))
                ->with('payment_error', self::MSG_CARTAO_NAO_VALIDADO);
        }

        $data = $request->validate($this->rules($request), $this->messages());

        // Parcelas: o limite depende do total COM frete (parcela mínima).
        // Mesmo cálculo do OrderService, antes de gravar qualquer coisa.
        if ($data['payment_method'] === 'cartao') {
            $items = $this->cart->items();
            $frete = $this->frete->opcao($items, (string) $data['zipcode'], $data['shipping_method']);
            $max = AppmaxService::maxParcelas((float) $items->sum('subtotal') + (float) $frete['valor']);

            if ((int) $data['installments'] > $max) {
                return redirect()
                    ->route('checkout.index')
                    ->withInput($request->except(['appmax_token']))
                    ->withErrors(['installments' => "Para este valor, parcelamos em até {$max}x sem juros."]);
            }
        }

        // 1) Pedido local (endereço + itens + baixa de estoque + limpa carrinho).
        //    A revalidação de estoque acontece DENTRO da transação; se algo
        //    esgotou entre o carrinho e aqui, a mensagem diz qual item e
        //    quanto sobrou, e o cliente volta ao carrinho para ajustar.
        try {
            $order = $this->orders->placeFromCart($request->user(), $data);
        } catch (OutOfStockException $e) {
            return redirect()->route('cart.index')->with('stock_error', $e->getMessage());
        }

        try {
            // 2) Cliente e pedido na Appmax.
            // IP: o coletado pelo Appmax JS (doc 3.1) tem prioridade; se o
            // script falhou/foi bloqueado, cai no IP do request.
            $clientIp = $request->input('appmax_ip') ?: $request->input('ip');
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
            // devolve os itens ao carrinho pro cliente tentar de novo. (A
            // incerteza na cobrança do cartão nem chega aqui — ver
            // handleCreditCard.) Texto cru da API só no pedido, pro admin.
            $this->orders->cancelAndRestoreStock($order, 'failed');
            $order->mesclarPaymentDetails(['erro_gateway' => $e->detalhe ?? $e->getMessage()]);
            $this->orders->restoreCartFromOrder($order);

            return redirect()
                ->route('checkout.index')
                ->withInput($request->except([...self::CAMPOS_CARTAO_PROIBIDOS, 'appmax_token']))
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
        try {
            // Token do Appmax JS: o onTokenize({ token }) do front grava no
            // hidden appmax_token (doc Appmax JS).
            $charge = $this->appmax->payWithCreditCard($order, [
                'token'           => $data['appmax_token'],
                'name'            => $data['card_name'],
                'document_number' => $data['cpf'],
                'installments'    => (int) $data['installments'],
            ]);
        } catch (AppmaxException $e) {
            if (! $e->isIndefinido()) {
                throw $e; // recusa clara: o catch do store() cancela
            }

            return $this->cobrancaIndefinida($order, $data, $e);
        }

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

    /**
     * Timeout, rede ou 5xx na cobrança: a Appmax PODE ter cobrado. Nada de
     * cancelar. Uma consulta imediata resolve o caso comum (cobrou e só a
     * resposta se perdeu); sem resposta conclusiva o pedido fica pending
     * "em verificação" — o webhook ou o pedidos:cancelar-cartao-preso
     * (que consulta a Appmax antes de qualquer coisa) fecham a conta.
     */
    protected function cobrancaIndefinida(Order $order, array $data, AppmaxException $e): RedirectResponse
    {
        Log::warning('Checkout: cobrança de cartão indefinida, pedido em verificação', [
            'order_id'        => $order->id,
            'appmax_order_id' => $order->appmax_order_id,
            'detalhe'         => $e->detalhe,
        ]);

        try {
            $situacao = $this->appmax->consultarSituacao($order);
        } catch (AppmaxException) {
            $situacao = 'desconhecido';
        }

        if ($situacao === 'recusado') {
            // Agora é recusa clara: segue o caminho normal de cancelamento.
            throw new AppmaxException(AppmaxService::MSG_CARTAO_RECUSADO, null, AppmaxException::RECUSADO,
                ($e->detalhe ?? '').' | consulta posterior: recusado');
        }

        $details = [
            'type'         => 'cartao',
            'installments' => (int) $data['installments'],
        ];

        if ($situacao === 'pago') {
            $order->update([
                'status'          => 'processing',
                'payment_status'  => 'paid',
                'paid_at'         => now(),
                'payment_details' => $details,
            ]);
        } else {
            $order->update([
                'payment_details' => $details + [
                    'em_verificacao' => true,
                    'erro_gateway'   => $e->detalhe,
                ],
            ]);
        }

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
            'zipcode'         => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'street'          => ['required', 'string', 'max:255'],
            'number'          => ['required', 'string', 'max:20'],
            'complement'      => ['nullable', 'string', 'max:255'],
            'neighborhood'    => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:255'],
            'state'           => ['required', 'string', 'size:2'],
            // Só o código da opção vem do front; valor e prazo o OrderService
            // recalcula do CEP (FreteService).
            'shipping_method' => ['required', 'in:'.implode(',', array_keys(config('frete.servicos')))],
            'payment_method'  => ['required', 'in:pix,cartao,boleto'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ];

        // Número/CVV crus são proibidos em qualquer método (o store() já
        // barra antes; isto é o cinto).
        foreach (self::CAMPOS_CARTAO_PROIBIDOS as $campo) {
            $rules[$campo] = ['prohibited'];
        }

        if ($request->input('payment_method') === 'cartao') {
            $rules += [
                'appmax_token' => ['required', 'string', 'max:255'],
                'card_name'    => ['required', 'string', 'max:100'],
                // Teto fixo aqui; o limite pela parcela mínima vem no store().
                'installments' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) config('appmax.max_installments', 3))],
            ];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'cpf.regex'         => 'Informe um CPF válido (000.000.000-00).',
            'zipcode.regex'     => 'Informe um CEP válido (00000-000).',
            'shipping_method.required' => 'Escolha uma opção de frete.',
            'shipping_method.in'       => 'Escolha uma opção de frete.',
            'appmax_token.required' => self::MSG_CARTAO_NAO_VALIDADO,
            'installments.max'      => 'Parcelamos em até '.max(1, (int) config('appmax.max_installments', 3)).'x sem juros.',
        ];
    }
}
