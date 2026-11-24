<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::latest()->withCount('usos')->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['type' => 'percent', 'active' => true, 'max_per_customer' => 1]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $coupon = Coupon::create($this->validateData($request));

        return redirect()->route('admin.coupons.show', $coupon)->with('status', 'Cupom criado.');
    }

    /** Relatório do cupom: usos, pagos, faturamento e desconto concedido. */
    public function show(Coupon $coupon): View
    {
        $pagos = $coupon->orders()->whereIn('status', CouponService::STATUS_COMPRA);

        return view('admin.coupons.show', [
            'coupon'      => $coupon,
            'usos'        => $coupon->usos()->count(),
            'pagos'       => (clone $pagos)->count(),
            'faturamento' => (float) (clone $pagos)->sum('total'),
            'desconto'    => (float) (clone $pagos)->sum('discount'),
            'pedidos'     => $coupon->orders()->with('user')->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validateData($request, $coupon));

        return redirect()->route('admin.coupons.show', $coupon)->with('status', 'Cupom atualizado.');
    }

    /** Cupom já usado não se apaga (os pedidos guardam o código): inativar. */
    public function destroy(Coupon $coupon): RedirectResponse
    {
        if ($coupon->orders()->exists()) {
            return redirect()->route('admin.coupons.index')
                ->with('status', "O cupom {$coupon->code} já foi usado em pedidos: desmarque \"Ativo\" para desligá-lo.");
        }

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Cupom removido.');
    }

    // ------------------------------------------------------------------ //

    private function validateData(Request $request, ?Coupon $coupon = null): array
    {
        // Admin: maiúsculas e sem espaço nas pontas; espaço no meio é recusado
        // (o cliente pode digitar com espaço: CouponService::normalizar tira).
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name'             => ['nullable', 'string', 'max:120'],
            'type'             => ['required', 'in:percent,fixed'],
            'value'            => ['required', 'numeric', 'gt:0', Rule::when($request->input('type') === 'percent', ['max:100'])],
            'min_order'        => ['nullable', 'numeric', 'min:0'],
            'max_uses'         => ['nullable', 'integer', 'min:1'],
            'max_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at'        => ['nullable', 'date_format:d/m/Y H:i'],
            'expires_at'       => ['nullable', 'date_format:d/m/Y H:i'],
        ], [
            'code.regex' => 'O código aceita só letras, números, hífen e sublinhado, sem espaço.',
        ]);

        foreach (['starts_at', 'expires_at'] as $campo) {
            $data[$campo] = filled($data[$campo] ?? null)
                ? Carbon::createFromFormat('d/m/Y H:i', $data[$campo], Coupon::FUSO)->utc()
                : null;
        }

        if ($data['starts_at'] && $data['expires_at'] && $data['expires_at']->lte($data['starts_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'expires_at' => 'O fim da validade precisa ser depois do início.',
            ]);
        }

        $data['min_order']           = $data['min_order'] ?? 0;
        $data['active']              = $request->boolean('active');
        $data['first_purchase_only'] = $request->boolean('first_purchase_only');

        return $data;
    }
}
