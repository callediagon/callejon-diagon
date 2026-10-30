<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Lista TODOS los productos de una tienda (incluye inactivos).
     * Solo para el tendero de esa tienda, director o vice.
     */
    public function index(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();

        $this->authorizeTender($request->user(), $shop);

        $products = $shop->products()
            ->with(['variants' => fn ($q) => $q->orderBy('id')])
            ->orderBy('id')
            ->get();

        return response()->json($products);
    }

    /**
     * Crea un producto con sus variantes en una sola transacción.
     * Solo para el tendero de esa tienda, director o vice.
     */
    public function store(Request $request, $slug)
    {
        $shop = Shop::where('slug', $slug)->firstOrFail();

        $this->authorizeTender($request->user(), $shop);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'validity_hours' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.price_override' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = DB::transaction(function () use ($validated, $shop) {
            $product = Product::create([
                'shop_id' => $shop->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'validity_hours' => $validated['validity_hours'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['variants'] as $v) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $v['name'],
                    'price_override' => $v['price_override'] ?? null,
                    'stock' => $v['stock'] ?? null,
                ]);
            }

            return $product;
        });

        $product->load('variants');

        return response()->json($product, 201);
    }

    /**
     * Valida que el usuario sea tendero de la tienda, director o vice.
     * Si no, aborta con 403.
     */
    private function authorizeTender($user, Shop $shop): void
    {
        if (!$user) {
            abort(401, 'No autenticado.');
        }

        // Director y Vice pueden todo
        if ($user->hasAnyRole(['director', 'vice'])) {
            return;
        }

        // Tendero: debe estar en el pivote shop_user de ESA tienda
        $isTender = $user->shops()->where('shops.id', $shop->id)->exists();

        if (!$isTender) {
            abort(403, 'No eres tendero de esta tienda.');
        }
    }
}