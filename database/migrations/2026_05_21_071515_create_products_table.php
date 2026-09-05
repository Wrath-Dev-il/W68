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
        if (Schema::connection('masterlist')->hasTable('products')) {
            return;
        }
        Schema::connection('masterlist')->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('part_number');
            $table->string('category');
            $table->text('description')->nullable();
            $table->date('date_added');
            $table->string('application')->nullable();
            $table->integer('on_hand')->default(0);
            $table->string('status')->default('Newly');
            $table->decimal('selling_price', 15, 2)->default(0.00);
            $table->decimal('cost', 15, 2)->default(0.00);
            $table->binary('Product_Picture')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->dropIfExists('products');
    }
};
