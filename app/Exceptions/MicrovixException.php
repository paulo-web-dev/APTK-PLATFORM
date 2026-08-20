<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro na comunicação ou na resposta do WS B2C do Microvix.
 * Carrega o XML de resposta (quando houver) para depuração/homologação.
 */
class MicrovixException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $responseXml = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
