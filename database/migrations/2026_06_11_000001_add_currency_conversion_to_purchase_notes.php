<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_notes', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'currency')) {
                $table->string('currency', 10)->nullable()->after('remarks');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'conversion_rate')) {
                $table->decimal('conversion_rate', 15, 6)->nullable()->after('currency');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_notes', 'total_amount_converted')) {
                $table->decimal('total_amount_converted', 15, 2)->nullable()->after('conversion_rate');
            }
        });

        Schema::connection('purchase')->table('purchase_note_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'currency')) {
                $table->string('currency', 10)->nullable()->after('description');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'conversion_rate')) {
                $table->decimal('conversion_rate', 15, 6)->nullable()->after('currency');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'unit_price_converted')) {
                $table->decimal('unit_price_converted', 15, 2)->nullable()->after('conversion_rate');
            }
            if (!Schema::connection('purchase')->hasColumn('purchase_note_items', 'total_price_converted')) {
                $table->decimal('total_price_converted', 15, 2)->nullable()->after('unit_price_converted');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_notes', function (Blueprint $table) {
            if (Schema::connection('purchase')->hasColumn('purchase_notes', 'total_amount_converted')) {
                $table->dropColumn('total_amount_converted');
            }
            if (Schema::connection('purchase')->hasColumn('purchase_notes', 'conversion_rate')) {
                $table->dropColumn('conversion_rate');
            }
            if (Schema::connection('purchase')->hasColumn('purchase_notes', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::connection('purchase')->table('purchase_note_items', function (Blueprint $table) {
            if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'total_price_converted')) {
                $table->dropColumn('total_price_converted');
            }
            if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'unit_price_converted')) {
                $table->dropColumn('unit_price_converted');
            }
            if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'conversion_rate')) {
                $table->dropColumn('conversion_rate');
            }
            if (Schema::connection('purchase')->hasColumn('purchase_note_items', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};