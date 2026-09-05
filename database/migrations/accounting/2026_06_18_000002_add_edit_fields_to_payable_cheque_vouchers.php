<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount')) {
                $table->decimal('global_discount', 8, 2)->default(0)->after('total_paid');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'global_discount_amount')) {
                $table->decimal('global_discount_amount', 15, 2)->default(0)->after('global_discount');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'edited_by')) {
                $table->unsignedBigInteger('edited_by')->nullable()->after('status');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('edited_by');
            }
        });

        Schema::connection('accounting')->table('payable_cheque_voucher_invoices', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_1')) {
                $table->decimal('discount_1', 8, 2)->default(0)->after('amount_due');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'discount_2')) {
                $table->decimal('discount_2', 8, 2)->default(0)->after('discount_1');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_amount')) {
                $table->decimal('return_amount', 15, 2)->default(0)->after('amount_paid');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'total_returns')) {
                $table->integer('total_returns')->default(0)->after('return_amount');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'return_number')) {
                $table->string('return_number')->nullable()->after('total_returns');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_invoices', 'rs_details')) {
                $table->text('rs_details')->nullable()->after('return_number');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            $table->dropColumn(['global_discount', 'global_discount_amount', 'edited_by', 'edited_at']);
        });

        Schema::connection('accounting')->table('payable_cheque_voucher_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_1', 'discount_2', 'return_amount', 'total_returns', 'return_number', 'rs_details']);
        });
    }
};
