<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes the unique constraint from order_number
     * because multiple sales_orders can now share the same order_number
     * (one record per invoice). The combination of order_number + invoice_numbers
     * should be unique instead.
     */
    public function up(): void
    {
        Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
            // Drop the unique constraint on order_number
            $table->dropUnique(['order_number']);
        });

        // Add a unique constraint on the combination of order_number and invoice_numbers
        // Note: This uses a generated name, or you can specify a custom name
        Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
            $table->unique(['order_number', 'invoice_numbers'], 'sales_orders_order_invoice_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
            // Remove the composite unique constraint
            $table->dropUnique('sales_orders_order_invoice_unique');
        });

        Schema::connection('sales')->table('sales_orders', function (Blueprint $table) {
            // Restore the original unique constraint on order_number
            $table->unique('order_number');
        });
    }
};
