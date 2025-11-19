<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Store::create([
            'user_id' => 2,
            'store_name' => 'Mamad Store',
            'category' => 'Antik',
            'description' => 'Menjual Barang Antik Berkualitas Tinggi',
            'location' => 'Ciater',
            'created_at' => '2025-09-30 06:01:14',
            'updated_at' => '2025-09-30 06:01:14',
        ]);
        
        Store::create([
            'user_id' => 3,
            'store_name' => 'Asa Store',
            'category' => 'Antik',
            'description' => 'Menjual Barang Antik Berkualitas Tinggi',
            'location' => 'Kemayoran',
            'created_at' => '2025-09-30 06:01:14',
            'updated_at' => '2025-09-30 06:01:14',
        ]);
    }
}
