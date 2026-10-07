<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql')->hasTable('shopee_credentials')) {
            return;
        }

        Schema::connection('mysql')->create('shopee_credentials', function (Blueprint $table) {
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
        Schema::connection('mysql')->dropIfExists('shopee_credentials');
    }
};
