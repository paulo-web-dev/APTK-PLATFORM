<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Dicas e Novidades — 3 posts de exemplo (leva 06) pra seção da home não
 * estrear vazia. Conteúdo real entra pelo admin (Novidades) e estes podem
 * ser editados/apagados por lá. Idempotente (upsert por slug).
 */
class PostsExemploSeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'slug'         => 'negroni-em-casa-3-detalhes',
                'title'        => 'Negroni em casa: 3 detalhes que mudam tudo',
                'excerpt'      => 'O copo, o gelo e o tempo de mexer — o clássico italiano no seu melhor.',
                'body'         => "O Negroni é generoso: três partes iguais e nenhum segredo — em teoria.\n\nNa prática, o copo baixo e pesado gelado de véspera, um gelo grande e denso que derrete devagar e 20 segundos de bailarina fazem a diferença entre um bom Negroni e um Negroni memorável.\n\nCom a versão engarrafada da APTK, o trabalho já está pronto: resfrie, sirva sobre a pedra e finalize com um twist de laranja.",
                'published_at' => now()->subDays(2),
            ],
            [
                'slug'         => 'gelo-cristal-por-que-importa',
                'title'        => 'Gelo cristal: por que ele importa no seu drink',
                'excerpt'      => 'Diluição lenta, visual impecável — o papel do gelo na alta coquetelaria.',
                'body'         => "O gelo é ingrediente, não acessório.\n\nUma pedra cristalina e densa derrete até 4 vezes mais devagar que o gelo comum: o drink permanece na temperatura certa sem virar água. E a transparência não é estética à toa — é sinal de água pura e congelamento controlado.\n\nÉ por isso que a Ice4Pros abastece algumas das melhores casas do país — e o mesmo gelo pode estar no seu copo.",
                'published_at' => now()->subDays(9),
            ],
            [
                'slug'         => 'como-harmonizar-drinks-e-comida',
                'title'        => 'Como harmonizar drinks e comida sem errar',
                'excerpt'      => 'Regras simples pra acertar do aperitivo à sobremesa.',
                'body'         => "Harmonizar drink e prato segue uma lógica parecida com a do vinho: intensidade conversa com intensidade.\n\nCoquetéis cítricos e amargos abrem o apetite — perfeitos de aperitivo. Drinks com corpo e madeira acompanham carnes e pratos intensos. Para sobremesas, procure o contraste: um amargo elegante ao lado do doce.\n\nNa dúvida, comece pelo clássico da casa e vá provando — repertório se constrói no copo.",
                'published_at' => now()->subDays(16),
            ],
        ];

        foreach ($posts as $post) {
            Post::updateOrCreate(
                ['slug' => $post['slug']],
                $post + ['active' => true],
            );
        }
    }
}
