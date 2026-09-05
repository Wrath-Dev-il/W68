<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sales')->hasTable('sales_returns') && !Schema::connection('sales')->hasColumn('sales_returns', 'additional_discount_percent')) {
            Schema::connection('sales')->table('sales_returns', function (Blueprint $table) {
                $table->decimal('additional_discount_percent', 5, 2)->default(0)->after('subtotal');
                $table->decimal('additional_discount_amount', 15, 2)->default(0)->after('additional_discount_percent');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sales')->hasColumn('sales_returns', 'additional_discount_percent')) {
            Schema::connection('sales')->table('sales_returns', function (Blueprint $table) {
                $table->dropColumn(['additional_discount_percent', 'additional_discount_amount']);
            });
        }
    }
};
