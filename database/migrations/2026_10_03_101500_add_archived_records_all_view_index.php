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

        $indexes = collect(DB::connection('ledger')->select('SHOW INDEX FROM archived_records'))
            ->pluck('Key_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();

        if (!in_array('archived_active_deleted_idx', $indexes, true)) {
            Schema::connection('ledger')->table('archived_records', function (Blueprint $table) {
                $table->index(
                    ['restored_at', 'deleted_at', 'id'],
                    'archived_active_deleted_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection('ledger')->hasTable('archived_records')) {
            return;
        }

        $indexes = collect(DB::connection('ledger')->select('SHOW INDEX FROM archived_records'))
            ->pluck('Key_name')
            ->map(fn ($name) => (string) $name)
            ->unique()
            ->values()
            ->all();

        if (in_array('archived_active_deleted_idx', $indexes, true)) {
            Schema::connection('ledger')->table('archived_records', function (Blueprint $table) {
                $table->dropIndex('archived_active_deleted_idx');
            });
        }
    }
};
