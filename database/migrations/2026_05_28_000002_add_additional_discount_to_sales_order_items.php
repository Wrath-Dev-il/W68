<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasColumn('sales_order_items', 'additional_discount')) {
            Schema::connection('sales')->table('sales_order_items', function (Blueprint $table) {
                $table->decimal('additional_discount', 5, 2)->default(0)->after('discount');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->table('sales_order_items', function (Blueprint $table) {
            $table->dropColumn('additional_discount');
        });
    }
};
