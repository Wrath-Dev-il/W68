<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('purchase')->hasTable('purchase_returns')) {
            Schema::connection('purchase')->create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number')->unique();
                $table->unsignedBigInteger('po_id'); // Reference to purchase_orders.id
                $table->unsignedBigInteger('supplier_id'); // Reference to masterlist.suppliers
                $table->date('date');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->string('status')->default('Completed');
                $table->timestamps();
            });
        }

        if (!Schema::connection('purchase')->hasTable('purchase_return_items')) {
            Schema::connection('purchase')->create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_return_id');
                $table->unsignedBigInteger('product_id'); // Reference to masterlist.products
                $table->string('product_code');
                $table->string('description')->nullable();
                $table->integer('quantity');
                $table->string('oum')->nullable();
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('purchase')->dropIfExists('purchase_return_items');
        Schema::connection('purchase')->dropIfExists('purchase_returns');
    }
};
