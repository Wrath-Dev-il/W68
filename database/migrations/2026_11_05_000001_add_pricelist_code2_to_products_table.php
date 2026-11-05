<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (!Schema::connection('masterlist')->hasColumn('products', 'pricelist_code2')) {
                $table->string('pricelist_code2', 255)->nullable()->after('pricelist_code');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (Schema::connection('masterlist')->hasColumn('products', 'pricelist_code2')) {
                $table->dropColumn('pricelist_code2');
            }
        });
    }
};
