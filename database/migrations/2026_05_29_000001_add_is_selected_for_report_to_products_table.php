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
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (!Schema::connection('masterlist')->hasColumn('products', 'is_selected_for_report')) {
                $table->boolean('is_selected_for_report')->default(false)->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn('is_selected_for_report');
        });
    }
};
