<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('ledger')->table('product_ledgers', function (Blueprint $table) {
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('customer_id');
            }
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'source_item_id')) {
                $table->unsignedBigInteger('source_item_id')->nullable()->after('source_id');
            }
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'processed_actual_qty')) {
                $table->decimal('processed_actual_qty', 18, 4)->nullable()->after('source_item_id');
            }
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'idempotency_key')) {
                $table->string('idempotency_key', 191)->nullable()->after('processed_actual_qty');
                $table->unique('idempotency_key', 'idx_pl_idempotency');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('ledger')->table('product_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_pl_idempotency');
            $table->dropColumn([
                'source_type',
                'source_id',
                'source_item_id',
                'processed_actual_qty',
                'idempotency_key',
            ]);
        });
    }
};
