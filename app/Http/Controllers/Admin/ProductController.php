<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with('category')->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('product_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $products = $query->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'stock' => $p->stock,
            'min_stock' => $p->min_stock,
            'active' => $p->active,
            'low_stock' => $p->isLowStock(),
            'image_url' => $p->image_url,
            'category' => ['id' => $p->category->id, 'name' => $p->category->name],
        ]);

        $categories = ProductCategory::active()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('admin/Products', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        return redirect()->route('admin.products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Producto creado exitosamente.']);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'active' => 'boolean',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Eliminar imagen anterior
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return redirect()->route('admin.products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Producto actualizado exitosamente.']);
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('flash', ['type' => 'success', 'message' => 'Producto eliminado.']);
    }

    public function toggleActive(Product $product): RedirectResponse
    {
        $product->update(['active' => ! $product->active]);

        return redirect()->back()
            ->with('flash', ['type' => 'success', 'message' => 'Producto actualizado.']);
    }
}
