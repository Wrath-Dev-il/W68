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
        Schema::connection('sales')->create('sales_note_converted_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_sales_note_id')->comment('Original partial sales note');
            $table->unsignedBigInteger('source_sales_note_item_id')->comment('Original sales note item');
            $table->unsignedBigInteger('target_sales_note_id')->comment('New sales note receiving converted items');
            $table->unsignedBigInteger('sales_order_id')->nullable()->comment('Related sales order');
            $table->unsignedBigInteger('sales_order_item_id')->nullable()->comment('Related sales order item');
            $table->unsignedBigInteger('product_id');
            $table->decimal('converted_qty', 10, 2)->comment('Quantity converted from remaining');
            $table->decimal('converted_amount', 10, 2)->comment('Amount for converted quantity');
            $table->timestamps();

            // Indexes for performance
            $table->index('source_sales_note_id');
            $table->index('target_sales_note_id');
            $table->index('sales_order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_note_converted_items');
    }
};
