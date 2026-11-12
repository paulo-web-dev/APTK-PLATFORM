<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Estoque por volume (size_stock). Volume da Linx (fonte erp + vínculo) é
 * só leitura — a sincronização é a dona; volume do site é editável, e
 * vazio = "sem controle" (vende sem limite). stock_qty não aparece mais.
 */
class StockController extends Controller
{
    private const LOW = 5;

    private const FILTROS = ['baixo', 'esgotado', 'sem_controle'];

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filtro'), self::FILTROS, true) ? $request->query('filtro') : null;

        $linhas = Product::with(['category', 'microvixProducts'])->orderBy('name')->get()
            ->flatMap(fn (Product $p) => array_map(fn ($size) => $this->linha($p, $size), $p->hasSizes() ? $p->sizes : [null]));

        $stats = [
            'volumes'      => $linhas->count(),
            'baixo'        => $linhas->where('situacao', 'baixo')->count(),
            'esgotado'     => $linhas->where('situacao', 'esgotado')->count(),
            'sem_controle' => $linhas->where('situacao', 'sem_controle')->count(),
            'unidades'     => (int) $linhas->sum(fn ($l) => max(0, (int) $l['saldo'])),
        ];

        $filtradas = $filter ? $linhas->where('situacao', $filter)->values() : $linhas->values();
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginadas = new LengthAwarePaginator(
            $filtradas->forPage($pagina, 30)->values(),
            $filtradas->count(),
            30,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.stock.index', [
            'linhas' => $paginadas,
            'stats'  => $stats,
            'low'    => self::LOW,
            'filter' => $filter,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'size'       => ['required', 'string', Rule::in($product->sizes ?? [])],
            'size_stock' => ['nullable', 'integer', 'min:0'],
        ]);
        $size = $data['size'];

        // Guard real (não só a tela): volume da Linx não se edita aqui.
        if ($product->volumeDaLinx($size)) {
            return back()->withErrors([
                'size_stock' => "O estoque de \"{$product->name}\" {$size} vem da Linx — ajuste lá; a sincronização atualiza aqui.",
            ]);
        }

        $estoque = $product->size_stock ?? [];
        if ($data['size_stock'] === null) {
            unset($estoque[$size]);
        } else {
            $estoque[$size] = (int) $data['size_stock'];
        }
        $product->update(['size_stock' => $estoque ?: null]);

        $texto = $data['size_stock'] === null ? 'sem controle' : (string) $data['size_stock'];

        return back()->with('status', "Estoque de \"{$product->name}\" {$size} atualizado para {$texto}.");
    }

    /** @return array{product: Product, size: ?string, saldo: ?int, linx: bool, codigo: ?int, situacao: string} */
    private function linha(Product $p, ?string $size): array
    {
        $saldo = $p->stockForSize($size);
        $linx = $p->volumeDaLinx($size);

        return [
            'product'  => $p,
            'size'     => $size,
            'saldo'    => $saldo,
            'linx'     => $linx,
            'codigo'   => $linx ? (int) $p->volumesDaLinx()[$size]->codigoproduto : null,
            'situacao' => match (true) {
                $saldo === null     => 'sem_controle',
                $saldo <= 0         => 'esgotado',
                $saldo <= self::LOW => 'baixo',
                default             => 'ok',
            },
        ];
    }
}
