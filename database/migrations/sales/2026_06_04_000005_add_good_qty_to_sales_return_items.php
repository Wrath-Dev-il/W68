<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sales')->hasTable('sales_return_items') && !Schema::connection('sales')->hasColumn('sales_return_items', 'good_qty')) {
            Schema::connection('sales')->table('sales_return_items', function (Blueprint $table) {
                $table->integer('good_qty')->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sales')->hasColumn('sales_return_items', 'good_qty')) {
            Schema::connection('sales')->table('sales_return_items', function (Blueprint $table) {
                $table->dropColumn('good_qty');
            });
        }
    }
};
