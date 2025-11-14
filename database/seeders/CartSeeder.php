<?php

namespace Database\Seeders;

use App\Models\Cart;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Cart::create([
            'id' => 1,
            'user_id' => 4,
            'product_id' => 1,
            'quantity' => 1,
            'created_at' => '2025-10-10 23:15:16',
            'updated_at' => '2025-10-10 23:15:16',
        ]);

        Cart::create([
            'id' => 2,
            'user_id' => 3,
            'product_id' => 1,
            'quantity' => 1,
            'created_at' => '2025-10-15 10:05:37',
            'updated_at' => '2025-10-15 10:05:37',
        ]);
    }
}
