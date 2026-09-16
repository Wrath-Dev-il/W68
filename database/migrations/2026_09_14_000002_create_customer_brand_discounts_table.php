<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('masterlist')->hasTable('customer_brand_discounts')) {
            Schema::connection('masterlist')->create('customer_brand_discounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->string('brand');
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->timestamps();

                $table->unique(['customer_id', 'brand'], 'customer_brand_discounts_customer_brand_unique');
                $table->index('brand');
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
        Schema::connection('masterlist')->dropIfExists('customer_brand_discounts');
    }
};
