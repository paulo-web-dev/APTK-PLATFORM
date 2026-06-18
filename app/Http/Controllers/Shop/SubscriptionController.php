<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    /** Página de confirmação da assinatura (endereço + pagamento). */
    public function checkout(SubscriptionPlan $plan): View|RedirectResponse
    {
        if (! $plan->active || ! $plan->isSelfServe()) {
            return redirect()->route('pages.show', 'clube');
        }

        // Pré-preenche com o último endereço salvo, se houver.
        $address = auth()->user()->addresses()->latest()->first();

        return view('subscription.checkout', compact('plan', 'address'));
    }

    /** Efetiva a assinatura. */
    public function store(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        if (! $plan->active || ! $plan->isSelfServe()) {
            return redirect()->route('pages.show', 'clube');
        }

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'zipcode'        => ['required', 'string', 'max:9'],
            'street'         => ['required', 'string', 'max:255'],
            'number'         => ['required', 'string', 'max:20'],
            'complement'     => ['nullable', 'string', 'max:255'],
            'neighborhood'   => ['required', 'string', 'max:255'],
            'city'           => ['required', 'string', 'max:255'],
            'state'          => ['required', 'string', 'size:2'],
            'payment_method' => ['required', 'in:pix,cartao,boleto'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $this->subscriptions->subscribe($request->user(), $plan, $data);

        return redirect()->route('subscription.index')
            ->with('subscription_ok', "Pronto! Sua assinatura do plano {$plan->name} está ativa.");
    }

    /** "Minha assinatura" — área logada. */
    public function index(): View
    {
        $subscriptions = auth()->user()
            ->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        return view('account.subscription', compact('subscriptions'));
    }

    public function pause(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);

        if ($subscription->isActive()) {
            $subscription->update(['status' => 'paused', 'paused_at' => now()]);
        }

        return back()->with('subscription_ok', 'Assinatura pausada. Você pode retomar quando quiser.');
    }

    public function resume(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);

        if ($subscription->isPaused()) {
            $subscription->update([
                'status'          => 'active',
                'paused_at'       => null,
                'next_renewal_at' => $this->subscriptions->nextRenewal($subscription->interval),
            ]);
        }

        return back()->with('subscription_ok', 'Assinatura retomada.');
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $this->authorizeOwner($subscription);

        if (! $subscription->isCancelled()) {
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return back()->with('subscription_ok', 'Assinatura cancelada.');
    }

    private function authorizeOwner(Subscription $subscription): void
    {
        abort_unless($subscription->user_id === auth()->id(), 403);
    }
}
