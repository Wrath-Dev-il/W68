<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasColumn('consignment_invoice_items', 'additional_discount')) {
            Schema::connection('sales')->table('consignment_invoice_items', function (Blueprint $table) {
                $table->decimal('additional_discount', 5, 2)->default(0)->after('discount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sales')->hasColumn('consignment_invoice_items', 'additional_discount')) {
            Schema::connection('sales')->table('consignment_invoice_items', function (Blueprint $table) {
                $table->dropColumn('additional_discount');
            });
        }
    }
};
