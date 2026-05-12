<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'stores' => ['prefix' => 'STR', 'length' => 11],
        'products' => ['prefix' => 'PRD', 'length' => 11],
        'categories' => ['prefix' => 'CAT', 'length' => 11],
        'carts' => ['prefix' => 'CRT', 'length' => 11],
        'contacts' => ['prefix' => 'MSG', 'length' => 11],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName => $config) {
            Schema::table($tableName, function (Blueprint $table) use ($config) {
                $table->string('public_id', $config['length'])->nullable()->unique()->after('id');
            });

            DB::table($tableName)
                ->whereNull('public_id')
                ->orderBy('id')
                ->select('id')
                ->chunkById(100, function ($records) use ($tableName, $config) {
                    foreach ($records as $record) {
                        DB::table($tableName)
                            ->where('id', $record->id)
                            ->update(['public_id' => $this->generatePublicId($tableName, $config['prefix'])]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(array_keys($this->tables)) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }

    private function generatePublicId(string $tableName, string $prefix): string
    {
        do {
            $publicId = $prefix . random_int(10000000, 99999999);
        } while (DB::table($tableName)->where('public_id', $publicId)->exists());

        return $publicId;
    }
};
