<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with('category')->active()->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('product_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        $products = $query->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => (float) $p->price,
            'stock' => $p->stock,
            'image_url' => $p->image_url,
            'category' => ['id' => $p->category->id, 'name' => $p->category->name],
        ]);

        $categories = ProductCategory::active()->orderBy('name')->get(['id', 'name']);

        // Detectar si viene del calendario
        $cart = $request->session()->get('cart', ['slot_ids' => [], 'items' => []]);
        $fromCalendar = $request->boolean('from_calendar');
        $cartSlotsCount = count($cart['slot_ids'] ?? []);

        return Inertia::render('client/Catalog', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id']),
            'from_calendar' => $fromCalendar,
            'cart_slots_count' => $cartSlotsCount,
        ]);
    }
}
