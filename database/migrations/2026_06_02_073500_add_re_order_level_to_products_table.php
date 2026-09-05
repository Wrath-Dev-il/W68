<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (!Schema::connection('masterlist')->hasColumn('products', 'Re_order_level')) {
                $table->integer('Re_order_level')->nullable()->default(null)->after('on_hand');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (Schema::connection('masterlist')->hasColumn('products', 'Re_order_level')) {
                $table->dropColumn('Re_order_level');
            }
        });
    }
};
