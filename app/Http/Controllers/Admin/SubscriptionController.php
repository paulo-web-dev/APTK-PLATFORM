<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $subscriptions = Subscription::with(['user', 'plan'])
            ->when(in_array($status, ['active', 'paused', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'all'       => Subscription::count(),
            'active'    => Subscription::where('status', 'active')->count(),
            'paused'    => Subscription::where('status', 'paused')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
        ];

        return view('admin.subscriptions.index', compact('subscriptions', 'status', 'counts'));
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load(['user', 'plan']);

        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function pause(Subscription $subscription): RedirectResponse
    {
        if ($subscription->isActive()) {
            $subscription->update(['status' => 'paused', 'paused_at' => now()]);
        }

        return back()->with('status', 'Assinatura pausada.');
    }

    public function resume(Subscription $subscription): RedirectResponse
    {
        if ($subscription->isPaused()) {
            $subscription->update([
                'status'          => 'active',
                'paused_at'       => null,
                'next_renewal_at' => $this->subscriptions->nextRenewal($subscription->interval),
            ]);
        }

        return back()->with('status', 'Assinatura retomada.');
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        if (! $subscription->isCancelled()) {
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return back()->with('status', 'Assinatura cancelada.');
    }
}
