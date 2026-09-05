<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('viber_list_items', 'purchase_note_id')) {
                $table->unsignedBigInteger('purchase_note_id')->nullable()->after('status');
                $table->timestamp('shipped_at')->nullable()->after('purchase_note_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_note_id', 'shipped_at']);
        });
    }
};
