<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\CatalogoImportarPlanilha;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Services\FotoUpload;
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

        $query = Product::with(['category', 'primaryImage', 'microvixProducts'])->orderBy('name');

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

        $this->recusaVolumeDesativado($data['sizes'] ?? null, []);

        $data['slug']     = $this->uniqueSlug($data['name']);
        $data['active']   = $request->boolean('active');
        $data['featured'] = $request->boolean('featured');
        // stock_qty saiu do admin (estoque é por volume, size_stock); o
        // agregado interno nasce como no importador, senão a vitrine mostraria
        // o produto novo como esgotado.
        $data['stock_qty'] = CatalogoImportarPlanilha::ESTOQUE_PLACEHOLDER;
        $data['size_stock'] = $this->estoqueDoSite($request, $data['sizes'] ?? null, null) ?: null;
        unset($data['size_stock_form']);

        $this->exigePodeAtivar($request, new Product($data), []);
        $nova = $this->volumesDasFotos($request, new Product($data), collect())[1];

        $product = Product::create($data);

        $this->handleImage($request, $product, $nova);

        return redirect()
            ->route('admin.products.index')
            ->with('status', 'Produto criado.');
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'microvixProducts']);

        return view('admin.products.form', [
            'product'    => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        // Volumes da Linx (fonte erp + vínculo): preço e estoque são da
        // Linx. O form só manda os volumes do SITE nos campos de texto; aqui
        // os volumes da Linx são remontados do que está gravado. É o guard
        // de verdade: um POST forjado (ou um form defasado) nunca altera
        // preço/estoque de volume da Linx nem remove o volume.
        $linx = $product->volumesDaLinx();
        $data = $this->validateData($request, $linx !== []);

        $this->recusaVolumeDesativado($data['sizes'] ?? null, $product->sizes ?? []);

        if ($linx !== []) {
            if ($repetidos = array_values(array_intersect($data['sizes'] ?? [], array_keys($linx)))) {
                throw ValidationException::withMessages([
                    'sizes' => implode(', ', $repetidos).' vem da Linx: fica fora dos volumes do site.',
                ]);
            }
            $data = $this->comVolumesDaLinx($data, $product, array_keys($linx));
            unset($data['price']); // preço base é só reserva quando há volume da Linx
        }

        $data['size_stock'] = $this->estoqueDoSite($request, $data['sizes'] ?? null, $product, array_keys($linx));
        $data['size_stock'] = $data['size_stock'] === [] ? null : $data['size_stock'];
        if ($this->mesmoMapa($data['size_stock'], $product->size_stock)) {
            unset($data['size_stock']);
        }
        unset($data['size_stock_form']);

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

        $this->exigePodeAtivar($request, $product->replicate()->fill($data), $product->images()->pluck('path')->all());
        [$volumes, $nova] = $this->volumesDasFotos($request, $product->replicate()->fill($data), $product->images);

        $product->update($data);

        foreach ($product->images as $img) {
            if ($img->volume !== $volumes[$img->id]) {
                $img->update(['volume' => $volumes[$img->id]]);
            }
        }
        $this->handleImage($request, $product, $nova);
        $product->renumerarGaleria();

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
        $product->renumerarGaleria();

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
            // Estoque por volume do SITE (volume da Linx não passa; vazio =
            // sem controle). stock_qty saiu do admin.
            'size_stock'        => ['nullable', 'array'],
            'size_stock.*'      => ['nullable', 'integer', 'min:0'],
            'size_stock_form'   => ['nullable'],
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'base'              => ['nullable', 'string', 'max:255'],
            'abv'               => ['nullable', 'integer', 'min:0', 'max:100'],
            'sizes'             => ['nullable', 'string', 'max:255'],
            'size_prices'       => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'image'             => FotoUpload::regras(), // até 30 MB; gravada reduzida (FotoUpload)
            'image_volume'      => ['nullable', 'array'],
            'image_volume.*'    => ['nullable', 'string', 'max:30'],
            'image_volume_nova' => ['nullable', 'string', 'max:30'],
        ]);

        // Arquivo e garrafa das fotos são tratados à parte (handleImage,
        // volumesDasFotos), não são colunas do produto.
        unset($data['image'], $data['image_volume'], $data['image_volume_nova']);

        // Volumes: texto separado por vírgula → array (coluna json).
        $data['sizes'] = $this->parseSizes($data['sizes'] ?? null);

        // Preços por volume: casam por posição com os volumes acima (com
        // volumes da Linx, só os do site estão nos dois campos).
        $data['size_prices'] = $this->parseSizePrices($data['sizes'], $data['size_prices'] ?? null);
        unset($data['size_stock']); // tratado à parte (estoqueDoSite)

        return $data;
    }

    /**
     * Junta os volumes da Linx (gravados) aos do site (do form): volumes em
     * ordem de ml; preço da Linx mantido como está gravado.
     */
    private function comVolumesDaLinx(array $data, Product $product, array $volumesLinx): array
    {
        $sizes = array_values(array_unique([...$volumesLinx, ...($data['sizes'] ?? [])]));
        usort($sizes, fn ($a, $b) => (int) $a <=> (int) $b);

        $precos = [];
        foreach ($sizes as $size) {
            if (in_array($size, $volumesLinx, true)) {
                if (isset($product->size_prices[$size])) {
                    $precos[$size] = $product->size_prices[$size];
                }
            } elseif (isset($data['size_prices'][$size])) {
                $precos[$size] = $data['size_prices'][$size];
            }
        }

        $data['sizes'] = $sizes;
        $data['size_prices'] = $precos ?: null;

        return $data;
    }

    /**
     * size_stock final: volumes da Linx ficam com o que está gravado (a
     * conciliação é a dona); volumes do site levam o valor do form (vazio =
     * sem controle, sai do mapa). Form que não traz a grade de estoque
     * (size_stock_form ausente) mantém o estoque gravado dos volumes do site.
     */
    private function estoqueDoSite(Request $request, ?array $sizes, ?Product $product, array $volumesLinx = []): array
    {
        $atual = $product?->size_stock ?? [];
        $doForm = $request->input('size_stock', []);
        $temGrade = $request->has('size_stock_form');
        $estoque = [];

        foreach ($sizes ?? [] as $size) {
            if (in_array($size, $volumesLinx, true)) {
                if (array_key_exists($size, $atual)) {
                    $estoque[$size] = $atual[$size];
                }

                continue;
            }

            $valor = $temGrade ? ($doForm[$size] ?? null) : ($atual[$size] ?? null);
            if ($valor !== null && $valor !== '') {
                $estoque[$size] = (int) $valor;
            }
        }

        return $estoque;
    }

    /**
     * Ativo só com preço e foto no padrão (Product::podeAtivar) — vale no
     * criar e no editar. A foto enviada neste mesmo salvar já conta: o
     * hashName() é o nome que o store() vai usar.
     */
    private function exigePodeAtivar(Request $request, Product $candidato, array $galeria): void
    {
        if (! $candidato->active) {
            return;
        }

        if ($request->hasFile('image')) {
            $galeria[] = $request->file('image')->hashName('products');
        }

        if ($candidato->podeAtivar($galeria)) {
            return;
        }

        throw ValidationException::withMessages([
            'active' => ! $candidato->temPreco()
                ? 'Produto sem preço não pode ser ativado. Preencha o preço (ou os preços por volume) e salve de novo.'
                : 'Envie uma foto do produto antes de ativar.',
        ]);
    }

    /**
     * Volume fora da venda (config catalogo.volumes_desativados, ex.: 100 ml)
     * não entra como volume novo. Produto que já o tem (o "Teste" de
     * pagamento) segue salvando normalmente.
     */
    private function recusaVolumeDesativado(?array $sizes, array $atuais): void
    {
        $novos = array_diff($sizes ?? [], $atuais);
        $fora = array_values(array_intersect($novos, config('catalogo.volumes_desativados', [])));

        if ($fora) {
            throw ValidationException::withMessages([
                'sizes' => 'O volume de '.implode(', ', $fora).' não está mais à venda e não pode ser adicionado.',
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
     * Garrafa de cada foto (leva 18): image_volume[id] das que existem e
     * image_volume_nova da enviada agora; vazio = geral (garrafa grande).
     * Volume aceito = os à venda menos o padrão (o candidato já com os
     * volumes deste salvar); o que a foto já tinha passa mesmo fora de venda
     * (só não aparece no site). Com foto, pelo menos uma tem de ser geral.
     *
     * @return array{0: array<int, ?string>, 1: ?string} [id => volume, volume da nova]
     */
    private function volumesDasFotos(Request $request, Product $candidato, $imagens): array
    {
        $permitidos = $candidato->volumesComFotoPropria();
        $enviados = (array) $request->input('image_volume', []);
        $invalido = fn (?string $v, ?string $atual = null) => $v !== null && $v !== $atual && ! in_array($v, $permitidos, true);

        $volumes = [];
        foreach ($imagens as $img) {
            $v = array_key_exists($img->id, $enviados) ? (filled($enviados[$img->id]) ? (string) $enviados[$img->id] : null) : $img->volume;
            if ($invalido($v, $img->volume)) {
                throw ValidationException::withMessages(['image_volume' => "Volume \"{$v}\" não é uma garrafa deste produto."]);
            }
            $volumes[$img->id] = $v;
        }

        $nova = null;
        $todas = $volumes;
        if ($request->hasFile('image')) {
            $nova = filled($request->input('image_volume_nova')) ? (string) $request->input('image_volume_nova') : null;
            if ($invalido($nova)) {
                throw ValidationException::withMessages(['image_volume' => "Volume \"{$nova}\" não é uma garrafa deste produto."]);
            }
            $todas[] = $nova;
        }

        if ($todas !== [] && ! in_array(null, $todas, true)) {
            throw ValidationException::withMessages(['image_volume' => 'Pelo menos uma foto precisa ser geral (garrafa grande).']);
        }

        return [$volumes, $nova];
    }

    /**
     * Imagem nova entra na frente: geral vira a principal; de volume, a
     * primeira daquele volume (renumerarGaleria põe as gerais antes). Nada é
     * apagado (antes apagava arquivo + linha de todas, inclusive as fotos do
     * pacote, que não se regeram em produção).
     */
    private function handleImage(Request $request, Product $product, ?string $volume = null): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $path = FotoUpload::salvar($request->file('image'), 'products');

        ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'alt'        => $product->name,
            'volume'     => $volume,
            'is_primary' => false,
            'sort_order' => -1,
        ]);

        $product->renumerarGaleria();
    }

    /** Linha sempre; arquivo só se for upload do admin e ninguém mais usar. */
    private function apagaImagem(ProductImage $image): void
    {
        $image->delete();

        if ($image->isUploadDoAdmin() && ! ProductImage::where('path', $image->path)->exists()) {
            Storage::disk('public')->delete($image->path);
        }
    }
}
