<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('masterlist')->hasTable('online_products')) {
            return;
        }
        Schema::connection('masterlist')->create('online_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 15, 2)->default(0.00);
            $table->string('category')->nullable();
            $table->text('image_url')->nullable();
            $table->boolean('is_converted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->dropIfExists('online_products');
    }
};
