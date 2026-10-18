<?php

namespace App\Exceptions;

use Exception;

/**
 * Erro na comunicação ou na resposta da Appmax.
 *
 * A mensagem é SEMPRE amigável, em português e segura para o cliente.
 * O texto cru da API fica em $detalhe (log e admin, nunca na tela da loja).
 *
 * $tipo separa o que é recusa clara do que é incerteza — no cartão, só
 * recusa clara pode cancelar o pedido local; incerteza pode ter cobrado.
 */
class AppmaxException extends Exception
{
    /** Cartão recusado pela operadora/antifraude. */
    public const RECUSADO = 'recusado';

    /** Dados rejeitados na validação (422). */
    public const DADOS_INVALIDOS = 'dados_invalidos';

    /** Falha que garantidamente não processou nada (credenciais, auth, 401, 429). */
    public const INDISPONIVEL = 'indisponivel';

    /** Timeout, erro de rede ou 5xx: a Appmax PODE ter processado. */
    public const INDEFINIDO = 'indefinido';

    public function __construct(
        string $message,
        public readonly ?array $apiResponse = null,
        public readonly string $tipo = self::INDISPONIVEL,
        public readonly ?string $detalhe = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public function isIndefinido(): bool
    {
        return $this->tipo === self::INDEFINIDO;
    }
}
