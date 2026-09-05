<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_notes', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'is_force_closed')) {
                $table->boolean('is_force_closed')->default(false)->after('status');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'force_closed_at')) {
                $table->timestamp('force_closed_at')->nullable()->after('is_force_closed');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'force_closed_by')) {
                $table->string('force_closed_by')->nullable()->after('force_closed_at');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'force_close_reason')) {
                $table->text('force_close_reason')->nullable()->after('force_closed_by');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'force_close_reference')) {
                $table->string('force_close_reference')->nullable()->after('force_close_reason');
            }
        });

        Schema::connection('purchase')->table('purchase_note_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'force_closed_remaining_qty')) {
                $table->integer('force_closed_remaining_qty')->default(0)->after('total_price');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'is_remaining_cancelled')) {
                $table->boolean('is_remaining_cancelled')->default(false)->after('force_closed_remaining_qty');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'remaining_cancelled_at')) {
                $table->timestamp('remaining_cancelled_at')->nullable()->after('is_remaining_cancelled');
            }
        });

        if (!Schema::connection('purchase')->hasTable('purchase_note_force_close_passcodes')) {
            Schema::connection('purchase')->create('purchase_note_force_close_passcodes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_note_id');
                $table->string('user_identifier');
                $table->string('passcode_hash');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['purchase_note_id', 'user_identifier', 'used_at'], 'pn_force_close_user_used_idx');
                $table->index('expires_at', 'pn_force_close_expires_idx');
            });
        }

        if (!Schema::connection('purchase')->hasTable('purchase_note_force_close_logs')) {
            Schema::connection('purchase')->create('purchase_note_force_close_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_note_id');
                $table->string('purchase_note_number');
                $table->string('previous_status');
                $table->string('new_status');
                $table->string('user_id')->nullable();
                $table->string('force_close_reference');
                $table->integer('processed_item_count')->default(0);
                $table->integer('removed_item_count')->default(0);
                $table->decimal('processed_quantity', 15, 4)->default(0);
                $table->decimal('cancelled_remaining_quantity', 15, 4)->default(0);
                $table->longText('affected_items_json')->nullable();
                $table->text('reason');
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('purchase')->dropIfExists('purchase_note_force_close_logs');
        Schema::connection('purchase')->dropIfExists('purchase_note_force_close_passcodes');

        Schema::connection('purchase')->table('purchase_note_items', function (Blueprint $table) {
            foreach (['remaining_cancelled_at', 'is_remaining_cancelled', 'force_closed_remaining_qty'] as $column) {
                if (Schema::connection('purchase')->hasColumn('purchase_note_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::connection('purchase')->table('purchase_notes', function (Blueprint $table) {
            foreach (['force_close_reference', 'force_close_reason', 'force_closed_by', 'force_closed_at', 'is_force_closed'] as $column) {
                if (Schema::connection('purchase')->hasColumn('purchase_notes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
