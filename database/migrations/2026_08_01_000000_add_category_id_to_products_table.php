<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('price');
        });

        // Migrate existing string categories into categories table
        $existing = DB::table('products')->select('category')->distinct()->pluck('category');
        foreach ($existing as $name) {
            if ($name === null) {
                continue;
            }
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $catId = DB::table('categories')->where('name', $name)->value('id');
            if (! $catId) {
                $catId = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('products')->where('category', $name)->update(['category_id' => $catId]);
        }

        // Add foreign key and remove old column
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            // Note: dropping columns requires doctrine/dbal package; if not available, run a separate migration to drop the column.
            if (Schema::hasColumn('products', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    public function down(): void
    {
        // Recreate category string column
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('price');
        });

        // Copy back names from categories
        $products = DB::table('products')->select('id', 'category_id')->get();
        foreach ($products as $p) {
            $name = $p->category_id ? DB::table('categories')->where('id', $p->category_id)->value('name') : null;
            DB::table('products')->where('id', $p->id)->update(['category' => $name]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
