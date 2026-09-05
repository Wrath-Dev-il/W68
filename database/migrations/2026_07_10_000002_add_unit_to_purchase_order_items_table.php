<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('purchase_order_items', 'unit')) {
                $table->string('unit', 50)->nullable()->after('product_code');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
    }
};
