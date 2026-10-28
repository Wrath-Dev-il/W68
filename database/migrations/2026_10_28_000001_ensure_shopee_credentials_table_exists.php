<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs production installations where the original Shopee credentials
 * migration was marked as complete but the table is missing.
 *
 * This migration is deliberately safe to run when the table already exists.
 * Its down() method does not drop credentials, because this is a repair
 * migration and the table may have been created by the original migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql');

        if ($schema->hasTable('shopee_credentials')) {
            return;
        }

        $schema->create('shopee_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('shop_id', 64)->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('access_token_expires_at')->nullable()->index();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->boolean('reauthorization_required')->default(false)->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Intentionally preserve the credentials table and stored tokens.
    }
};
