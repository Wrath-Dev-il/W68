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
        Schema::create('logins', function (Blueprint $table) {
            $table->id('login_ID');
            $table->integer('account_type')->default(3); // 1=admin, 2=special account, 3=regular users
            $table->string('User_ID')->unique();
            $table->string('Password');
            $table->string('User_First_Name');
            $table->string('User_Middle_Name')->nullable();
            $table->string('User_Last_Name');
            $table->string('Gender');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logins');
    }
};
