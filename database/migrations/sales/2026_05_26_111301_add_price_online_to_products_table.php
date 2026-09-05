<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->decimal('price_online', 15, 2)->nullable()->after('selling_price');
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn('price_online');
        });
    }
};
