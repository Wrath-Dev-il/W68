<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_orders', 'actual_total_amount')) {
                $table->decimal('actual_total_amount', 15, 2)->default(0)->after('total_amount');
            }
        });

        Schema::connection('purchase')->table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_order_items', 'actual_quantity')) {
                $table->integer('actual_quantity')->default(0)->after('quantity');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_order_items', 'actual_subtotal')) {
                $table->decimal('actual_subtotal', 15, 2)->default(0)->after('subtotal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('actual_total_amount');
        });

        Schema::connection('purchase')->table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['actual_quantity', 'actual_subtotal']);
        });
    }
};
