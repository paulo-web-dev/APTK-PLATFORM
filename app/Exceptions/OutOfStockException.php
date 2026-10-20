<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Estoque por volume insuficiente na hora de fechar o pedido — ou item do
 * carrinho que deixou de valer (garrafa custom recusada na revalidação).
 * O checkout devolve o cliente ao carrinho com a mensagem, que diz QUAL
 * item e QUANTO sobrou (ou o motivo) — nunca um "estoque insuficiente"
 * genérico (o cartão já está na mão).
 */
class OutOfStockException extends RuntimeException
{
}
