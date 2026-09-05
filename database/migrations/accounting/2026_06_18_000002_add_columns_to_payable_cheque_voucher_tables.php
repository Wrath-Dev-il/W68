<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns to payable_cheque_voucher_invoices table
        Schema::connection('accounting')->table('payable_cheque_voucher_invoices', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_1')) {
                $table->decimal('discount_1', 5, 2)->default(0.00)->after('amount_paid');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_2')) {
                $table->decimal('discount_2', 5, 2)->default(0.00)->after('discount_1');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_amount')) {
                $table->decimal('return_amount', 15, 2)->default(0.00)->after('discount_2');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'total_returns')) {
                $table->integer('total_returns')->default(0)->after('return_amount');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_number')) {
                $table->text('return_number')->nullable()->after('total_returns');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'rs_details')) {
                $table->text('rs_details')->nullable()->after('return_number');
            }
        });

        // Add columns to payable_cheque_vouchers table
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount')) {
                $table->decimal('global_discount', 5, 2)->default(0.00)->after('total_paid');
            }
            
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount_amount')) {
                $table->decimal('global_discount_amount', 15, 2)->default(0.00)->after('global_discount');
            }
        });
    }

    public function down(): void
    {
        // Remove columns from payable_cheque_voucher_invoices table
        Schema::connection('accounting')->table('payable_cheque_voucher_invoices', function (Blueprint $table) {
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'rs_details')) {
                $table->dropColumn('rs_details');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_number')) {
                $table->dropColumn('return_number');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'total_returns')) {
                $table->dropColumn('total_returns');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_amount')) {
                $table->dropColumn('return_amount');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_2')) {
                $table->dropColumn('discount_2');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_1')) {
                $table->dropColumn('discount_1');
            }
        });

        // Remove columns from payable_cheque_vouchers table
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            if (Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount_amount')) {
                $table->dropColumn('global_discount_amount');
            }
            
            if (Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount')) {
                $table->dropColumn('global_discount');
            }
        });
    }
};
