<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('process_payment_returns')) {
            Schema::connection('accounting')->create('process_payment_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('process_payment_id');
                $table->unsignedBigInteger('process_payment_invoice_id')->nullable();
                $table->unsignedBigInteger('sales_return_id');
                $table->string('return_number');
                $table->decimal('return_amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index('process_payment_id');
                $table->index('process_payment_invoice_id');
                $table->index('sales_return_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('accounting')->dropIfExists('process_payment_returns');
    }
};
