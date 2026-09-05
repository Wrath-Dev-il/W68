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
        Schema::table('logins', function (Blueprint $table) {
            if (!Schema::hasColumn('logins', 'Email')) {
                $table->string('Email')->nullable()->after('Gender');
            }

            if (!Schema::hasColumn('logins', 'Otp_Code')) {
                $table->string('Otp_Code')->nullable()->after('Email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('logins', function (Blueprint $table) {
            if (Schema::hasColumn('logins', 'Otp_Code')) {
                $table->dropColumn('Otp_Code');
            }

            if (Schema::hasColumn('logins', 'Email')) {
                $table->dropColumn('Email');
            }
        });
    }
};
