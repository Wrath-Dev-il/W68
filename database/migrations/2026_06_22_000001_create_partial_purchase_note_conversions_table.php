<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ledger')->create('partial_purchase_note_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_purchase_note_id')->comment('Original partial purchase note');
            $table->unsignedBigInteger('source_purchase_note_item_id')->comment('Original purchase note item');
            $table->unsignedBigInteger('new_purchase_note_id')->comment('New purchase note receiving transferred items');
            $table->unsignedBigInteger('new_purchase_note_item_id')->comment('New purchase note item from transfer');
            $table->unsignedBigInteger('product_ledger_id')->nullable()->comment('Product ledger entry if created');
            $table->unsignedBigInteger('supplier_id');
            $table->integer('transferred_quantity')->comment('Quantity transferred from remaining');
            $table->decimal('transferred_amount', 15, 2)->default(0)->comment('Amount for transferred quantity');
            $table->string('status', 50)->default('active');
            $table->timestamps();

            $table->index('source_purchase_note_id');
            $table->index('new_purchase_note_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::connection('ledger')->dropIfExists('partial_purchase_note_conversions');
    }
};
