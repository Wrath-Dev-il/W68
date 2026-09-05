<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('PHP')->after('remarks');
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
