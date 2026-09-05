<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasColumn('sales_order_items', 'actual_qty')) {
            Schema::connection('sales')->table('sales_order_items', function (Blueprint $table) {
                $table->integer('actual_qty')->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('actual_qty');
        });
    }
};
