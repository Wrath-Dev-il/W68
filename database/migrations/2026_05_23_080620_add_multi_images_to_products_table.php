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
            if (!Schema::connection('masterlist')->hasColumn('products', 'position')) {
                $table->string('position')->nullable()->after('application');
            }
            if (!Schema::connection('masterlist')->hasColumn('products', 'images')) {
                $table->longText('images')->nullable()->after('Product_Picture');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn(['position', 'images']);
        });
    }
};
