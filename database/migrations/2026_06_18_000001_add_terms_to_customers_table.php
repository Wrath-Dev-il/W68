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
        if (!Schema::connection('masterlist')->hasColumn('customers', 'terms')) {
            Schema::connection('masterlist')->table('customers', function (Blueprint $table) {
                $table->string('terms')->nullable()->after('pricing_remarks');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::connection('masterlist')->hasColumn('customers', 'terms')) {
            Schema::connection('masterlist')->table('customers', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }
    }
};