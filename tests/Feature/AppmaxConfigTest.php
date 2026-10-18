<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Services\AppmaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Modelo "API do painel" (loja própria): credenciais, URLs e external_id
 * pelo .env, com o fluxo de instalação (settings) como fallback legado.
 * E a regra de retry: só token e GET, nunca POST de pedido/pagamento.
 */
class AppmaxConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Estado "nada no .env": cada teste liga o que precisa.
        config([
            'appmax.client_id'              => null,
            'appmax.client_secret'          => null,
            'appmax.merchant_client_id'     => null,
            'appmax.merchant_client_secret' => null,
            'appmax.auth_url'               => null,
            'appmax.api_url'                => null,
            'appmax.external_id'            => null,
            'appmax.environment'            => 'sandbox',
            'appmax.http_retry_sleep_ms'    => 0,
        ]);
    }

    /** Dispara uma chamada autenticada e devolve as requests feitas. */
    protected function chamarGet(array $respostas = []): array
    {
        $feitas = [];
        Http::fake(function (Request $req) use (&$feitas, &$respostas) {
            $feitas[] = $req;
            $host = (string) parse_url($req->url(), PHP_URL_HOST);

            if (str_starts_with($host, 'auth.')) {
                return Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]);
            }

            return array_shift($respostas) ?? Http::response(['data' => ['order' => ['id' => 20, 'status' => 'aprovado']]]);
        });

        app(AppmaxService::class)->consultarSituacao(new Order(['appmax_order_id' => 20]));

        return $feitas;
    }

    protected function legado(): void
    {
        Setting::set('appmax_merchant_client_id', 'cid-setting', 'appmax');
        Setting::set('appmax_merchant_client_secret', 'secret-setting', 'appmax');
        Setting::set('appmax_install_external_id', 'ext-setting', 'appmax');
    }

    // ---- prioridade .env > settings --------------------------------------

    public function test_credenciais_do_env_tem_prioridade_sobre_as_settings(): void
    {
        $this->legado();
        config(['appmax.client_id' => 'cid-env', 'appmax.client_secret' => 'secret-env']);

        $auth = $this->chamarGet()[0];

        $this->assertSame('cid-env', $auth->data()['client_id']);
        $this->assertSame('secret-env', $auth->data()['client_secret']);
    }

    public function test_sem_env_cai_nas_settings_da_instalacao(): void
    {
        $this->legado();

        $auth = $this->chamarGet()[0];

        $this->assertSame('cid-setting', $auth->data()['client_id']);
        $this->assertSame('secret-setting', $auth->data()['client_secret']);
    }

    public function test_par_incompleto_no_env_nao_mistura_fontes(): void
    {
        $this->legado();
        config(['appmax.client_id' => 'cid-env']); // sem secret no .env

        $auth = $this->chamarGet()[0];

        $this->assertSame('cid-setting', $auth->data()['client_id']);
        $this->assertSame('secret-setting', $auth->data()['client_secret']);
    }

    public function test_urls_do_env_tem_prioridade_sobre_o_ambiente(): void
    {
        config([
            'appmax.client_id'     => 'cid-env',
            'appmax.client_secret' => 'secret-env',
            'appmax.auth_url'      => 'https://auth.appmax.com.br/oauth2/token',
            'appmax.api_url'       => 'https://api.appmax.com.br/',
        ]);

        [$auth, $get] = $this->chamarGet();

        $this->assertSame('https://auth.appmax.com.br/oauth2/token', $auth->url());
        $this->assertSame('https://api.appmax.com.br/v1/orders/20', $get->url());
    }

    public function test_sem_urls_no_env_usa_o_mapa_do_appmax_env(): void
    {
        $this->legado();

        [$auth, $get] = $this->chamarGet();

        $this->assertSame('https://auth.sandboxappmax.com.br/oauth2/token', $auth->url());
        $this->assertStringStartsWith('https://api.sandboxappmax.com.br/', $get->url());
    }

    public function test_external_id_do_env_tem_prioridade_sobre_a_setting(): void
    {
        $this->legado();
        $this->assertSame('ext-setting', AppmaxService::externalId());

        config(['appmax.external_id' => 'ext-env']);
        $this->assertSame('ext-env', AppmaxService::externalId());
    }

    public function test_script_do_appmax_js_acompanha_o_ambiente_da_api_url(): void
    {
        // APPMAX_ENV diz sandbox, mas a API é a de produção: vale a API.
        config(['appmax.api_url' => 'https://api.appmax.com.br']);
        $this->assertSame('https://scripts.appmax.com.br/appmax.min.js', AppmaxService::scriptUrl());

        config(['appmax.api_url' => 'https://api.sandboxappmax.com.br', 'appmax.environment' => 'production']);
        $this->assertSame('https://scripts.sandboxappmax.com.br/appmax.min.js', AppmaxService::scriptUrl());

        config(['appmax.api_url' => null, 'appmax.environment' => 'production']);
        $this->assertSame('https://scripts.appmax.com.br/appmax.min.js', AppmaxService::scriptUrl());
    }

    // ---- retry: token e GET sim, POST nunca -------------------------------

    public function test_get_repete_em_5xx_e_falha_de_rede(): void
    {
        $this->legado();

        $feitas = $this->chamarGet([
            Http::response(['message' => 'Bad Gateway'], 502),
            Http::response(['message' => 'Bad Gateway'], 503),
        ]);

        $this->assertCount(4, $feitas); // token + 3 GETs (2 falhas + sucesso)
    }

    public function test_token_repete_em_falha_de_rede(): void
    {
        $this->legado();
        $tentativasToken = 0;

        Http::fake(function (Request $req) use (&$tentativasToken) {
            if (str_starts_with((string) parse_url($req->url(), PHP_URL_HOST), 'auth.')) {
                if (++$tentativasToken < 3) {
                    throw new ConnectionException('reset');
                }

                return Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]);
            }

            return Http::response(['data' => ['order' => ['status' => 'aprovado']]]);
        });

        $this->assertSame('pago', app(AppmaxService::class)->consultarSituacao(new Order(['appmax_order_id' => 20])));
        $this->assertSame(3, $tentativasToken);
    }

    public function test_post_de_pagamento_nunca_repete_nem_em_5xx_rede_ou_401(): void
    {
        $this->legado();

        // Um fake só (Http::fake empilha); a falha da vez troca por referência.
        $posts = 0;
        $falha = null;
        Http::fake(function (Request $req) use (&$posts, &$falha) {
            if (str_starts_with((string) parse_url($req->url(), PHP_URL_HOST), 'auth.')) {
                return Http::response(['access_token' => 'bearer-x', 'expires_in' => 3600]);
            }
            $posts++;

            return $falha();
        });

        foreach ([
            fn () => Http::response(['message' => 'Bad Gateway'], 502),
            fn () => throw new ConnectionException('timeout'),
            fn () => Http::response(['message' => 'Unauthenticated'], 401),
        ] as $proxima) {
            $posts = 0;
            $falha = $proxima;

            $order = new Order(['appmax_order_id' => 20, 'appmax_customer_id' => 10]);

            try {
                app(AppmaxService::class)->payWithCreditCard($order, [
                    'token' => 'tok', 'name' => 'A', 'document_number' => '19100000000', 'installments' => 1,
                ]);
                $this->fail('deveria lançar');
            } catch (\App\Exceptions\AppmaxException) {
            }

            $this->assertSame(1, $posts);
        }
    }
}
