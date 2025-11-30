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
                'image' => 'assets/categories/guci-antik.jpg'
            ],
            [
                'name' => 'Furniture Antik',
                'image' => 'assets/categories/furniture-antik.jpg'
            ],
            [
                'name' => 'Keramik & Porselen',
                'image' => 'assets/categories/keramik-porselen.jpg'
            ],
            [
                'name' => 'Lukisan Klasik',
                'image' => 'assets/categories/lukisan-klasik.jpg'
            ],
            [
                'name' => 'Perhiasan Antik',
                'image' => 'assets/categories/perhiasan-antik.jpeg'
            ],
            [
                'name' => 'Jam Antik',
                'image' => 'assets/categories/jam-antik.jpg'
            ],
            [
                'name' => 'Tekstil Antik',
                'image' => 'assets/categories/tekstil-antik.jpg'
            ],
            [
                'name' => 'Alat Musik Klasik',
                'image' => 'assets/categories/alat-musik-klasik.jpg'
            ],
            [
                'name' => 'Buku & Manuskrip',
                'image' => 'assets/categories/buku-manuskrip.jpeg'
            ],
            [
                'name' => 'Peralatan Rumah Tangga',
                'image' => 'assets/categories/peralatan-rumah-tangga.jpg'
            ],
            [
                'name' => 'Patung & Arca',
                'image' => 'assets/categories/patung-arca.jpg'
            ],
            [
                'name' => 'Uang Kuno',
                'image' => 'assets/categories/uang-kuno.jpg'
            ],
            [
                'name' => 'Senjata Tradisional',
                'image' => 'assets/categories/senjata-tradisional.jpg'
            ],
            [
                'name' => 'Perabotan Dapur',
                'image' => 'assets/categories/perabotan-dapur.jpeg'
            ],
            [
                'name' => 'Aksesoris Vintage',
                'image' => 'assets/categories/aksesoris-vintage.jpg'
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
