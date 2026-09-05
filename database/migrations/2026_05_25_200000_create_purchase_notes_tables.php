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
        if (!Schema::connection('purchase')->hasTable('purchase_notes')) {
            // Purchase Notes Table (Header)
            Schema::connection('purchase')->create('purchase_notes', function (Blueprint $table) {
                $table->id();
                $table->string('purchase_note_number')->unique();
                $table->unsignedBigInteger('supplier_id'); // Foreign key to core4_masterlist.suppliers
                $table->date('date');
                $table->string('reference_number')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('Open'); // Open, Completed, Cancelled
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('purchase')->hasTable('purchase_note_items')) {
            // Purchase Note Items Table (Normalized)
            Schema::connection('purchase')->create('purchase_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_note_id');
                $table->unsignedBigInteger('product_id'); // Foreign key to core4_masterlist.products
                $table->string('product_code');
                $table->string('part_number')->nullable();
                $table->string('description');
                $table->integer('quantity');
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_price', 15, 2);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->dropIfExists('purchase_note_items');
        Schema::connection('purchase')->dropIfExists('purchase_notes');
    }
};
