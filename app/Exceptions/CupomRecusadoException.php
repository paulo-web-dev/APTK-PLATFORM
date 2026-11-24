<?php

namespace App\Exceptions;

use App\Services\CupomAvaliacao;
use RuntimeException;

/**
 * Cupom da sessão recusado na revalidação do fechamento (dentro da
 * transação, com lock). Nada foi gravado: o checkout tira o cupom da
 * sessão e devolve o cliente com o motivo, para ele conferir o novo total.
 */
class CupomRecusadoException extends RuntimeException
{
    public function __construct(public readonly CupomAvaliacao $avaliacao)
    {
        parent::__construct($avaliacao->mensagem());
    }
}
