<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('logins', 'profile_picture_mime')) {
            Schema::table('logins', function (Blueprint $table) {
                $table->string('profile_picture_mime')->nullable()->after('Gender');
            });
        }

        if (!Schema::hasColumn('logins', 'profile_picture')) {
            DB::statement('ALTER TABLE `logins` ADD COLUMN `profile_picture` LONGBLOB NULL AFTER `profile_picture_mime`');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('logins', 'profile_picture')) {
            DB::statement('ALTER TABLE `logins` DROP COLUMN `profile_picture`');
        }

        if (Schema::hasColumn('logins', 'profile_picture_mime')) {
            Schema::table('logins', function (Blueprint $table) {
                $table->dropColumn('profile_picture_mime');
            });
        }
    }
};
