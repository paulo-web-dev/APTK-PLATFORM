<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Clube APTK — estrutura oficial 2026 (leva 05, doc "CLUBE APTK").
 *
 * Substitui a vitrine antiga (Admirador/Bronze/Prata/Ouro/Aged, que ficam
 * INATIVOS — assinaturas existentes não são tocadas) pelos 4 níveis novos:
 *   1. APTK Explorer      — grátis (base/CRM)
 *   2. APTK Collector     — R$ 199/mês
 *   3. APTK Connoisseur   — R$ 349/mês
 *   4. Le Cercle APTK     — por convite (não vendável: price null → isSelfServe() false)
 *
 * Rodar: php artisan db:seed --class=ClubePlanos2026Seeder
 * Idempotente: upsert por slug; pode rodar de novo sem duplicar.
 */
class ClubePlanos2026Seeder extends Seeder
{
    public function run(): void
    {
        // 1) Desativa a vitrine antiga (registros preservados).
        SubscriptionPlan::whereIn('slug', ['admirador', 'bronze', 'prata', 'ouro', 'aged'])
            ->update(['active' => false]);

        // 2) Estrutura 2026.
        $planos = [
            [
                'slug'        => 'aptk-explorer',
                'name'        => 'APTK Explorer',
                'kicker'      => 'Entrada no círculo',
                'price'       => 0,
                'price_label' => 'Grátis',
                'interval'    => 'sempre',
                'featured'    => false,
                'sort_order'  => 1,
                'perks'       => [
                    'Ganhe pontos de boas-vindas ao entrar',
                    '10% do valor das compras retorna em pontos',
                    'Newsletter com dicas de drinks pelo Alê',
                    'Novidades e lançamentos em primeira mão',
                ],
            ],
            [
                'slug'        => 'aptk-collector',
                'name'        => 'APTK Collector',
                'kicker'      => 'Pra quem coleciona momentos',
                'price'       => 199.00,
                'price_label' => 'R$ 199/mês',
                'interval'    => 'mês',
                'featured'    => true,
                'sort_order'  => 2,
                'perks'       => [
                    'Pontuação em dobro nas compras',
                    '2 garrafas por mês (não cumulativo)',
                    'Pré-venda de lançamentos',
                    'Acesso antecipado às campanhas sazonais',
                    'Brindes sazonais',
                    'Cupom para personalização de rótulos',
                ],
            ],
            [
                'slug'        => 'aptk-connoisseur',
                'name'        => 'APTK Connoisseur',
                'kicker'      => 'De comprador a colecionador',
                'price'       => 349.00,
                'price_label' => 'R$ 349/mês',
                'interval'    => 'mês',
                'featured'    => false,
                'sort_order'  => 3,
                'perks'       => [
                    'Linha exclusiva de Small Batches',
                    'Garrafas numeradas — algumas com assinatura do Alê',
                    'Produtos antes do mercado',
                    'Masterclass semestral com convite garantido',
                    'Concierge para presentes e customizações',
                ],
            ],
            [
                'slug'        => 'le-cercle-aptk',
                'name'        => 'Le Cercle APTK',
                'kicker'      => 'Somente por convite',
                'price'       => null, // não vendável — isSelfServe() = false
                'price_label' => 'Por convite',
                'interval'    => 'convite', // coluna NOT NULL no schema
                'featured'    => false,
                'sort_order'  => 4,
                'perks'       => [
                    'Convites secretos: jantares privados e degustações',
                    'Acesso a protótipos e edições que não chegam ao mercado',
                    'Possibilidade de cocriar um líquido com a casa',
                    'Viagens e ativações com parceiros',
                    'Nome gravado em uma edição anual',
                ],
            ],
        ];

        foreach ($planos as $plano) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plano['slug']],
                $plano + ['active' => true],
            );
        }
    }
}
