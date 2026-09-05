<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('viber_list_items', 'is_rollback')) {
                $table->boolean('is_rollback')->default(false)->after('shipped_at');
            }
            if (!Schema::connection('purchase')->hasColumn('viber_list_items', 'rollback_source_item_id')) {
                $table->unsignedBigInteger('rollback_source_item_id')->nullable()->after('is_rollback');
            }
            if (!Schema::connection('purchase')->hasColumn('viber_list_items', 'rollbacked_at')) {
                $table->timestamp('rollbacked_at')->nullable()->after('rollback_source_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            if (Schema::connection('purchase')->hasColumn('viber_list_items', 'rollbacked_at')) {
                $table->dropColumn('rollbacked_at');
            }
            if (Schema::connection('purchase')->hasColumn('viber_list_items', 'rollback_source_item_id')) {
                $table->dropColumn('rollback_source_item_id');
            }
            if (Schema::connection('purchase')->hasColumn('viber_list_items', 'is_rollback')) {
                $table->dropColumn('is_rollback');
            }
        });
    }
};
