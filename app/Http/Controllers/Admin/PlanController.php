<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->paginate(20);

        return view('admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('admin.plans.form', [
            'plan' => new SubscriptionPlan(['interval' => 'monthly', 'active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $data['slug'] = $this->uniqueSlug($data['name']);

        SubscriptionPlan::create($data);

        return redirect()->route('admin.plans.index')->with('status', 'Plano criado.');
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('admin.plans.form', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        // O slug permanece estável para não quebrar links de assinatura existentes.
        $plan->update($this->validateData($request));

        return redirect()->route('admin.plans.index')->with('status', 'Plano atualizado.');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('status', 'Não é possível excluir: há assinaturas neste plano. Desative-o em vez de excluir.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Plano removido.');
    }

    // ------------------------------------------------------------------ //

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'kicker'      => ['nullable', 'string', 'max:255'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:255'],
            'interval'    => ['required', 'in:monthly,quarterly,yearly'],
            'perks'       => ['nullable', 'string'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        // perks: uma por linha → array
        $data['perks'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['perks'] ?? '')))
            ->map(fn ($p) => trim($p))
            ->filter()
            ->values()
            ->all();

        $data['price']      = $data['price'] !== null && $data['price'] !== '' ? $data['price'] : null;
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['featured']   = $request->boolean('featured');
        $data['active']     = $request->boolean('active');

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plano';
        $slug = $base;
        $i = 2;

        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
