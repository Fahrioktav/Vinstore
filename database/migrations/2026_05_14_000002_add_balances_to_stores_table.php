<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('available_balance', 14, 2)->default(0)->after('photo');
            $table->decimal('withdrawn_balance', 14, 2)->default(0)->after('available_balance');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['available_balance', 'withdrawn_balance']);
        });
    }
};
