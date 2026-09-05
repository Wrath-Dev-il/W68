<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('masterlist')->hasColumn('online_products', 'product_id')) {
            return;
        }

        Schema::connection('masterlist')->table('online_products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('masterlist')->hasColumn('online_products', 'product_id')) {
            return;
        }

        Schema::connection('masterlist')->table('online_products', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropColumn('product_id');
        });
    }
};
