<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Estoque por volume insuficiente na hora de fechar o pedido.
 * A mensagem é exibida ao cliente: diz QUAL item e QUANTO sobrou —
 * nunca um "estoque insuficiente" genérico (o cartão já está na mão).
 */
class OutOfStockException extends RuntimeException
{
}
