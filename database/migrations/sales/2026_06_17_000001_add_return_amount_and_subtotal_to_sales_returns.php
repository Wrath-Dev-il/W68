<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sales')->hasTable('sales_return_items') && !Schema::connection('sales')->hasColumn('sales_return_items', 'return_amount')) {
            Schema::connection('sales')->table('sales_return_items', function (Blueprint $table) {
                $table->decimal('return_amount', 15, 2)->default(0)->after('subtotal');
            });
        }

        if (Schema::connection('sales')->hasTable('sales_returns') && !Schema::connection('sales')->hasColumn('sales_returns', 'subtotal')) {
            Schema::connection('sales')->table('sales_returns', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sales')->hasColumn('sales_return_items', 'return_amount')) {
            Schema::connection('sales')->table('sales_return_items', function (Blueprint $table) {
                $table->dropColumn('return_amount');
            });
        }
        if (Schema::connection('sales')->hasColumn('sales_returns', 'subtotal')) {
            Schema::connection('sales')->table('sales_returns', function (Blueprint $table) {
                $table->dropColumn('subtotal');
            });
        }
    }
};
