<?php

namespace App\Services\Microvix;

use App\Exceptions\MicrovixNegocioException;
use App\Models\MicrovixProduct;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * Fase 2 — envio de pedido ao ERP, sequência da doc 5.1:
 *
 *   B2CConsultaPedidos (já integrado?)
 *     → B2CCadastraClientes (atualizar_por_cpf=1)
 *     → B2CCadastraClientesEnderecosEntrega
 *     → B2CCadastraPedido (somente_reserva=1 na reserva, 0 na efetivação)
 *     → B2CCadastraPedidoItens
 *
 * A TRAVA (config microvix.envia_pedido, default false) vive AQUI, não no
 * job: com ela desligada os envelopes são montados e gravados em
 * microvix_logs (simulated=true), mas nenhuma chamada sai — vale também
 * pro reenvio manual do painel. Não existe portal de teste: ligado, isso
 * escreve no ERP de produção.
 *
 * Idempotência: id_pedido = orders.id (PK no ERP, doc 6.2 obs. 7) e toda
 * ação começa pelo B2CConsultaPedidos — retry pós-timeout do cadastro não
 * duplica reserva (o client segue NUNCA retentando importa por conta
 * própria; quem decide repetir é o job, e sempre re-passando por aqui).
 *
 * Falha aqui NUNCA toca pedido, estoque ou pagamento do site — só as
 * colunas microvix_* de orders.
 */
class MicrovixPedidoSender
{
    public function __construct(
        protected MicrovixClient $client,
        protected MicrovixRelogio $relogio,
    ) {
    }

    /* -----------------------------------------------------------------
     | Ações (retornam o estado final do envio: reservado | confirmado |
     | cancelado | pendente quando a trava está desligada)
     ------------------------------------------------------------------ */

    /** Pedido criado no site → reserva de estoque no ERP. */
    public function reservar(Order $order): string
    {
        return $this->cadastraPedidoCompleto($order, somenteReserva: 1);
    }

    /** Pagamento confirmado (webhook Appmax) → efetiva a reserva. */
    public function confirmar(Order $order): string
    {
        $this->validaPreRequisitos($order);

        if (! $this->enviaLigado()) {
            $this->client->simulaImporta('B2CCadastraPedido', [$this->payloadPedido($order, 0)], $order->id);

            return 'pendente';
        }

        if ($this->consultaPedidoNoErp($order) === null) {
            // A reserva nunca chegou ao ERP (falhou e o pagamento veio antes
            // do retry): cadastra o pedido inteiro já efetivado.
            return $this->cadastraPedidoCompleto($order, somenteReserva: 0, jaConsultado: true);
        }

        $this->importa($order, 'B2CCadastraPedido', [$this->payloadPedido($order, 0)]);

        return $this->marcaEnvio($order, 'confirmado');
    }

    /** Pix/boleto expirado ou cartão recusado → cancela no ERP (libera a reserva). */
    public function cancelar(Order $order): string
    {
        if (! $this->enviaLigado()) {
            $this->client->simulaImporta('B2CCancelaPedido', [$this->payloadCancela($order)], $order->id);

            return 'pendente';
        }

        if ($this->consultaPedidoNoErp($order) === null) {
            // Nada chegou ao ERP — não há o que cancelar lá.
            return $this->marcaEnvio($order, 'cancelado');
        }

        $this->importa($order, 'B2CCancelaPedido', [$this->payloadCancela($order)]);

        return $this->marcaEnvio($order, 'cancelado');
    }

    /* -----------------------------------------------------------------
     | Fluxo completo (doc 5.1)
     ------------------------------------------------------------------ */

    protected function cadastraPedidoCompleto(Order $order, int $somenteReserva, bool $jaConsultado = false): string
    {
        $this->validaPreRequisitos($order);

        if (! $this->enviaLigado()) {
            $this->client->simulaConsulta('B2CConsultaPedidos', ['id_pedido' => $order->id, 'timestamp' => 0], $order->id);
            $this->client->simulaImporta('B2CCadastraClientes', [$this->payloadCliente($order)], $order->id);
            $this->client->simulaImporta('B2CCadastraClientesEnderecosEntrega', [$this->payloadEndereco($order)], $order->id);
            $this->client->simulaImporta('B2CCadastraPedido', [$this->payloadPedido($order, $somenteReserva)], $order->id);
            $this->client->simulaImporta('B2CCadastraPedidoItens', $this->payloadItens($order), $order->id);

            return 'pendente';
        }

        if (! $jaConsultado && ($existente = $this->consultaPedidoNoErp($order)) !== null) {
            // Passo 2 do fluxograma: já integrado — não reenvia, só espelha.
            return $this->marcaEnvio($order, 'reservado', $existente);
        }

        $this->importa($order, 'B2CCadastraClientes', [$this->payloadCliente($order)]);
        $this->importa($order, 'B2CCadastraClientesEnderecosEntrega', [$this->payloadEndereco($order)]);
        $this->importa($order, 'B2CCadastraPedido', [$this->payloadPedido($order, $somenteReserva)]);
        // Todos os itens em UMA requisição (um <Registros> por item).
        $this->importa($order, 'B2CCadastraPedidoItens', $this->payloadItens($order));

        return $this->marcaEnvio($order, $somenteReserva === 1 ? 'reservado' : 'confirmado');
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

    /* -----------------------------------------------------------------
     | Pré-requisitos (erro de negócio: falha na hora, sem retry)
     ------------------------------------------------------------------ */

    protected function validaPreRequisitos(Order $order): void
    {
        $planos = config('microvix.planos_pagamento', []);

        if (! array_key_exists($order->payment_method, $planos)) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: método de pagamento '{$order->payment_method}' sem entrada em config/microvix.php (planos_pagamento)."
            );
        }

        if ($planos[$order->payment_method] === null) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: não existe plano de pagamento APPMAX para '{$order->payment_method}' no ERP "
                    .'(B2CConsultaPlanos só tem 279/280/281, cartão até 3x). Pedir o cadastro do plano à gestão '
                    .'e preencher config/microvix.php antes de reenviar.'
            );
        }

        if (blank(config('microvix.id_tabela_preco'))) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: MICROVIX_ID_TABELA_PRECO vazio. Sem tabela de preço cadastrada no portal o "
                    .'pedido não fatura (não é campo do payload — é pré-requisito de configuração do ERP). '
                    .'Pedir à gestão a criação da tabela (B2CConsultaProdutosTabelas hoje volta vazio).'
            );
        }

        if (blank(config('microvix.id_status_inicial'))) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: MICROVIX_ID_STATUS_INICIAL vazio — decisão da gestão pendente (3 AGUARDANDO PAGAMENTO ou 1 EM ABERTO)."
            );
        }

        if (blank(config('microvix.id_tipo_encomenda'))) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: MICROVIX_ID_TIPO_ENCOMENDA vazio — decisão da gestão pendente (99 Transportadora, 41106 PAC, 40010 Sedex...)."
            );
        }

        if ($this->cpf($order) === '') {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: sem CPF (ship_cpf do pedido e cpf do usuário vazios) — doc_cliente é obrigatório em B2CCadastraClientes."
            );
        }

        foreach ($order->items as $item) {
            if ($this->codigoProdutoErp($item->product_id, $item->size) === null) {
                throw new MicrovixNegocioException(
                    "Pedido #{$order->id}: item #{$item->id} ({$item->product_name} {$item->size}) sem vínculo com o espelho "
                        .'Microvix — vincular produto+volume em /admin/microvix/produtos antes de reenviar.'
                );
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

    protected function payloadPedido(Order $order, int $somenteReserva): array
    {
        $payload = [
            'id_pedido'        => $order->id, // PK no ERP → idempotência (reenvio = update, não duplica)
            'dt_pedido'        => $order->created_at->format('Y-m-d H:i:s'),
            'cod_cliente'      => $order->user_id,
            'tipo_frete'       => (int) config('microvix.id_tipo_encomenda'),
            'id_status'        => (int) config('microvix.id_status_inicial'),
            'valor_frete'      => $this->dinheiro($order->shipping_cost),
            'ativo'            => 1,
            'plano_pagamento'  => (int) config('microvix.planos_pagamento')[$order->payment_method],
            'somente_reserva'  => $somenteReserva,
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
            'codigoproduto'  => $this->codigoProdutoErp($item->product_id, $item->size),
            'quantidade'     => (int) $item->qty,
            'vl_unitario'    => $this->dinheiro($item->unit_price),
        ])->all();
    }

    protected function payloadCancela(Order $order): array
    {
        return ['id_pedido' => $order->id, 'ativo' => 0];
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
     * ⚠️ FORMATO DA RESPOSTA DO IMPORTADOR NÃO CONFIRMADO. A doc (seção 8)
     * só mostra o request e nunca fizemos um importa real — as fixtures dos
     * testes são PALPITE no padrão dos WS Linx. Por isso o parse é
     * tolerante e pessimista: procura um indicador de sucesso/falha em
     * qualquer nível da resposta; se não reconhecer o formato, trata como
     * ERRO para revisão humana (resposta crua fica em microvix_logs).
     * Falso positivo é pior que falso negativo: um "sucesso" inventado
     * marca o pedido como integrado sem ele existir no ERP.
     * CONFIRMAR este parse no primeiro envio real e ajustar.
     */
    protected function avaliaRespostaImporta(array $resposta, string $comando, Order $order): void
    {
        $sucesso = $this->procuraIndicadorDeSucesso($resposta);

        if ($sucesso === true) {
            return;
        }

        $detalhe = $this->procuraTexto($resposta, ['descricao', 'mensagem', 'message', 'motivo', 'faultstring'])
            ?? 'sem descrição na resposta';

        if ($sucesso === false) {
            throw new MicrovixNegocioException(
                "Pedido #{$order->id}: {$comando} recusado pelo ERP — {$detalhe}. Resposta completa em microvix_logs."
            );
        }

        throw new MicrovixNegocioException(
            "Pedido #{$order->id}: resposta do Importador em formato não reconhecido para {$comando} — "
                .'NÃO presumindo sucesso. Revisar a resposta crua em microvix_logs e ajustar '
                .'MicrovixPedidoSender::avaliaRespostaImporta() (formato nunca foi confirmado em envio real).'
        );
    }

    /** true/false se achar um indicador booleano de sucesso; null se o formato for desconhecido. */
    protected function procuraIndicadorDeSucesso(array $dados): ?bool
    {
        $achou = null;

        array_walk_recursive($dados, function ($valor, $chave) use (&$achou) {
            if (! in_array(mb_strtolower((string) $chave), ['sucesso', 'success', 'responsesuccess'], true)) {
                return;
            }
            $v = mb_strtolower(trim((string) $valor));
            if (in_array($v, ['false', '0'], true)) {
                $achou = false;               // qualquer falha explícita vence
            } elseif (in_array($v, ['true', '1'], true) && $achou !== false) {
                $achou = true;
            }
        });

        return $achou;
    }

    protected function procuraTexto(array $dados, array $chaves): ?string
    {
        $texto = null;

        array_walk_recursive($dados, function ($valor, $chave) use (&$texto, $chaves) {
            if ($texto === null && in_array(mb_strtolower((string) $chave), $chaves, true) && trim((string) $valor) !== '') {
                $texto = trim((string) $valor);
            }
        });

        return $texto;
    }

    /* -----------------------------------------------------------------
     | Estado local
     ------------------------------------------------------------------ */

    protected function marcaEnvio(Order $order, string $estado, ?array $registroErp = null): string
    {
        $order->fill([
            'microvix_pedido_id'    => $registroErp !== null ? (int) $registroErp['id_pedido'] : $order->id,
            'microvix_cod_cliente'  => $order->user_id,
            'microvix_status'       => $registroErp !== null && ($registroErp['id_status'] ?? '') !== ''
                ? (int) $registroErp['id_status']
                : (int) config('microvix.id_status_inicial'),
            'microvix_synced_at'    => now(),
            'microvix_reserved'     => $estado === 'reservado',
            'microvix_envio_estado' => $estado,
            'microvix_envio_erro'   => null,
        ])->save();

        return $estado;
    }

    /* -----------------------------------------------------------------
     | Auxiliares
     ------------------------------------------------------------------ */

    protected function enviaLigado(): bool
    {
        return (bool) config('microvix.envia_pedido');
    }

    protected function cpf(Order $order): string
    {
        return preg_replace('/\D+/', '', (string) ($order->ship_cpf ?: $order->user?->cpf)) ?? '';
    }

    protected function codigoProdutoErp(?int $productId, ?string $size): ?int
    {
        if ($productId === null) {
            return null;
        }

        return MicrovixProduct::where('product_id', $productId)
            ->where('size', $size)
            ->value('codigoproduto');
    }

    /** MONEY da doc: decimal com ponto, duas casas. */
    protected function dinheiro(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
