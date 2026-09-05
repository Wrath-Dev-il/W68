<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('login_id')->nullable()->index();
            $table->string('user_id')->index();
            $table->unsignedTinyInteger('account_type')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at')->index();
            $table->timestamps();

            $table->foreign('login_id')
                ->references('login_ID')
                ->on('logins')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activity_logs');
    }
};
