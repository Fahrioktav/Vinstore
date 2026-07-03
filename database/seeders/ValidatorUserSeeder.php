<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ValidatorUserSeeder extends Seeder
{
    /**
     * Membuat akun validator barang antik (idempotent).
     * Jalankan dengan: php artisan db:seed --class=ValidatorUserSeeder
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'validator@gmail.com'],
            [
                'username' => 'validator',
                'first_name' => 'Validator',
                'last_name' => 'Barang Antik',
                'phone' => '081200000000',
                'address' => 'Jakarta',
                'password' => Hash::make('password'),
                'role' => 'validator',
            ]
        );
    }
}
