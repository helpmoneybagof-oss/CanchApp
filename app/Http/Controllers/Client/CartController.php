<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    private const SESSION_KEY = 'cart';

    /**
     * Vista del carrito.
     */
    public function index(Request $request): Response
    {
        $cart = $this->getCart($request);

        // Enriquecer con datos reales de productos
        $productIds = array_keys($cart['items'] ?? []);
        $products = $productIds
            ? Product::whereIn('id', $productIds)->active()->get()->keyBy('id')
            : collect();

        $items = [];
        foreach ($cart['items'] ?? [] as $productId => $qty) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }
            $items[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock' => $product->stock,
                'image_url' => $product->image_url,
                'quantity' => $qty,
                'subtotal' => (float) $product->price * $qty,
            ];
        }

        // Slots seleccionados
        $slotIds = $cart['slot_ids'] ?? [];
        $slots = $slotIds
            ? TimeSlot::whereIn('id', $slotIds)->orderBy('start_time')->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'date' => $s->date->format('d/m/Y'),
                    'start_formatted' => $s->start_time_formatted,
                    'end_formatted' => $s->end_time_formatted,
                    'price' => (float) $s->price,
                ])
            : [];

        $courtPrice = collect($slots)->sum('price');
        $consumablesPrice = collect($items)->sum('subtotal');
        $totalPrice = $courtPrice + $consumablesPrice;

        return Inertia::render('client/Cart', [
            'items' => $items,
            'slots' => $slots,
            'court_price' => $courtPrice,
            'consumables_price' => $consumablesPrice,
            'total_price' => $totalPrice,
        ]);
    }

    /**
     * Agregar / actualizar cantidad de un producto.
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::active()->findOrFail($request->product_id);

        if ($request->quantity > $product->stock) {
            return response()->json(['message' => 'Stock insuficiente.'], 422);
        }

        $cart = $this->getCart($request);
        $cart['items'][$product->id] = $request->quantity;
        $request->session()->put(self::SESSION_KEY, $cart);

        return response()->json(['message' => 'Producto agregado al carrito.', 'cart_count' => $this->cartItemCount($cart)]);
    }

    /**
     * Quitar un producto del carrito.
     */
    public function removeItem(Request $request, int $productId): JsonResponse
    {
        $cart = $this->getCart($request);
        unset($cart['items'][$productId]);
        $request->session()->put(self::SESSION_KEY, $cart);

        return response()->json(['message' => 'Producto eliminado del carrito.', 'cart_count' => $this->cartItemCount($cart)]);
    }

    /**
     * Guardar los slot_ids en el carrito (desde el calendario).
     */
    public function setSlots(Request $request): JsonResponse
    {
        $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'integer|exists:time_slots,id',
        ]);

        $cart = $this->getCart($request);
        $cart['slot_ids'] = $request->slot_ids;
        $request->session()->put(self::SESSION_KEY, $cart);

        return response()->json(['message' => 'Horarios guardados en el carrito.']);
    }

    /**
     * Limpiar completamente el carrito.
     */
    public function clear(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['message' => 'Carrito vaciado.']);
    }

    /**
     * Devuelve el carrito actual de la sesión.
     */
    private function getCart(Request $request): array
    {
        return $request->session()->get(self::SESSION_KEY, ['slot_ids' => [], 'items' => []]);
    }

    private function cartItemCount(array $cart): int
    {
        return array_sum($cart['items'] ?? []);
    }
}
