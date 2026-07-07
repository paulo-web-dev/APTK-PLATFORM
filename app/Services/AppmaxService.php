<?php

namespace App\Services;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da API Appmax v4 (fluxo de aplicativo) — Pix, cartão e boleto.
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
 * Erros: 422 vem como {errors:{message:{campo:[msgs]}}};
 *        4xx pode vir como {error:{message}} ou {message}.
 */
class AppmaxService
{
    protected const TOKEN_CACHE_KEY = 'appmax_merchant_access_token';

    /* -----------------------------------------------------------------
     | Credenciais e autenticação do merchant
     ------------------------------------------------------------------ */

    /** Credenciais do merchant: settings (gravadas na instalação) > .env. */
    protected function merchantCredentials(): array
    {
        return [
            'client_id'     => Setting::get('appmax_merchant_client_id') ?: config('appmax.merchant_client_id'),
            'client_secret' => Setting::get('appmax_merchant_client_secret') ?: config('appmax.merchant_client_secret'),
        ];
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
            throw new AppmaxException('Pagamento indisponível: credenciais do merchant Appmax ausentes (instalação do app não concluída?).');
        }

        $env     = config('appmax.environment', 'sandbox');
        $authUrl = config("appmax.auth_urls.{$env}");

        try {
            $response = Http::asForm()
                ->timeout(config('appmax.http_timeout', 30))
                ->post($authUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $c['client_id'],
                    'client_secret' => $c['client_secret'],
                ]);
        } catch (\Throwable $e) {
            Log::error('Appmax: falha ao autenticar merchant', ['erro' => $e->getMessage()]);
            throw new AppmaxException('Não conseguimos falar com o processador de pagamento. Tente novamente em instantes.');
        }

        $json = $response->json();

        if (! $response->successful() || empty($json['access_token'])) {
            Log::error('Appmax: autenticação do merchant recusada', ['status' => $response->status(), 'body' => $json]);
            throw new AppmaxException('Pagamento indisponível no momento (falha de autenticação com o gateway).');
        }

        $ttl = max(60, (int) ($json['expires_in'] ?? 3600) - 120);
        Cache::put(self::TOKEN_CACHE_KEY, $json['access_token'], $ttl);

        return $json['access_token'];
    }

    protected function client(): PendingRequest
    {
        $env  = config('appmax.environment', 'sandbox');
        $base = config("appmax.api_urls.{$env}");

        return Http::baseUrl($base)
            ->acceptJson()
            ->asJson()
            ->withToken($this->merchantToken())
            ->timeout(config('appmax.http_timeout', 30));
    }

    /**
     * POST autenticado. Se o Bearer expirou no meio do caminho (401),
     * renova uma vez e repete — padrão da doc 1.1.5 (sem refresh token).
     *
     * @throws AppmaxException
     */
    protected function post(string $endpoint, array $body, string $friendlyError): array
    {
        try {
            $response = $this->client()->post($endpoint, $body);

            if ($response->status() === 401) {
                Cache::forget(self::TOKEN_CACHE_KEY);
                $response = $this->client()->post($endpoint, $body);
            }
        } catch (AppmaxException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Appmax: falha de conexão', ['endpoint' => $endpoint, 'erro' => $e->getMessage()]);
            throw new AppmaxException('Não conseguimos falar com o processador de pagamento. Tente novamente em instantes.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json)) {
            Log::warning('Appmax: resposta com erro', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $this->safeErrorBody($endpoint, $json),
            ]);

            throw new AppmaxException($this->extractErrorMessage($json) ?? $friendlyError, is_array($json) ? $json : null);
        }

        return $json['data'] ?? $json;
    }

    /** Nunca logar corpo de resposta de cartão (pode ecoar dados sensíveis). */
    protected function safeErrorBody(string $endpoint, mixed $json): mixed
    {
        return str_contains($endpoint, 'credit-card') ? '[omitido - endpoint de cartão]' : $json;
    }

    /** Mensagem legível dos formatos de erro da v4. */
    protected function extractErrorMessage(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        // {errors:{message:{campo:["msg", ...], ...}}}
        if (isset($json['errors']['message']) && is_array($json['errors']['message'])) {
            $first = collect($json['errors']['message'])->flatten()->first();
            if (is_string($first)) {
                return 'Pagamento não concluído: '.$first;
            }
        }

        // {error:{message}} ou {message}
        $msg = $json['error']['message'] ?? $json['message'] ?? null;

        return is_string($msg) && $msg !== '' ? 'Pagamento não concluído: '.$msg : null;
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

    /** Pix: QR Code + copia-e-cola (EMV) + expiração. */
    public function payWithPix(Order $order, string $cpf): array
    {
        $data = $this->post('/v1/payments/pix', [
            'order_id'     => $order->appmax_order_id,
            'payment_data' => [
                'pix' => ['document_number' => $this->digits($cpf)],
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
     * Cartão de crédito (cobrança imediata; 400 = recusa → AppmaxException).
     *
     * @param array $card number, name, month, year(4 díg.), cvv,
     *                    document_number, installments
     */
    public function payWithCreditCard(Order $order, array $card): array
    {
        // Com tokenização Appmax JS (obrigatória p/ homologação sem PCI),
        // o front envia 'token' e os dados brutos do cartão nem chegam aqui.
        $cardData = filled($card['token'] ?? null)
            ? ['token' => $card['token']]
            : [
                'number' => $this->digits($card['number']),
                'cvv'    => (string) $card['cvv'],
            ];

        $data = $this->post('/v1/payments/credit-card', [
            'order_id'     => $order->appmax_order_id,
            'customer_id'  => $order->appmax_customer_id,
            'payment_data' => [
                'credit_card' => $cardData + [
                    // Doc 5.2: mês sem zero à esquerda, ano com 2 dígitos ("25").
                    'expiration_month'       => (string) ((int) $card['month']),
                    'expiration_year'        => str_pad((string) (((int) $card['year']) % 100), 2, '0', STR_PAD_LEFT),
                    'holder_document_number' => $this->digits($card['document_number']),
                    'holder_name'            => strtoupper($card['name']),
                    'installments'           => (int) ($card['installments'] ?? 1),
                    'soft_descriptor'        => substr((string) config('appmax.soft_descriptor', 'APTKSPIRITS'), 0, 13),
                ],
            ],
        ], 'Pagamento não autorizado pela operadora do cartão. Confira os dados ou tente outro cartão.');

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
