<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro na comunicação ou na resposta da Appmax.
 * A mensagem é segura para exibir ao cliente (sem dados técnicos).
 */
class AppmaxException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?array $apiResponse = null,
    ) {
        parent::__construct($message);
    }
}
