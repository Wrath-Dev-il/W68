<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('sales_returns')) {
            Schema::connection('sales')->create('sales_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number')->unique();
                $table->string('invoice_no')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->integer('total_items')->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('Completed');
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('sales')->hasTable('sales_return_items')) {
            Schema::connection('sales')->create('sales_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_return_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_code')->nullable();
                $table->string('part_number')->nullable();
                $table->string('description')->nullable();
                $table->string('application')->nullable();
                $table->integer('quantity')->default(0);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('sales_return_id')->references('id')->on('sales_returns')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_return_items');
        Schema::connection('sales')->dropIfExists('sales_returns');
    }
};
