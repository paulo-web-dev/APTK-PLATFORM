<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::latest()->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.form', [
            'coupon' => new Coupon(['type' => 'percent', 'active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Cupom criado.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validateData($request, $coupon));

        return redirect()->route('admin.coupons.index')->with('status', 'Cupom atualizado.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Cupom removido.');
    }

    // ------------------------------------------------------------------ //

    private function validateData(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type'       => ['required', 'in:percent,fixed'],
            'value'      => ['required', 'numeric', 'min:0'],
            'min_order'  => ['nullable', 'numeric', 'min:0'],
            'max_uses'   => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $data['code']      = strtoupper($data['code']);
        $data['min_order'] = $data['min_order'] ?? 0;
        $data['active']    = $request->boolean('active');

        return $data;
    }
}
