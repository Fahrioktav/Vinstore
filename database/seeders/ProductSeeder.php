<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Product::create([
            'store_id' => 1,
            'name' => 'Guci Premium',
            'stock' => 10,
            'price' => 1000000.00,
            'category' => 'Antik',
            'description' => 'Guci premium kualitas tinggi',
            'image' => 'uploads/products/1760163165_guci.jpeg',
            'created_at' => '2025-10-10 23:12:45',
            'updated_at' => '2025-10-10 23:12:45',
        ]);
    }
}
