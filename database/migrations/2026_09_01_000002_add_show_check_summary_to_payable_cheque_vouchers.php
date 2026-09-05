<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'show_check_summary')) {
            Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
                $table->boolean('show_check_summary')->default(false)->after('payment_method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'show_check_summary')) {
            Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
                $table->dropColumn('show_check_summary');
            });
        }
    }
};
