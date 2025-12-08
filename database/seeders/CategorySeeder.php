<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Guci Antik',
                'image' => 'categories/guci-antik.jpg'
            ],
            [
                'name' => 'Furniture Antik',
                'image' => 'categories/furniture-antik.jpg'
            ],
            [
                'name' => 'Keramik & Porselen',
                'image' => 'categories/keramik-porselen.jpg'
            ],
            [
                'name' => 'Lukisan Klasik',
                'image' => 'categories/lukisan-klasik.jpg'
            ],
            [
                'name' => 'Perhiasan Antik',
                'image' => 'categories/perhiasan-antik.jpeg'
            ],
            [
                'name' => 'Jam Antik',
                'image' => 'categories/jam-antik.jpg'
            ],
            [
                'name' => 'Tekstil Antik',
                'image' => 'categories/tekstil-antik.jpg'
            ],
            [
                'name' => 'Alat Musik Klasik',
                'image' => 'categories/alat-musik-klasik.jpg'
            ],
            [
                'name' => 'Buku & Manuskrip',
                'image' => 'categories/buku-manuskrip.jpeg'
            ],
            [
                'name' => 'Peralatan Rumah Tangga',
                'image' => 'categories/peralatan-rumah-tangga.jpg'
            ],
            [
                'name' => 'Patung & Arca',
                'image' => 'categories/patung-arca.jpg'
            ],
            [
                'name' => 'Uang Kuno',
                'image' => 'categories/uang-kuno.jpg'
            ],
            [
                'name' => 'Senjata Tradisional',
                'image' => 'categories/senjata-tradisional.jpg'
            ],
            [
                'name' => 'Perabotan Dapur',
                'image' => 'categories/perabotan-dapur.jpeg'
            ],
            [
                'name' => 'Aksesoris Vintage',
                'image' => 'categories/aksesoris-vintage.jpg'
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']], // Cari berdasarkan name
                ['image' => $category['image']] // Update image-nya
            );
        }
    }
}
