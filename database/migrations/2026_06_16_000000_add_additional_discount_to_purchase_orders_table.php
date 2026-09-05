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
            $table->decimal('additional_discount_percent', 5, 2)->default(0)->after('actual_total_amount')->comment('Additional discount percentage applied after item discounts');
            $table->decimal('additional_discount_amount', 15, 2)->default(0)->after('additional_discount_percent')->comment('Additional discount amount in PHP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['additional_discount_percent', 'additional_discount_amount']);
        });
    }
};
