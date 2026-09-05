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
        if (!Schema::connection('ledger')->hasTable('product_ledgers')) {
            Schema::connection('ledger')->create('product_ledgers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id'); // Association to masterlist.products
                $table->string('transaction_type'); // IN or OUT
                $table->date('date');
                $table->string('transaction_number'); // Trans#
                $table->string('reference_number')->nullable(); // Ref#
                $table->string('entity_name')->nullable(); // Supplier or Customer Name
                $table->integer('quantity_in')->default(0);
                $table->integer('quantity_out')->default(0);
                $table->integer('balance_stock')->default(0);
                $table->string('oum')->nullable(); // Unit of Measure
                $table->decimal('price', 15, 2)->default(0);
                $table->decimal('cost', 15, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('ledger')->dropIfExists('product_ledgers');
    }
};
