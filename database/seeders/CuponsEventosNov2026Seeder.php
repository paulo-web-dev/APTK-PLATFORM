<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Vouchers dos eventos Mitre e Communit (mailing nov/2026): 15% nos
 * produtos, só primeira compra, 1 uso por cliente, de agora até
 * 31/12/2026 23:59 (Brasília), ativos.
 *
 * Idempotente: cria só o que falta e NUNCA sobrescreve um cupom que já
 * existe (o admin pode ter ajustado). Produção:
 *   php artisan db:seed --class=CuponsEventosNov2026Seeder --force
 */
class CuponsEventosNov2026Seeder extends Seeder
{
    public const CUPONS = [
        'MITRE15'    => 'Evento Mitre - mailing nov/2026',
        'COMMUNIT15' => 'Evento Communit - mailing nov/2026',
    ];

    public function run(): void
    {
        $fim = Carbon::create(2026, 12, 31, 23, 59, 0, Coupon::FUSO)->utc();

        foreach (self::CUPONS as $codigo => $nome) {
            $coupon = Coupon::firstOrCreate(['code' => $codigo], [
                'name'                => $nome,
                'type'                => 'percent',
                'value'               => 15,
                'min_order'           => 0,
                'max_uses'            => null,
                'max_per_customer'    => 1,
                'starts_at'           => now(),
                'expires_at'          => $fim,
                'first_purchase_only' => true,
                'active'              => true,
            ]);

            $this->command?->info($coupon->wasRecentlyCreated
                ? "{$codigo}: criado."
                : "{$codigo}: já existia, não alterado.");
        }
    }
}
