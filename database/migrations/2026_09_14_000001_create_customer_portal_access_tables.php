<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('masterlist')->hasTable('customer_portal_authorizations')) {
            Schema::connection('masterlist')->create('customer_portal_authorizations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->unique();
                $table->char('token_hash', 64)->unique();
                $table->text('token_encrypted');
                $table->unsignedInteger('validity_value');
                $table->string('validity_unit', 16);
                $table->dateTime('expires_at');
                $table->string('created_by')->nullable();
                $table->timestamps();

                $table->index('expires_at');
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::connection('mysql')->hasTable('customer_portal_accounts')) {
            Schema::connection('mysql')->create('customer_portal_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->unique();
                $table->unsignedBigInteger('login_id')->unique();
                $table->unsignedBigInteger('authorization_id')->nullable();
                $table->dateTime('linked_at')->nullable();
                $table->timestamps();

                $table->index('authorization_id');
                $table->foreign('login_id')
                    ->references('login_ID')
                    ->on('logins')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('customer_portal_accounts');
        Schema::connection('masterlist')->dropIfExists('customer_portal_authorizations');
    }
};
