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
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with(['category', 'primaryImage'])
            ->latest()
            ->paginate(20);

        return view('admin.products.index', compact('products'));
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

        $product = Product::create($data);

        $this->handleImage($request, $product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto criado.');
    }

    public function edit(Product $product): View
    {
        $product->load('primaryImage');

        return view('admin.products.form', [
            'product'    => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateData($request);

        // O slug permanece estável para não quebrar URLs já existentes.
        $data['active']   = $request->boolean('active');
        $data['featured'] = $request->boolean('featured');

        $product->update($data);

        $this->handleImage($request, $product);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto atualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->images as $img) {
            Storage::disk('public')->delete($img->path);
            $img->delete();
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto removido.');
    }

    // ------------------------------------------------------------------ //

    private function categories()
    {
        return ProductCategory::orderBy('sort_order')->orderBy('name')->get();
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'category_id'       => ['nullable', 'integer', 'exists:product_categories,id'],
            'price'             => ['required', 'numeric', 'min:0'],
            'compare_price'     => ['nullable', 'numeric', 'min:0'],
            'sku'               => ['nullable', 'string', 'max:255'],
            'stock_qty'         => ['required', 'integer', 'min:0'],
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
        $data['size_prices'] = $this->parseSizePrices($data['sizes'], $data['size_prices'] ?? null);

        return $data;
    }

    /**
     * "65,90, 109,90, 189,90" + ['100 ml','375 ml','750 ml']
     *   → {"100 ml":65.90, "375 ml":109.90, "750 ml":189.90}
     * Aceita vírgula OU ponto como decimal; itens separados por vírgula
     * entre espaços ou ponto-e-vírgula. Quantidade diferente de volumes → null
     * (o produto passa a usar só o preço base).
     */
    private function parseSizePrices(?array $sizes, ?string $raw): ?array
    {
        if (! $sizes || ! $raw) {
            return null;
        }

        // separa por ";" ou por "," que NÃO seja decimal (vírgula entre dígitos)
        $parts = preg_split('/;|,(?!\d)/', $raw);
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        if (count($parts) !== count($sizes)) {
            return null;
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
                return null;
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

    private function handleImage(Request $request, Product $product): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        // Substitui: apaga imagens antigas (arquivo + registro).
        foreach ($product->images as $old) {
            Storage::disk('public')->delete($old->path);
            $old->delete();
        }

        $path = $request->file('image')->store('products', 'public');

        ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'alt'        => $product->name,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }
}
