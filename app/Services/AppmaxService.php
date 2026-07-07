<?php

namespace App\Services;

use App\Exceptions\AppmaxException;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da API Appmax v3 (Pix, cartão de crédito e boleto).
 *
 * Fluxo em 3 passos, sempre nessa ordem:
 *   1. createCustomer()  → id do cliente na Appmax
 *   2. createOrder()     → id do pedido na Appmax
 *   3. payWithPix() / payWithCreditCard() / payWithBoleto()
 *
 * Autenticação: campo "access-token" no corpo JSON de cada POST.
 * Envelope de resposta: { success: bool, text: string, data: {...} }.
 */
class AppmaxService
{
    protected function client(): PendingRequest
    {
        $env  = config('appmax.environment', 'sandbox');
        $base = config("appmax.base_urls.{$env}");

        return Http::baseUrl($base)
            ->acceptJson()
            ->asJson()
            ->timeout(config('appmax.http_timeout', 30));
    }

    public function isConfigured(): bool
    {
        return filled(config('appmax.token'));
    }

    /**
     * POST no endpoint com o access-token injetado, tratando o envelope.
     *
     * @throws AppmaxException quando success=false ou falha de rede.
     */
    protected function post(string $endpoint, array $body, string $friendlyError): array
    {
        if (! $this->isConfigured()) {
            throw new AppmaxException('Pagamento indisponível no momento (gateway não configurado). Tente novamente mais tarde.');
        }

        $body['access-token'] = config('appmax.token');

        try {
            /** @var Response $response */
            $response = $this->client()->post($endpoint, $body);
        } catch (\Throwable $e) {
            Log::error('Appmax: falha de conexão', ['endpoint' => $endpoint, 'erro' => $e->getMessage()]);
            throw new AppmaxException('Não conseguimos falar com o processador de pagamento. Tente novamente em instantes.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ! ($json['success'] ?? false)) {
            Log::warning('Appmax: resposta com erro', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'texto'    => $json['text'] ?? null,
                // Nunca logar $body aqui: contém dados de cartão em payment/credit-card.
            ]);

            throw new AppmaxException($friendlyError, is_array($json) ? $json : null);
        }

        return $json['data'] ?? [];
    }

    /* -----------------------------------------------------------------
     | 1) Cliente
     ------------------------------------------------------------------ */

    /**
     * Cria o cliente na Appmax a partir dos dados do checkout.
     *
     * @param  array $data  name, email, phone(11 díg.), zipcode, street,
     *                      number, complement, neighborhood, city, state
     * @return int id do cliente na Appmax
     */
    public function createCustomer(array $data, string $ip): int
    {
        [$firstName, $lastName] = $this->splitName($data['name']);

        $payload = [
            'firstname'                 => $firstName,
            'lastname'                  => $lastName ?: $firstName,
            'email'                     => $data['email'],
            'telephone'                 => substr($this->digits($data['phone'] ?? ''), 0, 11),
            'postcode'                  => substr($this->digits($data['zipcode']), 0, 8),
            'address_street'            => $data['street'],
            'address_street_number'     => (string) $data['number'],
            'address_street_complement' => $data['complement'] ?? '',
            'address_street_district'   => $data['neighborhood'],
            'address_city'              => $data['city'],
            'address_state'             => strtoupper($data['state']),
            'ip'                        => $ip,
        ];

        $customer = $this->post('customer', $payload, 'Não foi possível registrar seus dados no pagamento. Confira as informações e tente de novo.');

        if (empty($customer['id'])) {
            throw new AppmaxException('Resposta inesperada do processador de pagamento (cliente sem id).', $customer);
        }

        return (int) $customer['id'];
    }

    /* -----------------------------------------------------------------
     | 2) Pedido
     ------------------------------------------------------------------ */

    /**
     * Cria o pedido na Appmax espelhando o pedido local (itens + totais).
     *
     * @return int id do pedido na Appmax
     */
    public function createOrder(Order $order, int $appmaxCustomerId): int
    {
        $products = $order->items->map(function ($item) {
            $sku = $item->product_sku ?: ('APTK-'.$item->product_id);
            if ($item->size) {
                // SKU distinto por volume (a Appmax deduplica por SKU).
                $sku .= '-'.$this->digits($item->size);
            }

            return [
                'sku'   => substr($sku, 0, 100),
                'name'  => substr($item->product_name.($item->size ? ' '.$item->size : ''), 0, 255),
                'qty'   => (int) $item->qty,
                'price' => (float) $item->unit_price,
            ];
        })->values()->all();

        $payload = [
            'customer_id' => $appmaxCustomerId,
            'total'       => (float) $order->total,
            'shipping'    => (float) $order->shipping_cost,
            'discount'    => (float) $order->discount,
            'products'    => $products,
        ];

        $data = $this->post('order', $payload, 'Não foi possível registrar o pedido no pagamento. Tente novamente.');

        if (empty($data['id'])) {
            throw new AppmaxException('Resposta inesperada do processador de pagamento (pedido sem id).', $data);
        }

        return (int) $data['id'];
    }

    /* -----------------------------------------------------------------
     | 3) Pagamento
     ------------------------------------------------------------------ */

    /**
     * Pix: gera a chave e devolve QR code (base64), copia-e-cola e expiração.
     *
     * @return array{qrcode:?string, emv:?string, expiration:?string, pay_reference:?string}
     */
    public function payWithPix(Order $order, string $cpf): array
    {
        $payload = [
            'cart'     => ['order_id' => $order->appmax_order_id],
            'customer' => ['customer_id' => $order->appmax_customer_id],
            'payment'  => [
                'pix' => [
                    'document_number' => $this->digits($cpf),
                    'expiration_date' => now()
                        ->addMinutes(config('appmax.pix_expiration_minutes', 60))
                        ->format('Y-m-d H:i:s'),
                ],
            ],
        ];

        $data = $this->post('payment/pix', $payload, 'Não foi possível gerar a chave Pix. Tente novamente.');

        return [
            'qrcode'        => $data['pix_qrcode'] ?? null,
            'emv'           => $data['pix_emv'] ?? null,
            'expiration'    => $data['pix_expiration_date'] ?? null,
            'pay_reference' => $data['pay_reference'] ?? null,
        ];
    }

    /**
     * Cartão de crédito: cobrança imediata. Lança AppmaxException se recusado.
     *
     * @param  array $card  number, name, month, year, cvv, document_number, installments
     * @return array{pay_reference:?string, upsell_hash:?string}
     */
    public function payWithCreditCard(Order $order, array $card): array
    {
        $payload = [
            'cart'     => ['order_id' => $order->appmax_order_id],
            'customer' => ['customer_id' => $order->appmax_customer_id],
            'payment'  => [
                'CreditCard' => [
                    'number'          => $this->digits($card['number']),
                    'cvv'             => (string) $card['cvv'],
                    'month'           => (int) $card['month'],
                    'year'            => (int) $card['year'],
                    'name'            => strtoupper($card['name']),
                    'document_number' => $this->digits($card['document_number']),
                    'installments'    => (int) ($card['installments'] ?? 1),
                    'soft_descriptor' => substr((string) config('appmax.soft_descriptor', 'APTKSPIRITS'), 0, 13),
                ],
            ],
        ];

        $data = $this->post('payment/credit-card', $payload, 'Pagamento não autorizado pela operadora do cartão. Confira os dados ou tente outro cartão.');

        return [
            'pay_reference' => $data['pay_reference'] ?? null,
            'upsell_hash'   => $data['upsell_hash'] ?? null,
        ];
    }

    /**
     * Boleto: gera linha digitável, PDF e vencimento.
     *
     * @return array{digitable_line:?string, pdf:?string, due_date:?string, pay_reference:?string}
     */
    public function payWithBoleto(Order $order, string $cpf): array
    {
        $payload = [
            'cart'     => ['order_id' => $order->appmax_order_id],
            'customer' => ['customer_id' => $order->appmax_customer_id],
            'payment'  => [
                'Boleto' => [
                    'document_number' => $this->digits($cpf),
                ],
            ],
        ];

        $data = $this->post('payment/boleto', $payload, 'Não foi possível gerar o boleto. Tente novamente.');

        return [
            'digitable_line' => $data['digitable_line'] ?? ($data['boleto_payment_code'] ?? null),
            'pdf'            => $data['pdf'] ?? null,
            'due_date'       => $data['due_date'] ?? null,
            'pay_reference'  => $data['pay_reference'] ?? null,
        ];
    }

    /* -----------------------------------------------------------------
     | Estorno
     ------------------------------------------------------------------ */

    /** Estorno total do pedido na Appmax. */
    public function refund(Order $order): bool
    {
        $this->post('refund', [
            'order_id'    => $order->appmax_order_id,
            'refund_type' => 'total',
        ], 'Não foi possível processar o estorno na Appmax.');

        return true;
    }

    /* -----------------------------------------------------------------
     | Helpers
     ------------------------------------------------------------------ */

    protected function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /** "Paulo Orfanelli Silva" → ["Paulo", "Orfanelli Silva"] */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [trim($name)];

        return [substr($parts[0], 0, 100), substr($parts[1] ?? '', 0, 100)];
    }
}
