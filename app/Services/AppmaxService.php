<?php

namespace App\Services;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da API Appmax v4 — Pix, cartão e boleto.
 *
 * Modelo em uso: credenciais da PRÓPRIA LOJA, geradas no painel (menu API,
 * doc guides/meus-tokens) e lidas do .env. O fluxo de instalação de app
 * (settings gravadas pelo health check) segue como fallback legado.
 *
 * Autenticação (doc 1.1.5): server-to-server com token Bearer de CURTA
 * duração, sem refresh token. As credenciais do MERCHANT (recebidas no
 * health check da instalação e gravadas em settings, grupo "appmax")
 * trocam por um access_token em /oauth2/token; renovamos quando expira.
 *
 * Fluxo de venda (docs 3, 4.1, 5.x):
 *   1. POST /v1/customers  → data.customer.id
 *   2. POST /v1/orders     → data.order.id   (⚠️ VALORES EM CENTAVOS, int)
 *   3. POST /v1/payments/{pix|credit-card|boleto}
 *
 * Erros: 422 vem como {errors:{message:{campo:[msgs]}}} ou {errors:{campo:[msgs]}};
 *        4xx pode vir como {error:{message}} ou {message}. O texto cru vai
 *        pro log/admin (AppmaxException::$detalhe); o cliente só vê as
 *        mensagens amigáveis abaixo.
 */
class AppmaxService
{
    protected const TOKEN_CACHE_KEY = 'appmax_merchant_access_token';

    public const MSG_INDISPONIVEL = 'O pagamento está indisponível no momento. Tente novamente em instantes.';
    public const MSG_DADOS_INVALIDOS = 'Alguns dados não foram aceitos pelo processador de pagamento. Confira nome, CPF, telefone e endereço e tente de novo.';
    public const MSG_CARTAO_RECUSADO = 'Pagamento não autorizado pela operadora do cartão. Confira os dados ou tente outro cartão.';

    /** Status da Appmax (doc "Status de pedidos") → situação do pagamento aqui. */
    protected const SITUACAO_POR_STATUS = [
        'aprovado'                       => 'pago',
        'integrado'                      => 'pago',
        'pendente_integracao'            => 'pago',
        'pendente_integracao_em_analise' => 'pago',
        'autorizado'                     => 'em_analise', // antifraude, ainda pode ir pra aprovado
        'pendente'                       => 'pendente',
        'cancelado'                      => 'recusado',
        'recusado_por_risco'             => 'recusado',
        'estornado'                      => 'estornado',
        'chargeback_em_tratativa'        => 'estornado',
        'chargeback_em_disputa'          => 'estornado',
        'chargeback_perdido'             => 'estornado',
        'chargeback_vencido'             => 'estornado',
    ];

    /* -----------------------------------------------------------------
     | Credenciais e autenticação do merchant
     ------------------------------------------------------------------ */

    /**
     * Credenciais do merchant, nesta ordem:
     *   1. APPMAX_CLIENT_ID/SECRET do .env (painel → API, loja própria);
     *   2. settings gravadas pelo health check do fluxo de instalação (legado);
     *   3. APPMAX_MERCHANT_CLIENT_ID/SECRET (fallback legado).
     * O par é usado junto: nunca client_id de uma fonte com secret de outra.
     */
    protected function merchantCredentials(): array
    {
        if (filled(config('appmax.client_id')) && filled(config('appmax.client_secret'))) {
            return [
                'client_id'     => config('appmax.client_id'),
                'client_secret' => config('appmax.client_secret'),
            ];
        }

        return [
            'client_id'     => Setting::get('appmax_merchant_client_id') ?: config('appmax.merchant_client_id'),
            'client_secret' => Setting::get('appmax_merchant_client_secret') ?: config('appmax.merchant_client_secret'),
        ];
    }

    /** URL do /oauth2/token: APPMAX_AUTH_URL > mapa por APPMAX_ENV. */
    public static function authUrl(): string
    {
        return (string) (config('appmax.auth_url')
            ?: config('appmax.auth_urls.'.config('appmax.environment', 'sandbox')));
    }

    /** Base da API: APPMAX_API_URL > mapa por APPMAX_ENV. */
    public static function apiUrl(): string
    {
        return rtrim((string) (config('appmax.api_url')
            ?: config('appmax.api_urls.'.config('appmax.environment', 'sandbox'))), '/');
    }

    /**
     * Build do Appmax JS no MESMO ambiente da API — script de um ambiente
     * com o external_id do outro dá "Merchant not found".
     */
    public static function scriptUrl(): string
    {
        $host = strtolower((string) parse_url(self::apiUrl(), PHP_URL_HOST));

        $env = match (true) {
            $host === 'api.appmax.com.br'           => 'production',
            str_contains($host, 'sandboxappmax')    => 'sandbox',
            default                                 => config('appmax.environment', 'sandbox'),
        };

        return (string) config("appmax.script_urls.{$env}");
    }

    /** External ID do Appmax JS: APPMAX_EXTERNAL_ID > setting da instalação. */
    public static function externalId(): ?string
    {
        return config('appmax.external_id') ?: Setting::get('appmax_install_external_id');
    }

    /**
     * Retry só em falha de rede ou 5xx — usado APENAS no token e em GET.
     * POST de pedido/pagamento nunca passa por aqui (cobrança dupla).
     */
    protected function comRetry(PendingRequest $http): PendingRequest
    {
        return $http->retry(
            max(1, (int) config('appmax.http_retry_times', 3)),
            max(0, (int) config('appmax.http_retry_sleep_ms', 300)),
            fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()),
            throw: false,
        );
    }

    public function isConfigured(): bool
    {
        $c = $this->merchantCredentials();

        return filled($c['client_id']) && filled($c['client_secret']);
    }

    /**
     * Access token do merchant (Bearer de curta duração, cacheado até
     * ~2 min antes de expirar; sem refresh token — reautentica ao vencer).
     */
    protected function merchantToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $c = $this->merchantCredentials();

        if (! filled($c['client_id']) || ! filled($c['client_secret'])) {
            throw new AppmaxException(self::MSG_INDISPONIVEL, null, AppmaxException::INDISPONIVEL,
                'Credenciais do merchant Appmax ausentes (instalação do app não concluída?).');
        }

        try {
            // O token pode repetir: ninguém é cobrado por pedir token.
            $response = $this->comRetry(Http::asForm()->timeout(config('appmax.http_timeout', 30)))
                ->post(self::authUrl(), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $c['client_id'],
                    'client_secret' => $c['client_secret'],
                ]);
        } catch (\Throwable $e) {
            // Mensagem da exceção de transporte: URL e motivo, nunca o corpo (secret).
            Log::error('Appmax: falha ao autenticar merchant', ['erro' => $e->getMessage()]);
            // Sem token nenhuma cobrança saiu: indisponível, não indefinido.
            throw new AppmaxException(self::MSG_INDISPONIVEL, null, AppmaxException::INDISPONIVEL,
                'Falha de conexão na autenticação: '.$e->getMessage());
        }

        $json = $response->json();

        if (! $response->successful() || empty($json['access_token'])) {
            Log::error('Appmax: autenticação do merchant recusada', ['status' => $response->status(), 'body' => $json]);
            throw new AppmaxException(self::MSG_INDISPONIVEL, null, AppmaxException::INDISPONIVEL,
                'Autenticação do merchant recusada (HTTP '.$response->status().').');
        }

        $ttl = max(60, (int) ($json['expires_in'] ?? 3600) - 120);
        Cache::put(self::TOKEN_CACHE_KEY, $json['access_token'], $ttl);

        return $json['access_token'];
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(self::apiUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($this->merchantToken())
            ->timeout(config('appmax.http_timeout', 30));
    }

    /** POST autenticado (ver request()). */
    protected function post(string $endpoint, array $body, string $friendlyError): array
    {
        return $this->request('post', $endpoint, $body, $friendlyError);
    }

    /**
     * Requisição autenticada.
     *
     * Repetição:
     *   - GET: retry em rede/5xx (comRetry) e, em 401 (Bearer vencido),
     *     renova o token e repete uma vez.
     *   - POST: NUNCA repete. Nem em 401: o token cacheado é descartado e a
     *     falha volta como INDISPONIVEL (nada foi processado); a próxima
     *     tentativa do cliente já sai com token novo.
     *
     * Classifica a falha (AppmaxException::$tipo):
     *   - timeout, rede, 5xx, 408, 2xx ilegível → INDEFINIDO (pode ter processado)
     *   - 4xx no cartão                         → RECUSADO (mensagem de recusa)
     *   - 422                                   → DADOS_INVALIDOS
     *   - demais 4xx                            → INDISPONIVEL (nada processado)
     *
     * @throws AppmaxException
     */
    protected function request(string $method, string $endpoint, array $body, string $friendlyError): array
    {
        $isGet = $method === 'get';
        $http = fn () => $isGet ? $this->comRetry($this->client()) : $this->client();

        try {
            $response = $http()->{$method}($endpoint, $body);

            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);

                if ($isGet) {
                    $response = $http()->{$method}($endpoint, $body);
                }
            }
        } catch (AppmaxException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Appmax: falha de conexão', ['endpoint' => $endpoint, 'erro' => $e->getMessage()]);
            throw new AppmaxException(self::MSG_INDISPONIVEL, null, AppmaxException::INDEFINIDO,
                'Falha de conexão/timeout em '.$endpoint.': '.$e->getMessage());
        }

        $json = $response->json();
        $status = $response->status();

        if ($response->successful() && is_array($json)) {
            return $json['data'] ?? $json;
        }

        $detalhe = 'HTTP '.$status.' em '.$endpoint.($this->extractErrorMessage($json) ? ': '.$this->extractErrorMessage($json) : '');

        Log::warning('Appmax: resposta com erro', [
            'endpoint' => $endpoint,
            'status'   => $status,
            'body'     => $this->safeErrorBody($endpoint, $json),
        ]);

        $apiResponse = is_array($json) ? $json : null;
        $isCartao = str_contains($endpoint, 'credit-card');

        [$mensagem, $tipo] = match (true) {
            $response->successful(), $status >= 500, $status === 408
                => [self::MSG_INDISPONIVEL, AppmaxException::INDEFINIDO],
            $isCartao && ! in_array($status, [401, 403, 429], true)
                => [$friendlyError, AppmaxException::RECUSADO],
            $status === 422
                => [self::MSG_DADOS_INVALIDOS, AppmaxException::DADOS_INVALIDOS],
            in_array($status, [401, 403, 429], true)
                => [self::MSG_INDISPONIVEL, AppmaxException::INDISPONIVEL],
            default
                => [$friendlyError, AppmaxException::INDISPONIVEL],
        };

        throw new AppmaxException($mensagem, $apiResponse, $tipo, $detalhe, $status);
    }

    /** Nunca logar corpo de resposta de cartão (pode ecoar dados sensíveis). */
    protected function safeErrorBody(string $endpoint, mixed $json): mixed
    {
        return str_contains($endpoint, 'credit-card') ? '[omitido - endpoint de cartão]' : $json;
    }

    /**
     * Texto CRU do erro da v4, para log/admin (nunca para o cliente).
     * Formatos: {errors:{message:{campo:[...]}}}, {errors:{campo:[...]}},
     * {errors:{message:"..."}}, {error:{message}} e {message}.
     */
    protected function extractErrorMessage(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        if (isset($json['errors']) && is_array($json['errors'])) {
            $porCampo = is_array($json['errors']['message'] ?? null)
                ? $json['errors']['message']
                : $json['errors'];

            $partes = collect($porCampo)
                ->map(fn ($msgs, $campo) => (is_string($campo) && $campo !== 'message' ? $campo.': ' : '')
                    .implode(' ', array_filter((array) $msgs, 'is_string')))
                ->filter(fn ($linha) => trim($linha) !== '' && ! str_ends_with($linha, ': '))
                ->values();

            if ($partes->isNotEmpty()) {
                return $partes->implode('; ');
            }
        }

        $msg = $json['error']['message'] ?? $json['message'] ?? null;

        return is_string($msg) && $msg !== '' ? $msg : null;
    }

    /* -----------------------------------------------------------------
     | 1) Cliente  —  POST /v1/customers  (doc 3)
     ------------------------------------------------------------------ */

    /**
     * @param  array $data  name, email, phone, cpf, zipcode, street, number,
     *                      complement, neighborhood, city, state
     * @return int id do cliente na Appmax
     */
    public function createCustomer(array $data, string $ip): int
    {
        [$firstName, $lastName] = $this->splitName($data['name']);

        $payload = [
            'first_name'      => $firstName,
            'last_name'       => $lastName ?: $firstName,
            'email'           => $data['email'],
            // A doc valida phone com MÁXIMO de 11 caracteres (DDD + número).
            'phone'           => substr($this->digits($data['phone'] ?? ''), 0, 11),
            'document_number' => $this->digits($data['cpf'] ?? ''),
            'address'         => [
                'postcode'   => substr($this->digits($data['zipcode']), 0, 8),
                'street'     => $data['street'],
                'number'     => (string) $data['number'],
                'complement' => $data['complement'] ?? '',
                'district'   => $data['neighborhood'],
                'city'       => $data['city'],
                'state'      => strtoupper($data['state']),
            ],
            'ip' => $ip,
        ];

        $data = $this->post('/v1/customers', $payload, 'Não foi possível registrar seus dados no pagamento. Confira as informações e tente de novo.');

        $id = $data['customer']['id'] ?? $data['id'] ?? null;

        if (! $id) {
            throw new AppmaxException('Resposta inesperada do processador de pagamento (cliente sem id).', $data);
        }

        return (int) $id;
    }

    /* -----------------------------------------------------------------
     | 2) Pedido  —  POST /v1/orders  (doc 4.1)  ⚠️ CENTAVOS
     ------------------------------------------------------------------ */

    public function createOrder(Order $order, int $appmaxCustomerId): int
    {
        $products = $order->items->map(function ($item) {
            $sku = $item->product_sku ?: ('APTK-'.$item->product_id);
            if ($item->size) {
                $sku .= '-'.$this->digits($item->size); // SKU único por volume
            }

            return [
                'sku'        => substr($sku, 0, 100),
                'name'       => substr($item->product_name.($item->size ? ' '.$item->size : ''), 0, 255),
                'quantity'   => (int) $item->qty,
                'unit_value' => $this->toCents($item->unit_price),
                'type'       => 'physical',
            ];
        })->values()->all();

        $payload = [
            'customer_id'    => $appmaxCustomerId,
            'products_value' => $this->toCents($order->subtotal),
            'discount_value' => $this->toCents($order->discount),
            'shipping_value' => $this->toCents($order->shipping_cost),
            'products'       => $products,
        ];

        $data = $this->post('/v1/orders', $payload, 'Não foi possível registrar o pedido no pagamento. Tente novamente.');

        $id = $data['order']['id'] ?? $data['id'] ?? null;

        if (! $id) {
            throw new AppmaxException('Resposta inesperada do processador de pagamento (pedido sem id).', $data);
        }

        return (int) $id;
    }

    /* -----------------------------------------------------------------
     | 3) Pagamentos  —  POST /v1/payments/*  (docs 5.2/5.3/5.4)
     ------------------------------------------------------------------ */

    /**
     * Pix: QR Code + copia-e-cola (EMV) + expiração.
     * Resposta em data.payment: pix_qrcode (PNG base64 sem prefixo), pix_emv,
     * pix_expiration_date ("Y-m-d H:i:s", horário de Brasília).
     */
    public function payWithPix(Order $order, string $cpf): array
    {
        // Vencimento por transação: Y-m-d H:i:s no fuso de Brasília, futuro.
        $expiracao = now('America/Sao_Paulo')
            ->addMinutes(max(1, (int) config('appmax.pix_expiration_minutes', 60)))
            ->format('Y-m-d H:i:s');

        $data = $this->post('/v1/payments/pix', [
            'order_id'     => $order->appmax_order_id,
            'payment_data' => [
                'pix' => [
                    'document_number' => $this->digits($cpf),
                    'expiration_date' => $expiracao,
                ],
            ],
        ], 'Não foi possível gerar a chave Pix. Tente novamente.');

        return [
            'qrcode'        => $this->findKey($data, ['pix_qrcode', 'qrcode', 'qr_code', 'qr_code_base64']),
            'emv'           => $this->findKey($data, ['pix_emv', 'emv', 'copy_paste', 'qr_code_text']),
            'expiration'    => $this->findKey($data, ['pix_expiration_date', 'expiration_date', 'expires_at', 'expiration']),
            'pay_reference' => $this->findKey($data, ['pay_reference', 'reference', 'payment_id']),
        ];
    }

    /**
     * Cartão de crédito (cobrança imediata; 4xx = recusa → AppmaxException).
     *
     * SÓ com token do Appmax JS: número, CVV e validade nunca passam pelo
     * nosso servidor (sem PCI-DSS a doc não permite). Validade vai dentro
     * do token — o payload é o do exemplo de checkout completo da doc.
     *
     * @param array $card token, name, document_number, installments
     */
    public function payWithCreditCard(Order $order, array $card): array
    {
        if (blank($card['token'] ?? null)) {
            throw new AppmaxException(self::MSG_CARTAO_RECUSADO, null, AppmaxException::DADOS_INVALIDOS,
                'Cobrança de cartão sem token do Appmax JS — bloqueada antes de chamar a API.');
        }

        $data = $this->post('/v1/payments/credit-card', [
            'order_id'     => $order->appmax_order_id,
            'customer_id'  => $order->appmax_customer_id,
            'payment_data' => [
                'credit_card' => [
                    'token'                  => (string) $card['token'],
                    'holder_document_number' => $this->digits($card['document_number']),
                    'holder_name'            => strtoupper($card['name']),
                    'installments'           => (int) ($card['installments'] ?? 1),
                    'soft_descriptor'        => substr((string) config('appmax.soft_descriptor', 'APTKSPIRITS'), 0, 13),
                ],
            ],
        ], self::MSG_CARTAO_RECUSADO);

        return [
            'pay_reference' => $this->findKey($data, ['pay_reference', 'reference', 'payment_id']),
            'upsell_hash'   => $this->findKey($data, ['upsell_hash']),
        ];
    }

    /** Boleto: linha digitável + PDF + vencimento. */
    public function payWithBoleto(Order $order, string $cpf): array
    {
        $data = $this->post('/v1/payments/boleto', [
            'order_id'     => $order->appmax_order_id,
            'payment_data' => [
                'boleto' => ['document_number' => $this->digits($cpf)],
            ],
        ], 'Não foi possível gerar o boleto. Tente novamente.');

        return [
            'digitable_line' => $this->findKey($data, ['digitable_line', 'boleto_payment_code', 'line', 'barcode']),
            'pdf'            => $this->findKey($data, ['pdf', 'pdf_url', 'url', 'boleto_url']),
            'due_date'       => $this->findKey($data, ['due_date', 'expiration_date']),
            'pay_reference'  => $this->findKey($data, ['pay_reference', 'reference', 'payment_id']),
        ];
    }

    /* -----------------------------------------------------------------
     | Consulta  —  GET /v1/orders/{id}  (reconciliação)
     ------------------------------------------------------------------ */

    /**
     * Situação do pagamento na Appmax: pago | em_analise | pendente |
     * recusado | estornado | inexistente | desconhecido. Usada quando a
     * cobrança ficou indefinida (timeout/5xx) e pelo pedidos:cancelar-cartao-preso.
     *
     * @throws AppmaxException  se a consulta falhar (aí NADA se decide)
     */
    public function consultarSituacao(Order $order): string
    {
        return $this->consultarPedido($order)['situacao'];
    }

    /**
     * Consulta completa (diagnóstico): status cru da Appmax, situação
     * traduzida e o corpo de data (valores em centavos, pagamento etc.).
     *
     * @return array{status: ?string, situacao: string, dados: array}
     *
     * @throws AppmaxException
     */
    public function consultarPedido(Order $order): array
    {
        try {
            $data = $this->request('get', '/v1/orders/'.(int) $order->appmax_order_id, [],
                'Não foi possível consultar o pedido na Appmax.');
        } catch (AppmaxException $e) {
            if ($e->httpStatus === 404) {
                // "Order not found": nada foi cobrado
                return ['status' => null, 'situacao' => 'inexistente', 'dados' => []];
            }

            throw $e;
        }

        $status = (string) ($data['order']['status'] ?? '');

        return [
            'status'   => $status !== '' ? $status : null,
            'situacao' => self::SITUACAO_POR_STATUS[$status] ?? 'desconhecido',
            'dados'    => $data,
        ];
    }

    /* -----------------------------------------------------------------
     | Estorno  —  POST /v1/orders/refund-request  (doc 6)
     | type: "total" | "partial" (value em centavos, obrigatório se partial).
     | 201 → {data:{message:"Refund request accepted"}}
     ------------------------------------------------------------------ */

    public function refund(Order $order, string $type = 'total', ?float $value = null): bool
    {
        $payload = [
            'order_id' => $order->appmax_order_id,
            'type'     => $type,
        ];

        if ($type === 'partial') {
            $payload['value'] = $this->toCents($value ?? 0);
        }

        $this->post('/v1/orders/refund-request', $payload, 'Não foi possível processar o estorno na Appmax.');

        return true;
    }

    /* -----------------------------------------------------------------
     | Parcelamento sem juros (a loja absorve)
     ------------------------------------------------------------------ */

    /**
     * Máximo de parcelas para um total: até appmax.max_installments, sem
     * parcela abaixo de appmax.min_installment_value. Nunca menos que 1.
     * A Appmax não calcula juros: cobra o total enviado, dividido.
     */
    public static function maxParcelas(float $total): int
    {
        $max = max(1, (int) config('appmax.max_installments', 3));
        $minimo = (float) config('appmax.min_installment_value', 30.00);

        if ($minimo <= 0) {
            return $max;
        }

        // Em centavos, pra 90,00 / 30,00 dar 3 e não 2,999...
        $porValor = intdiv((int) round($total * 100), (int) round($minimo * 100));

        return max(1, min($max, $porValor));
    }

    /* -----------------------------------------------------------------
     | Helpers
     ------------------------------------------------------------------ */

    /** R$ 189,90 (decimal) → 18990 (centavos, inteiro — exigência da doc 4.1). */
    protected function toCents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /** Busca recursiva do primeiro valor não-vazio entre nomes candidatos. */
    protected function findKey(array $data, array $candidates): mixed
    {
        foreach ($candidates as $key) {
            if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findKey($value, $candidates);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** "Paulo Orfanelli Silva" → ["Paulo", "Orfanelli Silva"] */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [trim($name)];

        return [substr($parts[0], 0, 100), substr($parts[1] ?? '', 0, 100)];
    }
}
