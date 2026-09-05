<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (!Schema::connection('masterlist')->hasColumn('products', 'unit')) {
                $table->string('unit', 50)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            if (Schema::connection('masterlist')->hasColumn('products', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }
};
