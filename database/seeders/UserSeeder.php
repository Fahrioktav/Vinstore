<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'username' => 'admin',
            'first_name' => 'admin',
            'last_name' => 'ganteng',
            'email' => 'adminganteng@gmail.com',
            'phone' => '0237483287450',
            'address' => 'Pamulang',
            'password' => '$2y$12$dzBR6qzeYOzxjYO4gDHgI.fy.dCDxmqHwJpBDr17Zdy7YEXPX/eJ.',
            'created_at' => '2025-09-30 05:58:16',
            'updated_at' => '2025-09-30 05:58:16',
            'role' => 'admin',
        ]);

        User::create([
            'username' => 'cikidaw',
            'first_name' => 'cikidaw',
            'last_name' => 'daw',
            'email' => 'cikidaw@gmail.com',
            'phone' => '02374028',
            'address' => 'Ciater',
            'password' => '$2y$12$VnCIN3bPfeLxW6BVi/6fgeKDvINVxTSQOU40rKPcpm/jOZrWMBzIe',
            'created_at' => '2025-09-30 06:00:03',
            'updated_at' => '2025-09-30 06:01:14',
            'role' => 'seller',
        ]);

        User::create([
            'username' => 'asa',
            'first_name' => 'asa',
            'last_name' => 'batford',
            'email' => 'asabatford@gmail.com',
            'phone' => '082127412',
            'address' => 'Pamulang',
            'password' => '$2y$12$qvKQOpfceLkNUZZierH6r.gT6FfvQyMBYImxHh5Qu57sEClG116fO',
            'created_at' => '2025-10-08 08:07:30',
            'updated_at' => '2025-10-08 08:07:30',
            'role' => 'seller',
        ]);

        User::create([
            'username' => 'asep',
            'first_name' => 'asep',
            'last_name' => 'maguire',
            'email' => 'asep@example.com',
            'phone' => '083954598',
            'address' => 'BSD',
            'password' => '$2y$12$7LhQBZxWjeU7v.DfdTQBaOV4w.qvIrPY0bLWW.hnjxsiUENXOmZRW',
            'created_at' => '2025-10-10 23:14:32',
            'updated_at' => '2025-10-10 23:14:32',
            'role' => 'user',
        ]);
    }
}
