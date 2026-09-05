<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('consignment_invoices')) {
            Schema::connection('sales')->create('consignment_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sales_note_id')->nullable();
                $table->string('invoice_number')->unique();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status')->default('Open');
                $table->string('waybill_no')->nullable();
                $table->date('waybill_date')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('consignment_invoices');
    }
};
