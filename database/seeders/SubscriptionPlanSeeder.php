<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        /*
         | Leva 01 do feedback: planos renomeados —
         |   Descoberta → Admirador · Clássicos → Bronze · Premium → Prata
         |   Small Batch → Ouro · Corporativo → Aged
         | Os SLUGS são mantidos de propósito: são a chave do updateOrCreate
         | e das rotas de assinatura — mudar o slug criaria planos duplicados.
         | Programação, diferenciais, pagamentos e renovação: 2ª etapa.
         */
        $plans = [
            [
                'slug' => 'descoberta', 'name' => 'Admirador', 'kicker' => 'Entrada no universo APTK',
                'price' => 89.00, 'featured' => false, 'sort_order' => 0,
                'perks' => ['1 garrafa de 375 ml por mês', 'Curadoria rotativa de clássicos', 'Cancele quando quiser'],
            ],
            [
                'slug' => 'classicos', 'name' => 'Bronze', 'kicker' => 'Curadoria mensal',
                'price' => 149.00, 'featured' => true, 'sort_order' => 1,
                'perks' => ['2 drinks prontos (375 ml) por mês', 'Frete grátis para todo o Brasil', 'Acesso antecipado aos lançamentos'],
            ],
            [
                'slug' => 'premium', 'name' => 'Prata', 'kicker' => 'Acesso especial',
                'price' => 249.00, 'featured' => false, 'sort_order' => 2,
                'perks' => ['2 garrafas de 750 ml por mês', '1 edição limitada por trimestre', 'Brindes e convites exclusivos'],
            ],
            [
                'slug' => 'small-batch', 'name' => 'Ouro', 'kicker' => 'Edições artesanais',
                'price' => 329.00, 'featured' => false, 'sort_order' => 3,
                'perks' => ['Seleção de small batches do mês', 'Rótulos numerados e raros', 'Convites para experiências'],
            ],
            [
                'slug' => 'corporativo', 'name' => 'Aged', 'kicker' => 'Empresas e equipes',
                'price' => null, 'price_label' => 'Sob consulta', 'featured' => false, 'sort_order' => 4,
                'perks' => ['Volume e curadoria sob medida', 'Faturamento PJ e nota fiscal', 'Branding e kits para a empresa'],
            ],
        ];

        foreach ($plans as $p) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name'        => $p['name'],
                    'kicker'      => $p['kicker'],
                    'price'       => $p['price'] ?? null,
                    'price_label' => $p['price_label'] ?? null,
                    'interval'    => 'monthly',
                    'perks'       => $p['perks'],
                    'featured'    => $p['featured'],
                    'active'      => true,
                    'sort_order'  => $p['sort_order'],
                ],
            );
        }
    }
}
