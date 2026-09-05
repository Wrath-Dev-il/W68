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
            if (!Schema::connection('purchase')->hasColumn('purchase_orders', 'supplier_invoice_number')) {
                $table->string('supplier_invoice_number')->nullable()->after('po_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('supplier_invoice_number');
        });
    }
};
