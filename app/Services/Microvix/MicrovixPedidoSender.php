<?php

namespace App\Services\Microvix;

use App\Exceptions\MicrovixException;
use App\Exceptions\MicrovixNegocioException;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * Fase 2 — envio de pedido PAGO à Linx, sequência da doc 5.1, direto com
 * somente_reserva=0 (sem reserva no checkout):
 *
 *   B2CConsultaPedidos (já integrado?)
 *     → B2CCadastraClientes (atualizar_por_cpf=1)
 *     → B2CCadastraClientesEnderecosEntrega
 *     → B2CCadastraPedido (somente_reserva=0)
 *     → B2CCadastraPedidoItens
 *
 * As regras de elegibilidade (pago, data de corte, manual) vivem no
 * MicrovixEnvio; aqui é a execução. A TRAVA (config microvix.envia_pedido)
 * também vive aqui: desligada, os envelopes vão para microvix_logs
 * (simulated=true) e nada sai — vale também pro reenvio do painel.
 *
 * Idempotência: pedido com microvix_pedido_id nunca é reenviado. id_pedido
 * = orders.id e id_pedido_item = order_items.id são chave primária na Linx;
 * se o pedido já existe lá sem marca local (timeout depois de chegar), o
 * cadastro do pedido e dos itens é reenviado (atualiza, não duplica) e só
 * então o pedido é marcado.
 *
 * Falha aqui NUNCA toca pedido, estoque ou pagamento do site — só as
 * colunas microvix_* de orders.
 */
class MicrovixPedidoSender
{
    public function __construct(
        protected MicrovixClient $client,
        protected MicrovixRelogio $relogio,
        protected MicrovixEnvio $regras,
    ) {
    }

    /**
     * Envia o pedido. Retorna: enviado | manual | pendente (trava desligada,
     * só simulou) | nao_aplicavel (fora do período, não pago, cancelado ou
     * lançado à mão — nada é tocado).
     */
    public function enviar(Order $order): string
    {
        $order->loadMissing(['items', 'user']);

        if ($order->microvix_pedido_id !== null) {
            return 'enviado';
        }
        if (! MicrovixEnvio::noPeriodo($order) || ! $order->isPaid() || $order->status === 'cancelled'
            || $order->microvix_envio_estado === 'lancado_manual') {
            return 'nao_aplicavel';
        }

        $this->validaConfiguracao($order);

        if (($motivo = $this->regras->motivoManual($order)) !== null) {
            $order->forceFill([
                'microvix_envio_estado' => 'manual',
                'microvix_envio_erro'   => mb_substr("Lançar manualmente na Linx: {$motivo}", 0, 2000),
                'microvix_envio_em'     => now(),
            ])->save();

            return 'manual';
        }

        if (! $this->enviaLigado()) {
            $this->client->simulaConsulta('B2CConsultaPedidos', ['id_pedido' => $order->id, 'timestamp' => 0], $order->id);
            $this->client->simulaImporta('B2CCadastraClientes', [$this->payloadCliente($order)], $order->id);
            $this->client->simulaImporta('B2CCadastraClientesEnderecosEntrega', [$this->payloadEndereco($order)], $order->id);
            $this->client->simulaImporta('B2CCadastraPedido', [$this->payloadPedido($order)], $order->id);
            $this->client->simulaImporta('B2CCadastraPedidoItens', $this->payloadItens($order), $order->id);

            return 'pendente';
        }

        $existente = $this->consultaPedidoNoErp($order);

        if ($existente === null) {
            $this->importa($order, 'B2CCadastraClientes', [$this->payloadCliente($order)]);
            $this->importa($order, 'B2CCadastraClientesEnderecosEntrega', [$this->payloadEndereco($order)]);
        }
        // Já existe (tentativa anterior chegou e a resposta se perdeu): os
        // itens podem ter ficado de fora — reenvia pedido + itens, que são
        // chave primária na Linx (atualiza, não duplica).
        $this->importa($order, 'B2CCadastraPedido', [$this->payloadPedido($order)]);
        // Todos os itens em UMA requisição (um <Registros> por item).
        $this->importa($order, 'B2CCadastraPedidoItens', $this->payloadItens($order));

        return $this->marcaEnviado($order, $existente);
    }

    /** @return array<string, string>|null registro do pedido no ERP, ou null se não integrado */
    protected function consultaPedidoNoErp(Order $order): ?array
    {
        $rows = $this->client->consulta(
            'B2CConsultaPedidos',
            ['id_pedido' => $order->id, 'timestamp' => 0],
            orderId: $order->id,
        );

        return ($rows === null || $rows === []) ? null : $rows[0];
    }

    /**
     * Configuração do portal faltando é erro (não manual): vale pra todo
     * pedido e se resolve no .env, não no pedido.
     */
    protected function validaConfiguracao(Order $order): void
    {
        foreach ([
            'id_tabela_preco'   => 'MICROVIX_ID_TABELA_PRECO',
            'id_status_inicial' => 'MICROVIX_ID_STATUS_INICIAL',
            'id_tipo_encomenda' => 'MICROVIX_ID_TIPO_ENCOMENDA',
        ] as $chave => $env) {
            if (blank(config("microvix.{$chave}"))) {
                throw new MicrovixNegocioException("Pedido #{$order->id}: {$env} vazio no .env — preencher antes de reenviar.");
            }
        }
    }

    /* -----------------------------------------------------------------
     | Payloads (nomes de campo da doc 6.2 — conferidos contra o PDF)
     ------------------------------------------------------------------ */

    protected function payloadCliente(Order $order): array
    {
        $user = $order->user;

        return [
            'cod_cliente'             => $order->user_id,
            'doc_cliente'             => $this->cpf($order),
            'ativo'                   => 1,
            'receber_email'           => 0,
            'nm_cliente'              => mb_substr($order->ship_name ?: $user->name, 0, 50),
            // Endereço básico no cadastro do cliente: sem ele o faturamento
            // falha ("detalhamento de configuração tributária não encontrado",
            // doc 6.2 obs. 1 de B2CCadastraClientes).
            'end_cliente'             => mb_substr((string) $order->ship_street, 0, 250),
            'nr_rua_cliente'          => mb_substr((string) $order->ship_number, 0, 20),
            'complemento_end_cliente' => $order->ship_complement ? mb_substr($order->ship_complement, 0, 50) : null,
            'bairro_cliente'          => mb_substr((string) $order->ship_neighborhood, 0, 60),
            'cep_cliente'             => mb_substr((string) $order->ship_zipcode, 0, 9),
            'cidade_cliente'          => mb_substr((string) $order->ship_city, 0, 40),
            'uf_cliente'              => mb_substr((string) $order->ship_state, 0, 2),
            'fone_cliente'            => mb_substr((string) ($order->ship_phone ?: $user->phone), 0, 20),
            'email_cliente'           => mb_substr((string) $user->email, 0, 50),
            'atualizar_por_cpf'       => 1, // doc 6.2 obs. 3: não duplica cliente
        ];
    }

    protected function payloadEndereco(Order $order): array
    {
        return [
            'id_endereco_entrega' => $order->id, // namespace global do portal (PDF pg. 39/65): orders.id nunca colide
            'cod_cliente'         => $order->user_id,
            'endereco_cliente'    => mb_substr((string) $order->ship_street, 0, 250),
            'principal'           => 1,
            'numero_rua_cliente'  => mb_substr((string) $order->ship_number, 0, 20),
            'complemento_end_cli' => $order->ship_complement ? mb_substr($order->ship_complement, 0, 60) : null,
            'cep_cliente'         => mb_substr((string) $order->ship_zipcode, 0, 9),
            'bairro_cliente'      => mb_substr((string) $order->ship_neighborhood, 0, 60),
            'cidade_cliente'      => mb_substr((string) $order->ship_city, 0, 40),
            'uf_cliente'          => mb_substr((string) $order->ship_state, 0, 2),
            // Origem visível pro atendimento: o cliente acumula um endereço
            // por pedido no ERP e alguém precisa saber de onde cada um veio.
            'descricao'           => "Pedido #{$order->id}",
        ];
    }

    protected function payloadPedido(Order $order): array
    {
        $payload = [
            'id_pedido'        => $order->id, // PK no ERP → idempotência (reenvio = update, não duplica)
            'dt_pedido'        => $order->created_at->format('Y-m-d H:i:s'),
            'cod_cliente'      => $order->user_id,
            'tipo_frete'       => (int) config('microvix.id_tipo_encomenda'),
            'id_status'        => (int) config('microvix.id_status_inicial'),
            'valor_frete'      => $this->dinheiro($order->shipping_cost),
            'ativo'            => 1,
            'plano_pagamento'  => $this->regras->planoPagamento($order),
            'somente_reserva'  => 0, // só pedido pago vai, já efetivado
            'desconto'         => $this->dinheiro($order->discount),
            'id_endereco_entrega' => $order->id,
            'ecommerce_origem' => mb_substr((string) config('app.url'), 0, 200),
        ];

        // Parâmetros de faturamento opcionais: só entram se descobertos
        // (microvix:descobrir) e preenchidos no .env.
        foreach ([
            'empresa'                 => 'empresa',
            'cod_vendedor'            => 'cod_vendedor',
            'id_deposito_faturamento' => 'id_deposito',
        ] as $campo => $chaveConfig) {
            if (filled(config("microvix.{$chaveConfig}"))) {
                $payload[$campo] = (int) config("microvix.{$chaveConfig}");
            }
        }

        return $payload;
    }

    /** @return array<int, array<string, mixed>> um registro por item do pedido */
    protected function payloadItens(Order $order): array
    {
        return $order->items->map(fn ($item) => [
            'id_pedido_item' => $item->id,
            'id_pedido'      => $order->id,
            'codigoproduto'  => $this->regras->codigoProduto($item->product_id, $item->size),
            'quantidade'     => (int) $item->qty,
            'vl_unitario'    => $this->dinheiro($item->unit_price),
        ])->all();
    }

    /* -----------------------------------------------------------------
     | Envio serializado + avaliação da resposta
     ------------------------------------------------------------------ */

    protected function importa(Order $order, string $comando, array $registros): void
    {
        $this->respeitaJanelaDoMetodo($comando);
        $resposta = $this->client->importa($comando, $registros, $order->id);
        $this->avaliaRespostaImporta($resposta, $comando, $order);
    }

    /**
     * Obs. 5 de B2CCadastraPedido: nunca duas requisições do MESMO método no
     * mesmo segundo (métodos diferentes no mesmo segundo são permitidos).
     * Guarda em cache o segundo do último envio por método; se o segundo
     * corrente já foi usado, dorme até o próximo. Devolve o segundo usado.
     */
    protected function respeitaJanelaDoMetodo(string $metodo): int
    {
        $chave = "microvix:ultimo_segundo_envio:{$metodo}";

        while (($ultimo = Cache::get($chave)) !== null && (int) $ultimo >= $this->relogio->agora()) {
            $this->relogio->esperaProximoSegundo();
        }

        $segundo = $this->relogio->agora();
        Cache::put($chave, $segundo, 120);

        return $segundo;
    }

    /**
     * Formato REAL do Importador.svc (confirmado no 1º envio, pedido #58 —
     * fixture tests/Fixtures/microvix/importa-sucesso.xml). Lido por
     * local-name (o client já tira os prefixos de namespace):
     *
     *   ImportarResult
     *     Mensagem         (lista de string; vazia no sucesso)
     *     Succeeded        true|false
     *     importacaoLOG    (um por registro?) ResultadoChamada, MensagemErro, ...
     *
     * Sucesso = Succeeded true E todo ResultadoChamada true E nenhuma
     * MensagemErro/Mensagem. Qualquer falha explícita = erro de negócio (ou
     * temporário, se o texto for de pool/timeout). Formato sem Succeeded e
     * sem ResultadoChamada continua NÃO presumindo sucesso.
     */
    protected function avaliaRespostaImporta(array $resposta, string $comando, Order $order): void
    {
        $succeeded = $this->valores($resposta, 'Succeeded');
        $resultados = $this->valores($resposta, 'ResultadoChamada');

        if ($succeeded === [] || $resultados === []) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: resposta do Importador em formato não reconhecido para {$comando} — "
                    .'NÃO presumindo sucesso. Revisar a resposta crua em microvix_logs e ajustar '
                    .'MicrovixPedidoSender::avaliaRespostaImporta().'
            );
        }

        $mensagens = array_values(array_unique(array_merge(
            $this->valores($resposta, 'MensagemErro'),
            $this->valores($resposta, 'Mensagem'),
        )));
        $verdadeiro = fn (string $v) => mb_strtolower($v) === 'true';

        $sucesso = array_filter($succeeded, $verdadeiro) === $succeeded
            && array_filter($resultados, $verdadeiro) === $resultados
            && $mensagens === [];

        if ($sucesso) {
            return;
        }

        $detalhe = $mensagens === []
            ? 'Succeeded='.implode(',', $succeeded).', ResultadoChamada='.implode(',', $resultados).' sem mensagem'
            : implode('; ', $mensagens);

        if ($this->pareceTemporario($detalhe)) {
            // Pool/timeout do lado da Linx: retry com backoff (o job recomeça
            // pelo B2CConsultaPedidos, sem duplicar).
            throw new MicrovixException("Pedido #{$order->id}: {$comando} — erro temporário da Linx: {$detalhe}.");
        }

        throw new MicrovixNegocioException(
            "Pedido #{$order->id}: {$comando} recusado pela Linx — {$detalhe}. Resposta completa em microvix_logs."
        );
    }

    /**
     * Todos os textos não vazios sob as chaves de nome $chave (qualquer
     * profundidade; elemento vazio vem do SimpleXML como []). Lista de
     * <string> (Mensagem) é achatada.
     *
     * @return list<string>
     */
    protected function valores(array $dados, string $chave): array
    {
        $achados = [];

        foreach ($dados as $k => $v) {
            if ((string) $k === $chave) {
                if (is_array($v)) {
                    array_walk_recursive($v, function ($folha) use (&$achados) {
                        if (is_scalar($folha) && trim((string) $folha) !== '') {
                            $achados[] = trim((string) $folha);
                        }
                    });
                } elseif (is_scalar($v) && trim((string) $v) !== '') {
                    $achados[] = trim((string) $v);
                }
            } elseif (is_array($v)) {
                array_push($achados, ...$this->valores($v, $chave));
            }
        }

        return $achados;
    }

    /**
     * Texto de erro com cara de indisponibilidade momentânea. PALPITE: o
     * formato do Importador nunca foi confirmado em envio real.
     */
    protected function pareceTemporario(string $detalhe): bool
    {
        return (bool) preg_match('/\bpool\b|time-?out|tempo (limite|esgotado)|tente novamente|indispon[ií]vel|too many|sobrecarg/iu', $detalhe);
    }

    protected function marcaEnviado(Order $order, ?array $registroErp = null): string
    {
        $order->forceFill([
            'microvix_pedido_id'    => $registroErp !== null && filled($registroErp['id_pedido'] ?? null) ? (int) $registroErp['id_pedido'] : $order->id,
            'microvix_cod_cliente'  => $order->user_id,
            'microvix_status'       => $registroErp !== null && ($registroErp['id_status'] ?? '') !== ''
                ? (int) $registroErp['id_status']
                : (int) config('microvix.id_status_inicial'),
            'microvix_synced_at'    => now(),
            'microvix_reserved'     => false,
            'microvix_envio_estado' => 'enviado',
            'microvix_envio_erro'   => null,
        ])->save();

        return 'enviado';
    }

    protected function enviaLigado(): bool
    {
        return (bool) config('microvix.envia_pedido');
    }

    /** Só o snapshot do pedido (regra 4): sem CPF no pedido o envio é manual. */
    protected function cpf(Order $order): string
    {
        return preg_replace('/\D+/', '', (string) $order->ship_cpf) ?? '';
    }

    /** MONEY da doc: decimal com ponto, duas casas. */
    protected function dinheiro(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
