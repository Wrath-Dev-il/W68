<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('masterlist')->hasTable('customer_description_discount')) {
            Schema::connection('masterlist')->create('customer_description_discount', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('description', 255);
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['customer_id', 'description'], 'customer_desc_discount_unique');
                $table->index('description');
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::connection('masterlist')->hasTable('customer_product_discount')) {
            Schema::connection('masterlist')->create('customer_product_discount', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_code', 255);
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['customer_id', 'product_code'], 'customer_prod_code_discount_unique');
                $table->index('product_id');
                $table->index('product_code');
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('masterlist')->dropIfExists('customer_product_discount');
        Schema::connection('masterlist')->dropIfExists('customer_description_discount');
    }
};

