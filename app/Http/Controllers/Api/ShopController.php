<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Lista de tiendas activas (para el mapa del Callejón).
     * Cualquier usuario autenticado puede verlas.
     */
    public function index()
    {
        $shops = Shop::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'description']);

        return response()->json($shops);
    }

    /**
     * Detalle de una tienda con sus productos activos y variantes.
     * Cualquier usuario autenticado puede verla.
     */
    public function show($slug)
    {
        $shop = Shop::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $shop->load(['products' => function ($q) {
            $q->where('is_active', true)
              ->with(['variants' => function ($v) {
                  $v->orderBy('id');
              }]);
        }]);

        return response()->json($shop);
    }

    /**
     * Tiendas donde el usuario autenticado es tendero.
     * Solo devuelve tiendas activas.
     */
    public function myShops(Request $request)
    {
        $shops = $request->user()
            ->shops()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['shops.id', 'shops.name', 'shops.slug', 'shops.description']);

        return response()->json($shops);
    }
}