<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('ledger')->hasTable('audit_trails')) {
            Schema::connection('ledger')->create('audit_trails', function (Blueprint $table) {
                $table->id();
                $table->string('module')->index();
                $table->string('action')->index();
                $table->string('source_connection')->nullable();
                $table->string('source_table')->nullable();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('display_id')->nullable()->index();
                $table->string('record_name')->nullable()->index();
                $table->string('user_name')->nullable()->index();
                $table->string('user_identifier')->nullable()->index();
                $table->longText('audit_data')->nullable();
                $table->timestamps();

                $table->index(['module', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('ledger')->dropIfExists('audit_trails');
    }
};
