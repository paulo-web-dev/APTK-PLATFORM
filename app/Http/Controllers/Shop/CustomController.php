<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use App\Services\CustomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Custom — página + configurador do Custom Simples.
 *
 * Fluxos:
 *  - Custom Simples (leva 11): compra direta. O configurador escolhe bebida,
 *    texto e fonte (config/custom.php; a cor é fixa, preto) e põe a garrafa 375 ml no
 *    carrinho a preço fixo. Não cria mais lead.
 *  - Full Custom: contato direto via WhatsApp da APTK (whatsapp_link na view).
 */
class CustomController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CustomService $custom,
    ) {
    }

    public function show(): View
    {
        // Só bebida com produto no catálogo (ativo ou não), na ordem da config,
        // com o nome do Custom (config `nome`; o da loja fica de reserva).
        $produtos = Product::whereIn('slug', array_keys($this->custom->bebidas()))->pluck('name', 'slug');
        $bebidas = collect($this->custom->bebidas())
            ->filter(fn ($b, $slug) => $produtos->has($slug))
            ->map(fn ($b, $slug) => ['nome' => $this->custom->nomeBebida($slug) ?? $produtos[$slug]] + $this->custom->exibir(['bebida' => $slug]));

        return view('shop.custom', [
            'bebidas'  => $bebidas,
            'fontes'   => $this->custom->fontes(),
            'cor'      => $this->custom->cor(),
            'preco'    => $this->custom->preco(),
            'volume'   => $this->custom->volume(),
            'limite'   => $this->custom->limite(),
            'aviso'    => config('custom.aviso'),
        ]);
    }

    /** Adicionar ao carrinho: valida a personalização e confere o estoque do 375. */
    public function adicionar(Request $request): RedirectResponse
    {
        // A cor nunca vem do formulário: o validar põe a fixa.
        $p = $this->custom->validar($request->only(['bebida', 'texto', 'fonte']));
        $qty = (int) ($request->validate(['qty' => ['nullable', 'integer', 'min:1', 'max:50']])['qty'] ?? 1);

        $produto = $this->custom->produto($p['bebida']);
        $volume = $this->custom->volume();

        // Consome o estoque da 375 da bebida (soma com as linhas que já estão
        // no carrinho). Volume não rastreado (saldo null) não trava.
        $saldo = $produto->stockForSize($volume);
        if ($saldo !== null) {
            $rotulo = '"'.($this->custom->nomeBebida($p['bebida']) ?? $produto->name)." ({$volume})\"";
            $noCarrinho = $this->cart->qtyDoVolume($produto->id, $volume);

            if ($saldo <= 0) {
                return back()->withInput()->with('stock_error', "{$rotulo} está esgotado.");
            }

            if ($noCarrinho + $qty > $saldo) {
                return back()->withInput()->with(
                    'stock_error',
                    "Só temos {$saldo} unidade(s) de {$rotulo}".($noCarrinho > 0 ? " e você já tem {$noCarrinho} no carrinho" : '').'.',
                );
            }
        }

        $this->cart->add($produto->id, $qty, $volume, $p);

        return redirect()
            ->route('cart.index')
            ->with('status', 'Garrafa personalizada adicionada ao carrinho.');
    }
}
