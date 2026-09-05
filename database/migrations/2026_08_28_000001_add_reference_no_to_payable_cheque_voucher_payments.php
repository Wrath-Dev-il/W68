<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_payments', 'reference_no')) {
            Schema::connection('accounting')->table('payable_cheque_voucher_payments', function (Blueprint $table) {
                $table->string('reference_no', 255)->nullable()->after('payment_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_payments', 'reference_no')) {
            Schema::connection('accounting')->table('payable_cheque_voucher_payments', function (Blueprint $table) {
                $table->dropColumn('reference_no');
            });
        }
    }
};
