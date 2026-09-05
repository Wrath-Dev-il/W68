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
        if (!Schema::connection('purchase')->hasTable('purchase_orders')) {
            // Purchase Orders Table (Header)
            Schema::connection('purchase')->create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number')->unique();
                $table->unsignedBigInteger('supplier_id'); // Foreign key to core4_masterlist.suppliers
                $table->date('date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('reference_number')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('Pending'); // Pending, Approved, Received, Cancelled
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('purchase')->hasTable('purchase_order_items')) {
            // Purchase Order Items Table (Normalized)
            Schema::connection('purchase')->create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id');
                $table->unsignedBigInteger('product_id'); // Foreign key to core4_masterlist.products
                $table->string('product_code');
                $table->string('description')->nullable();
                $table->integer('quantity');
                $table->integer('received_quantity')->default(0);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('subtotal', 15, 2);
                $table->timestamps();

                // Foreign key constraint (optional since it's across DBs, but good for local)
                // $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->dropIfExists('purchase_order_items');
        Schema::connection('purchase')->dropIfExists('purchase_orders');
    }
};
