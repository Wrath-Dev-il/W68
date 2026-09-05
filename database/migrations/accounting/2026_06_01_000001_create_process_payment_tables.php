<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('process_payments')) {
            Schema::connection('accounting')->create('process_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_no')->unique();
                $table->unsignedBigInteger('customer_id');
                $table->string('customer_name');
                $table->date('payment_date');
                $table->decimal('total_paid', 15, 2)->default(0);
                $table->string('status')->default('Posted');
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('accounting')->hasTable('process_payment_invoices')) {
            Schema::connection('accounting')->create('process_payment_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('process_payment_id');
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->string('invoice_no');
                $table->decimal('invoice_amount', 15, 2)->default(0);
                $table->decimal('due_amount', 15, 2)->default(0);
                $table->decimal('adjustment', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->string('payment_status')->default('Partial');
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['source_type', 'source_id']);
                $table->index('process_payment_id');
            });
        }

        if (!Schema::connection('accounting')->hasTable('process_payment_checks')) {
            Schema::connection('accounting')->create('process_payment_checks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('process_payment_id');
                $table->unsignedBigInteger('customer_bank_account_id')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('check_no')->nullable();
                $table->date('check_date')->nullable();
                $table->decimal('credit_amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index('process_payment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('accounting')->dropIfExists('process_payment_checks');
        Schema::connection('accounting')->dropIfExists('process_payment_invoices');
        Schema::connection('accounting')->dropIfExists('process_payments');
    }
};
