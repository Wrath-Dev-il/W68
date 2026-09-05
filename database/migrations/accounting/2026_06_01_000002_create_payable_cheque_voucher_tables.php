<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('payable_cheque_vouchers')) {
            Schema::connection('accounting')->create('payable_cheque_vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('voucher_no')->unique();
                $table->unsignedBigInteger('supplier_id');
                $table->string('supplier_name');
                $table->date('voucher_date');
                $table->string('reference_no')->nullable();
                $table->text('particulars')->nullable();
                $table->string('payment_method')->default('Gcash');
                $table->decimal('total_paid', 15, 2)->default(0);
                $table->string('status')->default('Posted');
                $table->timestamps();

                $table->index('supplier_id');
                $table->index('voucher_date');
            });
        }

        if (!Schema::connection('accounting')->hasTable('payable_cheque_voucher_invoices')) {
            Schema::connection('accounting')->create('payable_cheque_voucher_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payable_cheque_voucher_id');
                $table->unsignedBigInteger('purchase_order_id')->nullable();
                $table->string('purchase_no');
                $table->string('invoice_no');
                $table->decimal('invoice_amount', 15, 2)->default(0);
                $table->decimal('amount_due', 15, 2)->default(0);
                $table->decimal('amount_paid', 15, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->string('payment_status')->default('Partial');
                $table->timestamps();

                $table->index('payable_cheque_voucher_id');
                $table->index('purchase_order_id');
                $table->index('invoice_no');
            });
        }

        if (!Schema::connection('accounting')->hasTable('payable_cheque_voucher_payments')) {
            Schema::connection('accounting')->create('payable_cheque_voucher_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payable_cheque_voucher_id');
                $table->string('payment_method')->default('Gcash');
                $table->string('account_no')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('check_no')->nullable();
                $table->date('check_date')->nullable();
                $table->date('payment_date')->nullable();
                $table->decimal('credit_amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index('payable_cheque_voucher_id');
                $table->index('payment_method');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('accounting')->dropIfExists('payable_cheque_voucher_payments');
        Schema::connection('accounting')->dropIfExists('payable_cheque_voucher_invoices');
        Schema::connection('accounting')->dropIfExists('payable_cheque_vouchers');
    }
};
