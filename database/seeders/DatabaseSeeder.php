<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin por defecto
        User::updateOrCreate(['email' => 'admin@cancha.com'], [
            'name' => 'Administrador',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // Cliente de prueba
        User::updateOrCreate(['email' => 'cliente@cancha.com'], [
            'name' => 'Cliente Demo',
            'password' => bcrypt('password'),
            'role' => 'client',
            'phone' => '3001234567',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // Canchas de ejemplo
        Court::updateOrCreate(['name' => 'Cancha Fútbol 5'], [
            'type'           => 'Fútbol 5',
            'price_per_hour' => 80000,
            'description'    => 'Cancha de fútbol 5 con superficie sintética de última generación.',
            'capacity'       => 5,
            'surface'        => 'Sintética',
            'start_hour'     => 17,
            'end_hour'       => 23,
            'active'         => true,
        ]);

        // Configuración inicial del sistema
        Setting::setValue('court_price_per_hour', '50000', 'Precio por hora de la cancha en pesos');
        Setting::setValue('court_name', 'Cancha Sintética', 'Nombre de la cancha');
        Setting::setValue('court_address', 'Dirección de la cancha', 'Dirección física');
        Setting::setValue('contact_phone', '3001234567', 'Teléfono de contacto');
        Setting::setValue('contact_email', 'admin@cancha.com', 'Correo de contacto');

        // Categorías de productos
        $bebidas   = ProductCategory::updateOrCreate(['name' => 'Bebidas'],   ['description' => 'Agua, jugos, gaseosas y energizantes', 'active' => true]);
        $snacks    = ProductCategory::updateOrCreate(['name' => 'Snacks'],    ['description' => 'Mecatos y pasabocas', 'active' => true]);
        $equipos   = ProductCategory::updateOrCreate(['name' => 'Equipos'],   ['description' => 'Petos, balones y accesorios', 'active' => true]);

        // Productos de ejemplo
        $productos = [
            ['category' => $bebidas,  'name' => 'Agua 600ml',        'description' => 'Agua mineral natural',              'price' => 2000,  'stock' => 50, 'min_stock' => 10],
            ['category' => $bebidas,  'name' => 'Gatorade 500ml',    'description' => 'Bebida hidratante deportiva',        'price' => 4500,  'stock' => 30, 'min_stock' => 5],
            ['category' => $bebidas,  'name' => 'Red Bull 250ml',    'description' => 'Bebida energizante',                 'price' => 7000,  'stock' => 20, 'min_stock' => 5],
            ['category' => $bebidas,  'name' => 'Jugo Hit 300ml',    'description' => 'Jugo de fruta natural',              'price' => 3000,  'stock' => 40, 'min_stock' => 8],
            ['category' => $snacks,   'name' => 'Papas Margarita',   'description' => 'Papas fritas crujientes 105g',       'price' => 3500,  'stock' => 25, 'min_stock' => 5],
            ['category' => $snacks,   'name' => 'Maní con pasas',    'description' => 'Mix energético 100g',               'price' => 2500,  'stock' => 30, 'min_stock' => 5],
            ['category' => $snacks,   'name' => 'Barra energética',  'description' => 'Barra de cereal y miel',            'price' => 3000,  'stock' => 20, 'min_stock' => 5],
            ['category' => $equipos,  'name' => 'Peto deportivo',    'description' => 'Peto de tela para diferenciación',  'price' => 2000,  'stock' => 20, 'min_stock' => 4],
            ['category' => $equipos,  'name' => 'Balón de fútbol',   'description' => 'Balón oficial No. 5',               'price' => 5000,  'stock' => 5,  'min_stock' => 1],
        ];

        foreach ($productos as $p) {
            Product::updateOrCreate(
                ['name' => $p['name'], 'product_category_id' => $p['category']->id],
                [
                    'description'         => $p['description'],
                    'price'               => $p['price'],
                    'stock'               => $p['stock'],
                    'min_stock'           => $p['min_stock'],
                    'active'              => true,
                ]
            );
        }
    }
}
