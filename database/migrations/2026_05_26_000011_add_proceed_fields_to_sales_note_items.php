<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sales')->table('sales_note_items', function (Blueprint $table) {
            if (!Schema::connection('sales')->hasColumn('sales_note_items', 'additional_qty')) {
                $table->integer('additional_qty')->default(0)->after('quantity');
            }
            if (!Schema::connection('sales')->hasColumn('sales_note_items', 'particulars')) {
                $table->string('particulars')->nullable()->after('subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_note_items', function (Blueprint $table) {
            $table->dropColumn(['additional_qty', 'particulars']);
        });
    }
};
