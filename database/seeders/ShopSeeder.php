<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        if (Shop::where('slug', 'criaturas-magicas')->exists()) {
            $this->command->warn('ShopSeeder ya fue ejecutado. Saltando...');
            return;
        }
        $criaturas = Shop::create([
            'name' => 'Tienda de Criaturas Mágicas',
            'slug' => 'criaturas-magicas',
            'description' => 'Criaturas fantásticas para ayudarte en tus aventuras.',
            'is_active' => false,
        ]);

        $objetos = Shop::create([
            'name' => 'Tienda de Objetos Mágicos',
            'slug' => 'objetos-magicos',
            'description' => 'Objetos encantados con poderes únicos.',
            'is_active' => false,
        ]);

        $criaturasProducts = [
            [
                'name' => 'Lechuza de Atenea',
                'price' => 300,
                'validity_hours' => 24,
                'description' => 'Te da 200 pts en biblioteca poniendo el emoji (🦉) al final de la historia.',
            ],
            [
                'name' => 'Gato de la Bruja',
                'price' => 100,
                'validity_hours' => 24,
                'description' => 'Te aporta 100 pts en dinámica mostrándole el emoji (🐱) al moderador al final de la dinámica.',
            ],
            [
                'name' => 'Ardilla Rayada',
                'price' => 500,
                'validity_hours' => 24,
                'description' => 'Te da el doble de los galeones que ganes en cualquier actividad de dinero.',
            ],
            [
                'name' => 'Dragón Elemental',
                'price' => 150,
                'validity_hours' => 24,
                'description' => 'Te multiplica x2 los pts obtenidos en un reto diario. Debes poner este emoji (🐉) en el reto luego de activar el producto.',
            ],
        ];

        $objetosProducts = [
            [
                'name' => 'Talismán Teletransportador',
                'price' => 200,
                'validity_hours' => 24,
                'description' => 'Permite saltar un reto diario recibiendo el máximo de pts. Solo uno por copa.',
            ],
            [
                'name' => 'Llave Mística',
                'price' => 400,
                'validity_hours' => 24,
                'description' => 'Te da el doble de pts en un reto diario al hacerlo. Un solo uso.',
            ],
            [
                'name' => 'Varita de Luna',
                'price' => 200,
                'validity_hours' => 24,
                'description' => 'Abona 200 pts en el club de arte poniendo el emoji (🪄) junto con tu emblema.',
            ],
            [
                'name' => 'Orbe Elemental',
                'price' => 1000,
                'validity_hours' => 24,
                'description' => 'Te permite recibir todos los puntos de todas las actividades del día sin hacerlas. Solo una copa.',
            ],
        ];

        $this->seedProducts($criaturas, $criaturasProducts);
        $this->seedProducts($objetos, $objetosProducts);
    }

    private function seedProducts(Shop $shop, array $products): void
    {
        foreach ($products as $data) {
            $product = Product::create([
                'shop_id' => $shop->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'validity_hours' => $data['validity_hours'],
                'is_active' => true,
            ]);

            ProductVariant::create([
                'product_id' => $product->id,
                'name' => 'estándar',
                'price_override' => null,
                'stock' => 10,
            ]);
        }
    }
}