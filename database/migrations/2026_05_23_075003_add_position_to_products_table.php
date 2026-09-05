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
        if (Schema::connection('masterlist')->hasColumn('products', 'position')) {
            return;
        }
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->string('position')->nullable()->after('application');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
