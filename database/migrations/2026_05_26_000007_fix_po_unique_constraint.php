<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            // Remove the unique constraint from po_number
            $table->dropUnique(['po_number']);
            
            // Add a dedicated column for the unique Transaction/Receiving Number if it doesn't exist
            if (!Schema::connection('purchase')->hasColumn('purchase_orders', 'receiving_number')) {
                $table->string('receiving_number')->unique()->nullable()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->unique('po_number');
            $table->dropColumn('receiving_number');
        });
    }
};
