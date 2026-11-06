<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasColumn('sales_orders', 'is_vat')) {
            Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
                // W68_SALES_ORDER_VAT_TOGGLE_20261106
                // NULL = no VAT, 1 = +12% VAT applied to every item unit price.
                $table->tinyInteger('is_vat')->nullable()->after('order_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sales')->hasColumn('sales_orders', 'is_vat')) {
            Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
                $table->dropColumn('is_vat');
            });
        }
    }
};
