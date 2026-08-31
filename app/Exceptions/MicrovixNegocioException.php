<?php

namespace App\Exceptions;

/**
 * Erro de NEGÓCIO no envio ao WS B2C: pré-requisito faltando (plano de
 * pagamento não cadastrado, item sem vínculo, config vazia) ou recusa
 * explícita do ERP. Retry não resolve — o job falha na primeira e espera
 * intervenção humana; o reenvio manual do painel fica disponível depois
 * que a causa for corrigida.
 */
class MicrovixNegocioException extends MicrovixException
{
}
