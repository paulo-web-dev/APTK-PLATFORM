<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Cria uma assinatura para o usuário, dentro de uma transação.
     * (Não processa pagamento — a recorrência de cobrança é integrada a um gateway depois.)
     *
     * @param  array<string, mixed>  $data  dados já validados (endereço + pagamento)
     */
    public function subscribe(User $user, SubscriptionPlan $plan, array $data): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $data) {
            $address = Address::create([
                'user_id'      => $user->id,
                'label'        => 'Assinatura',
                'name'         => $data['name'],
                'street'       => $data['street'],
                'number'       => $data['number'],
                'complement'   => $data['complement'] ?? null,
                'neighborhood' => $data['neighborhood'],
                'city'         => $data['city'],
                'state'        => strtoupper($data['state']),
                'zipcode'      => $data['zipcode'],
                'is_default'   => $user->addresses()->count() === 0,
            ]);

            $addressLine = sprintf(
                '%s, %s%s — %s, %s/%s — CEP %s',
                $address->street,
                $address->number,
                $address->complement ? ' ('.$address->complement.')' : '',
                $address->neighborhood,
                $address->city,
                $address->state,
                $address->zipcode,
            );

            return Subscription::create([
                'user_id'              => $user->id,
                'subscription_plan_id' => $plan->id,
                'status'               => 'active',
                'price'                => $plan->price,
                'interval'             => $plan->interval,
                'recipient_name'       => $address->name,
                'shipping_address'     => $addressLine,
                'payment_method'       => $data['payment_method'],
                'started_at'           => now(),
                'next_renewal_at'      => $this->nextRenewal($plan->interval),
                'notes'                => $data['notes'] ?? null,
            ]);
        });
    }

    /** Próxima data de renovação a partir de agora, conforme o ciclo. */
    public function nextRenewal(string $interval): Carbon
    {
        return match ($interval) {
            'weekly'    => now()->addWeek(),
            'quarterly' => now()->addMonths(3),
            'yearly'    => now()->addYear(),
            default     => now()->addMonth(),
        };
    }
}
