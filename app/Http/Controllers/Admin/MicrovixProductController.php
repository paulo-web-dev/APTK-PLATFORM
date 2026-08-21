<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MicrovixProduct;
use App\Models\Product;
use App\Services\Microvix\MicrovixMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vínculo do espelho do ERP (microvix_products) com o catálogo do site.
 * O estado que importa aqui é "quantos faltam sem vínculo" — a tela ordena
 * os desvinculados primeiro e o matcher só pré-seleciona a sugestão.
 */
class MicrovixProductController extends Controller
{
    public function index(Request $request, MicrovixMatcher $matcher): View
    {
        $filter = $request->query('filtro'); // sem-vinculo | vinculados | null
        $busca  = trim((string) $request->query('q'));

        $rows = MicrovixProduct::query()
            ->when($filter === 'sem-vinculo', fn ($q) => $q->whereNull('product_id'))
            ->when($filter === 'vinculados', fn ($q) => $q->whereNotNull('product_id'))
            ->when($busca !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nome_produto', 'like', "%{$busca}%")
                ->orWhere('codigoproduto', 'like', "{$busca}%")))
            ->with('product')
            ->orderByRaw('(product_id IS NULL) DESC') // sem vínculo primeiro
            ->orderBy('codigoproduto')
            ->paginate(30)
            ->withQueryString();

        $products = Product::orderBy('name')->get();

        // Sugestão só para linhas da página ainda sem vínculo.
        $sugestoes = [];
        foreach ($rows as $mp) {
            if ($mp->product_id === null && ($s = $matcher->sugerir($mp, $products))) {
                $sugestoes[$mp->id] = $s;
            }
        }

        return view('admin.microvix.products.index', [
            'rows'      => $rows,
            'sugestoes' => $sugestoes,
            'combos'    => $this->combos($products),
            'filter'    => $filter,
            'busca'     => $busca,
            'stats'     => [
                'total'      => MicrovixProduct::count(),
                'semVinculo' => MicrovixProduct::whereNull('product_id')->count(),
                'vinculados' => MicrovixProduct::whereNotNull('product_id')->count(),
            ],
        ]);
    }

    public function vincular(Request $request, MicrovixProduct $microvixProduct): RedirectResponse
    {
        $data = $request->validate(
            ['vinculo' => ['required', Rule::in(array_keys($this->combos(Product::all())))]],
            ['vinculo.in' => 'Escolha um produto e volume válidos.'],
        );

        [$productId, $size] = array_pad(explode('|', $data['vinculo'], 2), 2, '');
        $size = $size !== '' ? $size : null;

        // Dois SKUs do ERP no mesmo produto+volume fariam a conciliação
        // gravar duas vezes no mesmo lugar — bloqueia com aviso.
        $ocupado = MicrovixProduct::where('product_id', (int) $productId)
            ->where('size', $size)
            ->where('id', '!=', $microvixProduct->id)
            ->first();

        if ($ocupado) {
            return back()->withErrors([
                'vinculo' => "Esse produto e volume já estão vinculados ao código {$ocupado->codigoproduto} do ERP.",
            ]);
        }

        $microvixProduct->update(['product_id' => (int) $productId, 'size' => $size]);

        return back()->with('status', "Código {$microvixProduct->codigoproduto} vinculado.");
    }

    public function desvincular(MicrovixProduct $microvixProduct): RedirectResponse
    {
        $microvixProduct->update(['product_id' => null, 'size' => null]);

        return back()->with('status', "Código {$microvixProduct->codigoproduto} desvinculado.");
    }

    /**
     * Opções de vínculo: uma por produto+volume, valor "productId|size"
     * (produto sem volumes entra como "productId|"). Mesma lista alimenta
     * o select da tela e a validação do vincular().
     *
     * @return array<string, string> valor => rótulo
     */
    protected function combos($products): array
    {
        $combos = [];
        foreach ($products as $p) {
            foreach ($p->hasSizes() ? $p->sizes : [''] as $size) {
                $rotulo = $size === '' ? $p->name : "{$p->name} — {$size}";
                $combos["{$p->id}|{$size}"] = $rotulo;
            }
        }

        return $combos;
    }
}
