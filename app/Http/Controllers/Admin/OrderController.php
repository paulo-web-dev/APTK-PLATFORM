<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Services\AppmaxService;
use App\Services\AvisoPedido;
use App\Services\ReconciliacaoPagamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    /**
     * Status que o admin pode GRAVAR. 'processing' é o "Pago" (o mesmo do
     * checkout); o 'paid' antigo segue exibido como Pago, mas não é mais
     * gravado. Estorno não entra aqui: é pelo botão da Appmax.
     */
    private const STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

    public function index(Request $request): View
    {
        $active = $request->query('status');

        $query = Order::with('user')->withCount('items')
            ->withExists(['items as tem_custom' => fn ($q) => $q->whereNotNull('personalizacao')])
            ->latest();

        if (in_array($active, Order::STATUS_FILTROS, true)) {
            $query->comStatus($active); // mesmo critério do rótulo
        } elseif ($active === 'a_conferir') {
            $query->whereIn('id', ReconciliacaoPagamento::aConferir()->select('id'));
        }

        // Busca: CPF (com ou sem pontuação), número (APTK-00012 ou 12), nome ou e-mail.
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $digitos = preg_replace('/\D+/', '', $search);

            $query->where(function ($q) use ($search, $digitos) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                  ->orWhere('ship_name', 'like', "%{$search}%");

                if (strlen($digitos) >= 3) {
                    $q->orWhere('ship_cpf', 'like', "%{$digitos}%")
                      ->orWhereHas('user', fn ($u) => $u->where('cpf', 'like', "%{$digitos}%"));
                }
                if ($digitos !== '' && strlen($digitos) <= 9) {
                    $q->orWhere('id', (int) $digitos);
                }
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders'    => $orders,
            'statuses'  => Order::STATUS_FILTROS,
            'active'    => $active,
            'search'    => $search,
            'aConferir' => ReconciliacaoPagamento::aConferir()->count(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items']);

        return view('admin.orders.show', [
            'order'    => $order,
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ]);

        $order->status = $data['status'];

        if ($data['status'] === 'processing') {
            $order->payment_status = 'paid';
        } elseif ($data['status'] === 'cancelled') {
            $order->payment_status = 'failed';
        }

        $order->save();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Status do pedido atualizado.');
    }
    /**
     * Botão "Enviar aviso via e-mail" (leva 16): envia na hora para
     * PEDIDOS_NOTIFICAR, mesmo que o aviso automático já tenha saído. O
     * resultado (ou o erro do SMTP) volta no flash e fica gravado no pedido.
     */
    public function notify(Order $order, AvisoPedido $aviso): RedirectResponse
    {
        try {
            $aviso->enviar($order);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', 'O aviso não foi enviado: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Aviso enviado para '.implode(', ', AvisoPedido::destinatarios()).'.');
    }

    /**
     * Estorno total via Appmax (só para pedidos pagos e vinculados ao gateway).
     * A confirmação definitiva vem pelo webhook (OrderRefund).
     */
    public function refund(Order $order, AppmaxService $appmax): RedirectResponse
    {
        if (! $order->isPaid() || ! $order->appmax_order_id) {
            return back()->with('error', 'Este pedido não está pago via Appmax — nada a estornar.');
        }

        try {
            $appmax->refund($order);
        } catch (AppmaxException $e) {
            // Admin vê o texto cru da Appmax junto (o cliente nunca vê).
            return back()->with('error', $e->getMessage().($e->detalhe ? ' ('.$e->detalhe.')' : ''));
        }

        $order->update(['payment_status' => 'refunded', 'status' => 'cancelled']);

        return back()->with('status', 'Estorno solicitado na Appmax. O status final chega pelo webhook.');
    }

}
