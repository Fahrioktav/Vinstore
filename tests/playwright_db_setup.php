<?php

// Boot Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

if (env('PLAYWRIGHT_DB_SETUP') !== '1') {
    echo "Refusing to run database setup without PLAYWRIGHT_DB_SETUP=1.\n";
    echo "This protects your normal local users from accidental test data changes.\n";
    exit(1);
}

try {
    DB::transaction(function () {
        $passwordHash = Hash::make('password123');

        // 1. Ensure we have the user accounts matching the test files.
        // Do not overwrite every user password here; this script may run against
        // a local development database that contains real manually-created users.
        foreach (['Koin', 'Senjata', 'Antik'] as $categoryName) {
            Category::firstOrCreate(['name' => $categoryName]);
        }

        // asep@example.com (buyer)
        $asep = User::updateOrCreate(
            ['email' => 'asep@example.com'],
            [
                'username' => 'asep',
                'first_name' => 'Asep',
                'last_name' => 'Maguire',
                'phone' => '083954598',
                'address' => 'BSD',
                'role' => 'user',
                'password' => $passwordHash,
            ]
        );

        // pembeli@example.com (buyer 1)
        $pembeli = User::updateOrCreate(
            ['email' => 'pembeli@example.com'],
            [
                'username' => 'pembeli1',
                'first_name' => 'Pembeli',
                'last_name' => 'Satu',
                'phone' => '081299991111',
                'address' => 'Jakarta',
                'role' => 'user',
                'password' => $passwordHash,
            ]
        );

        // pembeli2@example.com (buyer 2)
        $pembeli2 = User::updateOrCreate(
            ['email' => 'pembeli2@example.com'],
            [
                'username' => 'pembeli2',
                'first_name' => 'Pembeli',
                'last_name' => 'Dua',
                'phone' => '081299992222',
                'address' => 'Bandung',
                'role' => 'user',
                'password' => $passwordHash,
            ]
        );

        // seller@example.com (seller 1)
        $seller = User::updateOrCreate(
            ['email' => 'seller@example.com'],
            [
                'username' => 'seller1',
                'first_name' => 'Seller',
                'last_name' => 'Satu',
                'phone' => '081288881111',
                'address' => 'Tangerang',
                'role' => 'seller',
                'password' => $passwordHash,
            ]
        );

        $store1 = Store::updateOrCreate(
            ['user_id' => $seller->id],
            [
                'store_name' => 'Toko Barang Antik Seller 1',
                'category' => 'Antik',
                'description' => 'Toko antik terlengkap',
                'location' => 'Tangerang',
            ]
        );

        // sellera@example.com (seller A)
        $sellerA = User::updateOrCreate(
            ['email' => 'sellera@example.com'],
            [
                'username' => 'sellera',
                'first_name' => 'Seller',
                'last_name' => 'A',
                'phone' => '081288882222',
                'address' => 'Bekasi',
                'role' => 'seller',
                'password' => $passwordHash,
            ]
        );

        $storeA = Store::updateOrCreate(
            ['user_id' => $sellerA->id],
            [
                'store_name' => 'Toko Antik Seller A',
                'category' => 'Antik',
                'description' => 'Koleksi antik A',
                'location' => 'Bekasi',
            ]
        );

        // sellerb@example.com (seller B)
        $sellerB = User::updateOrCreate(
            ['email' => 'sellerb@example.com'],
            [
                'username' => 'sellerb',
                'first_name' => 'Seller',
                'last_name' => 'B',
                'phone' => '081288883333',
                'address' => 'Bogor',
                'role' => 'seller',
                'password' => $passwordHash,
            ]
        );

        $storeB = Store::updateOrCreate(
            ['user_id' => $sellerB->id],
            [
                'store_name' => 'Toko Antik Seller B',
                'category' => 'Antik',
                'description' => 'Koleksi antik B',
                'location' => 'Bogor',
            ]
        );

        // validator@gmail.com
        $validator = User::updateOrCreate(
            ['email' => 'validator@gmail.com'],
            [
                'username' => 'validator',
                'first_name' => 'Validator',
                'last_name' => 'Barang Antik',
                'phone' => '081200000000',
                'address' => 'Jakarta',
                'role' => 'validator',
                'password' => $passwordHash,
            ]
        );

        // adminganteng@gmail.com (admin)
        $admin = User::updateOrCreate(
            ['email' => 'adminganteng@gmail.com'],
            [
                'username' => 'admin',
                'first_name' => 'Admin',
                'last_name' => 'Ganteng',
                'phone' => '081211112222',
                'address' => 'Pamulang',
                'role' => 'admin',
                'password' => $passwordHash,
            ]
        );

        // 3. Clear existing test products to prevent conflicts
        Product::whereIn('name', [
            'Koin Emas Kerajaan Majapahit',
            'Guci Premium',
            'Keris Pusaka Omyang Jimbe',
            'Pedang Katana Kuno',
            'Koin Kuno Yasin',
        ])->delete();

        // 4. Seed products for tests
        // Normal approved product for purchase (TC-JB-03)
        Product::create([
            'store_id' => $store1->id,
            'name' => 'Koin Emas Kerajaan Majapahit', // Let's match the name in TC-JB-03
            'stock' => 5,
            'price' => 2500000.00,
            'category' => 'Koin',
            'description' => 'Koin emas kuno era Majapahit asli bersertifikat.',
            'image' => 'products/dummy_koin.jpg',
            'certificate' => 'products/dummy_cert.pdf',
            'approval_status' => Product::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'validated_at' => now(),
            'validated_by' => $validator->id,
            'sale_type' => Product::SALE_TYPE_NORMAL,
        ]);

        // Barterable product owned by Seller B (requested in barter test)
        Product::create([
            'store_id' => $storeB->id,
            'name' => 'Keris Pusaka Omyang Jimbe',
            'stock' => 1,
            'price' => 5000000.00,
            'category' => 'Senjata',
            'description' => 'Keris pusaka kuno peninggalan leluhur.',
            'image' => 'products/dummy_keris.jpg',
            'is_barterable' => true,
            'approval_status' => Product::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'validated_at' => now(),
            'validated_by' => $validator->id,
            'sale_type' => Product::SALE_TYPE_NORMAL,
        ]);

        // Barterable product owned by Seller A (offered in barter test)
        Product::create([
            'store_id' => $storeA->id,
            'name' => 'Pedang Katana Kuno',
            'stock' => 1,
            'price' => 4500000.00,
            'category' => 'Senjata',
            'description' => 'Pedang katana antik buatan pandai besi Jepang abad 18.',
            'image' => 'products/dummy_katana.jpg',
            'is_barterable' => true,
            'approval_status' => Product::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'validated_at' => now(),
            'validated_by' => $validator->id,
            'sale_type' => Product::SALE_TYPE_NORMAL,
        ]);

        // Tebak Harga product (active) (TC-TH-01, TC-TH-02)
        Product::create([
            'store_id' => $store1->id,
            'name' => 'Koin Kuno Yasin',
            'stock' => 1,
            'price' => 800000.00, // real price
            'category' => 'Koin',
            'description' => 'Koin kuno bertuliskan Yasin sangat langka.',
            'image' => 'products/dummy_yasin.jpg',
            'approval_status' => Product::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'validated_at' => now(),
            'validated_by' => $validator->id,
            'sale_type' => Product::SALE_TYPE_TEBAK_HARGA,
            'guess_status' => Product::GUESS_ACTIVE,
            'guess_starts_at' => Carbon::now()->subHours(12),
            'guess_ends_at' => Carbon::now()->addHours(12),
        ]);

        // 5. Clear existing test auctions to prevent conflicts
        Auction::whereIn('name', ['Guci Kuno Dinasti Ming'])->delete();

        // Approved active auction for TC-LE-02 and TC-LE-03
        Auction::create([
            'store_id' => $store1->id,
            'name' => 'Guci Kuno Dinasti Ming',
            'description' => 'Guci antik dinasti ming utuh tanpa retak.',
            'image' => 'auctions/dummy_guci.jpg',
            'starting_price' => 10000000.00,
            'min_increment' => 500000.00,
            'current_price' => 10000000.00,
            'starts_at' => Carbon::now()->subHours(12),
            'ends_at' => Carbon::now()->addHours(12),
            'approval_status' => 'approved',
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        echo "Created and verified Playwright test users and products successfully.\n";
    });
} catch (\Throwable $e) {
    echo 'Error during database setup: '.$e->getMessage()."\n";
    exit(1);
}
