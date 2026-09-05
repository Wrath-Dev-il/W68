<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('consignment_invoice_items')) {
            Schema::connection('sales')->create('consignment_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consignment_invoice_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_code');
                $table->string('description')->nullable();
                $table->integer('quantity')->default(0);
                $table->integer('additional_qty')->default(0);
                $table->string('oum')->default('PCS');
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->text('particulars')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('consignment_invoice_items');
    }
};
