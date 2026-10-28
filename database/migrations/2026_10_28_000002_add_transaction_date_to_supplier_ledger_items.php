<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure supplier ledger detail rows have an explicit transaction date.
 * Existing dates are preserved; null dates are initialized from their parent
 * supplier ledger so the detail row matches the transaction header.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('ledger');

        if (!$schema->hasTable('supplier_ledger_items')) {
            // This table may be provisioned separately on some W68 installs.
            // The route-level sync also checks the schema before updating it.
            return;
        }

        if (!$schema->hasColumn('supplier_ledger_items', 'date')) {
            $schema->table('supplier_ledger_items', function (Blueprint $table): void {
                $table->date('date')->nullable();
            });
        }

        if (
            $schema->hasTable('supplier_ledgers')
            && $schema->hasColumn('supplier_ledger_items', 'supplier_ledger_id')
            && $schema->hasColumn('supplier_ledger_items', 'date')
            && $schema->hasColumn('supplier_ledgers', 'date')
            && $schema->hasColumn('supplier_ledgers', 'module_type')
        ) {
            DB::connection('ledger')
                ->table('supplier_ledger_items as sli')
                ->join('supplier_ledgers as sl', 'sl.id', '=', 'sli.supplier_ledger_id')
                ->where('sl.module_type', 'Purchase Order')
                ->whereNull('sli.date')
                ->update(['sli.date' => DB::raw('sl.date')]);
        }
    }

    public function down(): void
    {
        // Intentionally preserve the transaction-date column and historical data.
    }
};
