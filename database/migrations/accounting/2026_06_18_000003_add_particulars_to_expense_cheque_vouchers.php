<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('expense_cheque_vouchers')) {
            return;
        }

        if (Schema::connection('accounting')->hasColumn('expense_cheque_vouchers', 'particulars')) {
            return;
        }

        Schema::connection('accounting')->table('expense_cheque_vouchers', function (Blueprint $table) {
            $table->text('particulars')->nullable()->after('ref');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('accounting')->hasTable('expense_cheque_vouchers')) {
            return;
        }

        if (!Schema::connection('accounting')->hasColumn('expense_cheque_vouchers', 'particulars')) {
            return;
        }

        Schema::connection('accounting')->table('expense_cheque_vouchers', function (Blueprint $table) {
            $table->dropColumn('particulars');
        });
    }
};
