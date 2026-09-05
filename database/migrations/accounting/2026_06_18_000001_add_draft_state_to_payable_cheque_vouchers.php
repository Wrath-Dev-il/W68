<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'draft_state')) {
                $table->text('draft_state')->nullable()->after('status');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'is_draft')) {
                $table->boolean('is_draft')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('accounting')->table('payable_cheque_vouchers', function (Blueprint $table) {
            if (Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'draft_state')) {
                $table->dropColumn('draft_state');
            }
            if (Schema::connection('accounting')->hasColumn('payable_cheque_vouchers', 'is_draft')) {
                $table->dropColumn('is_draft');
            }
        });
    }
};
