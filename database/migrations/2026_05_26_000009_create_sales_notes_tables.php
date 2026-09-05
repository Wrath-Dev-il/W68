<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('sales_notes')) {
            Schema::connection('sales')->create('sales_notes', function (Blueprint $table) {
                $table->id();
                $table->string('sales_number')->unique();
                $table->string('so_type')->default('Sales Order'); // Sales Order, Consignment, Cash
                $table->unsignedBigInteger('customer_id');
                $table->string('customer_name');
                $table->date('order_date');
                $table->string('salesman')->nullable();
                $table->string('prepared_by')->nullable();
                $table->string('checked_by')->nullable();
                $table->string('packed_by')->nullable();
                $table->boolean('is_rush')->default(false);
                $table->decimal('gross_total', 15, 2)->default(0);
                $table->decimal('total_discount', 15, 2)->default(0);
                $table->decimal('net_total', 15, 2)->default(0);
                $table->string('status')->default('Open'); // Open, Partial, Closed, Cancelled
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('sales')->hasTable('sales_note_items')) {
            Schema::connection('sales')->create('sales_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_note_id');
                $table->unsignedBigInteger('product_id');
                $table->string('product_code');
                $table->string('description')->nullable();
                $table->integer('quantity');
                $table->string('oum')->nullable();
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->integer('bonus')->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_note_items');
        Schema::connection('sales')->dropIfExists('sales_notes');
    }
};
