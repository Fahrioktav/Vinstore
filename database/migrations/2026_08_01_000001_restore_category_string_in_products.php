<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('category')->nullable()->after('price');
            });
        }

        if (Schema::hasColumn('products', 'category_id')) {
            // Populate the restored category column from categories table.
            // Ditulis lewat query builder, bukan raw SQL: sintaks
            // "UPDATE ... JOIN ... SET" khusus MySQL dan tidak dikenali SQLite
            // yang dipakai sebagai database testing di phpunit.xml.
            foreach (DB::table('categories')->select('id', 'name')->get() as $category) {
                DB::table('products')
                    ->where('category_id', $category->id)
                    ->update(['category' => $category->name]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
