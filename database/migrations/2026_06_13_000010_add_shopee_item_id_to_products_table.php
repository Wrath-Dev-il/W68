<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('masterlist')->hasColumn('products', 'shopee_item_id')) {
            return;
        }
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->string('shopee_item_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn('shopee_item_id');
        });
    }
};
