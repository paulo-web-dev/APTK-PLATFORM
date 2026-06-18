<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = ProductCategory::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new ProductCategory(['active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $data['slug']   = $this->uniqueSlug($data['name']);
        $data['active'] = $request->boolean('active');

        ProductCategory::create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Categoria criada.');
    }

    public function edit(ProductCategory $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $data = $this->validateData($request);

        // O slug permanece estável para não quebrar links já existentes.
        $data['active'] = $request->boolean('active');

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Categoria atualizada.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('status', 'Não é possível excluir: a categoria ainda tem produtos. Mova-os primeiro.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Categoria removida.');
    }

    // ------------------------------------------------------------------ //

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $i = 2;

        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
