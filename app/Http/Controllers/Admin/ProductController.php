<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const FILTROS = ['ativos' => true, 'inativos' => false];

    public function index(Request $request): View
    {
        $filtro = $request->query('status');

        $query = Product::with(['category', 'primaryImage'])->orderBy('name');

        if (array_key_exists($filtro, self::FILTROS)) {
            $query->where('active', self::FILTROS[$filtro]);
        } else {
            $filtro = null;
        }

        return view('admin.products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'filtro'   => $filtro,
            'contagem' => [
                'todos'    => Product::count(),
                'ativos'   => Product::where('active', true)->count(),
                'inativos' => Product::where('active', false)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product'    => new Product(['active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $data['slug']     = $this->uniqueSlug($data['name']);
        $data['active']   = $request->boolean('active');
        $data['featured'] = $request->boolean('featured');

        $this->exigePrecoParaAtivar(new Product($data));

        $product = Product::create($data);

        $this->handleImage($request, $product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto criado.');
    }

    public function edit(Product $product): View
    {
        $product->load('images');

        return view('admin.products.form', [
            'product'    => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $erpManaged = $product->managedByErp();
        $data = $this->validateData($request, $erpManaged);

        // Produto gerenciado pelo ERP (fonte_da_verdade=erp + vínculo): preço
        // e estoque não passam — o form mostra os campos travados, mas o
        // guard de verdade é este, senão um POST forjado (ou um form defasado)
        // sobrescreveria o que a conciliação gravou.
        if ($erpManaged) {
            unset($data['price'], $data['size_prices'], $data['stock_qty']);
        }

        // Salvar sem mexer não pode regravar o JSON (80 virava 80.0): volume
        // e preço por volume só entram quando mudaram de fato.
        foreach (['sizes', 'size_prices'] as $campo) {
            if (array_key_exists($campo, $data) && $this->mesmoMapa($data[$campo], $product->{$campo})) {
                unset($data[$campo]);
            }
        }

        // O slug permanece estável para não quebrar URLs já existentes.
        $data['active']   = $request->boolean('active');
        $data['featured'] = $request->boolean('featured');

        $this->exigePrecoParaAtivar($product->replicate()->fill($data));

        $product->update($data);

        $this->handleImage($request, $product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto atualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->images as $img) {
            $this->apagaImagem($img);
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto removido.');
    }

    /** Remove UMA imagem da galeria; a próxima vira a principal. */
    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->apagaImagem($image);
        $this->renumera($product);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Imagem removida da galeria.');
    }

    // ------------------------------------------------------------------ //

    private function categories()
    {
        return ProductCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    private function validateData(Request $request, bool $erpManaged = false): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'category_id'       => ['nullable', 'integer', 'exists:product_categories,id'],
            // Gerenciado pelo ERP: os inputs vêm desabilitados (não são
            // enviados) e o valor é descartado no update — não podem ser
            // required aqui.
            'price'             => [$erpManaged ? 'nullable' : 'required', 'numeric', 'min:0'],
            'compare_price'     => ['nullable', 'numeric', 'min:0'],
            'sku'               => ['nullable', 'string', 'max:255'],
            'stock_qty'         => [$erpManaged ? 'nullable' : 'required', 'integer', 'min:0'],
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'base'              => ['nullable', 'string', 'max:255'],
            'abv'               => ['nullable', 'integer', 'min:0', 'max:100'],
            'sizes'             => ['nullable', 'string', 'max:255'],
            'size_prices'       => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'image'             => ['nullable', 'image', 'max:4096'], // KB ~ 4MB
        ]);

        // O arquivo é tratado à parte (handleImage), não é coluna do produto.
        unset($data['image']);

        // Volumes: texto separado por vírgula → array (coluna json).
        $data['sizes'] = $this->parseSizes($data['sizes'] ?? null);

        // Preços por volume: casam por posição com os volumes acima.
        if (! $erpManaged) {
            $data['size_prices'] = $this->parseSizePrices($data['sizes'], $data['size_prices'] ?? null);
        }

        return $data;
    }

    /** Produto sem preço não fica ativo (leva 08) — vale no criar e no editar. */
    private function exigePrecoParaAtivar(Product $candidato): void
    {
        if ($candidato->active && ! $candidato->temPreco()) {
            throw ValidationException::withMessages([
                'active' => 'Produto sem preço não pode ser ativado. Preencha o preço (ou os preços por volume) e salve de novo.',
            ]);
        }
    }

    /** Mesmo conteúdo, na mesma ordem, comparando número como número (80 == 80.0). */
    private function mesmoMapa(?array $novo, ?array $atual): bool
    {
        return $novo == $atual && array_keys($novo ?? []) === array_keys($atual ?? []);
    }

    /**
     * "65,90, 109,90, 189,90" + ['100 ml','375 ml','750 ml']
     *   → {"100 ml":65.90, "375 ml":109.90, "750 ml":189.90}
     * Aceita vírgula OU ponto como decimal; itens separados por vírgula
     * entre espaços ou ponto-e-vírgula. Campo vazio → null (o produto usa só
     * o preço base). Quantidade diferente de volumes ou preço zerado é ERRO
     * de validação — antes zerava os preços calado.
     */
    private function parseSizePrices(?array $sizes, ?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        if (! $sizes) {
            throw ValidationException::withMessages([
                'size_prices' => 'Preço por volume sem volume: preencha os volumes ou deixe os preços por volume em branco.',
            ]);
        }

        // separa por ";" ou por "," que NÃO seja decimal (vírgula entre dígitos)
        $parts = preg_split('/;|,(?!\d)/', $raw);
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        if (count($parts) !== count($sizes)) {
            throw ValidationException::withMessages([
                'size_prices' => 'São '.count($sizes).' volume(s) e '.count($parts).' preço(s) por volume — informe um preço para cada volume, na mesma ordem.',
            ]);
        }

        $out = [];
        foreach ($sizes as $i => $size) {
            $clean = preg_replace('/[^\d.,]/', '', $parts[$i]);
            if (str_contains($clean, ',')) {
                // formato BR: "1.189,90" → ponto = milhar, vírgula = decimal
                $clean = str_replace(',', '.', str_replace('.', '', $clean));
            }
            $value = (float) $clean;
            if ($value <= 0) {
                throw ValidationException::withMessages([
                    'size_prices' => "Preço do volume {$size} inválido (\"{$parts[$i]}\").",
                ]);
            }
            $out[$size] = round($value, 2);
        }

        return $out;
    }

    private function parseSizes(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));

        return $parts ?: null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produto';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Imagem nova ENTRA como principal e as outras sobem uma posição — nada
     * é apagado (antes apagava arquivo + linha de todas, inclusive as fotos
     * do pacote, que não se regeram em produção).
     */
    private function handleImage(Request $request, Product $product): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $path = $request->file('image')->store('products', 'public');

        ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
        ProductImage::where('product_id', $product->id)->increment('sort_order');

        ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'alt'        => $product->name,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->renumera($product);
    }

    /** Linha sempre; arquivo só se for upload do admin e ninguém mais usar. */
    private function apagaImagem(ProductImage $image): void
    {
        $image->delete();

        if ($image->isUploadDoAdmin() && ! ProductImage::where('path', $image->path)->exists()) {
            Storage::disk('public')->delete($image->path);
        }
    }

    /** Ordem 0..n sem buracos; a primeira é a principal. */
    private function renumera(Product $product): void
    {
        $product->images()->orderBy('id')->get() // images() já ordena por sort_order
            ->each(fn (ProductImage $img, int $i) => $img->update(['sort_order' => $i, 'is_primary' => $i === 0]));
    }
}
