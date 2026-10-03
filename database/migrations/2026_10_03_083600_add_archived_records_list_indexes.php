<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('ledger')->hasTable('archived_records')) {
            return;
        }

        $indexNames = collect(DB::connection('ledger')->select('SHOW INDEX FROM archived_records'))
            ->pluck('Key_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();

        if (!in_array('archived_active_module_deleted_idx', $indexNames, true)) {
            Schema::connection('ledger')->table('archived_records', function (Blueprint $table) {
                $table->index(
                    ['restored_at', 'module', 'deleted_at', 'id'],
                    'archived_active_module_deleted_idx'
                );
            });
        }

        if (!in_array('archived_active_expiry_idx', $indexNames, true)) {
            Schema::connection('ledger')->table('archived_records', function (Blueprint $table) {
                $table->index(
                    ['restored_at', 'expires_at'],
                    'archived_active_expiry_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection('ledger')->hasTable('archived_records')) {
            return;
        }

        $indexNames = collect(DB::connection('ledger')->select('SHOW INDEX FROM archived_records'))
            ->pluck('Key_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();

        Schema::connection('ledger')->table('archived_records', function (Blueprint $table) use ($indexNames) {
            if (in_array('archived_active_module_deleted_idx', $indexNames, true)) {
                $table->dropIndex('archived_active_module_deleted_idx');
            }
            if (in_array('archived_active_expiry_idx', $indexNames, true)) {
                $table->dropIndex('archived_active_expiry_idx');
            }
        });
    }
};
