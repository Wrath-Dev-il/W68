<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sales')->table('waybills', function (Blueprint $table) {
            $table->renameColumn('waybill_number', 'waybill_sequence');
        });

        Schema::connection('sales')->table('waybills', function (Blueprint $table) {
            $table->string('waybill_no', 255)
                ->nullable()
                ->after('waybill_sequence');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->table('waybills', function (Blueprint $table) {
            $table->dropColumn('waybill_no');
        });

        Schema::connection('sales')->table('waybills', function (Blueprint $table) {
            $table->renameColumn('waybill_sequence', 'waybill_number');
        });
    }
};
