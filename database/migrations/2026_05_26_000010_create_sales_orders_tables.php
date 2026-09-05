<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('sales_orders')) {
            Schema::connection('sales')->create('sales_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_note_id')->nullable();
                $table->string('order_number')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->string('customer_name');
                $table->text('invoice_numbers')->nullable();
                $table->string('waybill_no')->nullable();
                $table->date('waybill_date')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('Open');
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('sales')->hasTable('sales_order_items')) {
            Schema::connection('sales')->create('sales_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_code');
                $table->string('description')->nullable();
                $table->integer('quantity');
                $table->integer('additional_qty')->default(0);
                $table->string('oum')->nullable();
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->string('particulars')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_order_items');
        Schema::connection('sales')->dropIfExists('sales_orders');
    }
};
