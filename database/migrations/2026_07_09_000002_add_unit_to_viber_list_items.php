<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            if (!Schema::connection('purchase')->hasColumn('viber_list_items', 'unit')) {
                $table->string('unit', 50)->nullable()->after('application');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('purchase')->table('viber_list_items', function (Blueprint $table) {
            if (Schema::connection('purchase')->hasColumn('viber_list_items', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
