<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::connection('masterlist')->hasTable('forwarders')
            && !Schema::connection('masterlist')->hasColumn('forwarders', 'forwarder_type')
        ) {
            Schema::connection('masterlist')->table('forwarders', function (Blueprint $table) {
                $table->string('forwarder_type', 20)
                    ->nullable()
                    ->after('name');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::connection('masterlist')->hasTable('forwarders')
            && Schema::connection('masterlist')->hasColumn('forwarders', 'forwarder_type')
        ) {
            Schema::connection('masterlist')->table('forwarders', function (Blueprint $table) {
                $table->dropColumn('forwarder_type');
            });
        }
    }
};
