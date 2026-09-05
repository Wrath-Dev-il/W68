<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->integer('actual_qty')->nullable()->default(null)->after('on_hand');
        });
    }

    public function down(): void
    {
        Schema::connection('masterlist')->table('products', function (Blueprint $table) {
            $table->dropColumn('actual_qty');
        });
    }
};
