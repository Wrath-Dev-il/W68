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
        // Ensure doctrine/dbal is installed for column modifications
        // composer require doctrine/dbal
        Schema::table('logins', function (Blueprint $table) {
            $table->bigIncrements('login_ID')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logins', function (Blueprint $table) {
            // Revert back to a plain unsigned big integer (no auto‑increment)
            $table->unsignedBigInteger('login_ID')->change();
        });
    }
};
