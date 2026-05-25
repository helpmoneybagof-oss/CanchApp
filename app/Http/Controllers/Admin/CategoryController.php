<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ProductCategory::withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'active' => $c->active,
                'products_count' => $c->products_count,
            ]);

        return Inertia::render('admin/Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:product_categories,name',
            'description' => 'nullable|string|max:300',
            'active' => 'boolean',
        ]);

        ProductCategory::create($request->only(['name', 'description', 'active']));

        return redirect()->route('admin.categories.index')
            ->with('flash', ['type' => 'success', 'message' => 'Categoría creada exitosamente.']);
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:product_categories,name,'.$category->id,
            'description' => 'nullable|string|max:300',
            'active' => 'boolean',
        ]);

        $category->update($request->only(['name', 'description', 'active']));

        return redirect()->route('admin.categories.index')
            ->with('flash', ['type' => 'success', 'message' => 'Categoría actualizada.']);
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return redirect()->back()
                ->withErrors(['message' => 'No se puede eliminar una categoría con productos asociados.']);
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('flash', ['type' => 'success', 'message' => 'Categoría eliminada.']);
    }
}
