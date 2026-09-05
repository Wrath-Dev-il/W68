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
        if (!Schema::connection('masterlist')->hasTable('forwarders')) {
            Schema::connection('masterlist')->create('forwarders', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::connection('masterlist')->hasTable('forwarder_contacts')) {
            Schema::connection('masterlist')->create('forwarder_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forwarder_id')->constrained('forwarders')->cascadeOnUpdate()->cascadeOnDelete();
                $table->string('contact_number')->nullable();
                $table->string('contact_person')->nullable();
                $table->timestamps();

                $table->index('forwarder_id');
            });
        }

        if (!Schema::connection('masterlist')->hasTable('forwarder_addresses')) {
            Schema::connection('masterlist')->create('forwarder_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('forwarder_id')->constrained('forwarders')->cascadeOnUpdate()->cascadeOnDelete();
                $table->text('address')->nullable();
                $table->timestamps();

                $table->index('forwarder_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->dropIfExists('forwarder_addresses');
        Schema::connection('masterlist')->dropIfExists('forwarder_contacts');
        Schema::connection('masterlist')->dropIfExists('forwarders');
    }
};
