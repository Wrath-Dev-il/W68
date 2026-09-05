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
        Schema::connection('ledger')->table('product_ledgers', function (Blueprint $table) {
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('product_id');
            }
            if (!Schema::connection('ledger')->hasColumn('product_ledgers', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('supplier_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('ledger')->table('product_ledgers', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'customer_id']);
        });
    }
};
