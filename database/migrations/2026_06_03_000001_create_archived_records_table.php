<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('ledger')->hasTable('archived_records')) {
            Schema::connection('ledger')->create('archived_records', function (Blueprint $table) {
                $table->id();
                $table->string('module')->index();
                $table->string('source_connection')->nullable();
                $table->string('source_table')->nullable();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->string('display_id')->nullable()->index();
                $table->string('data_name')->nullable();
                $table->longText('archived_data');
                $table->string('deleted_by')->nullable();
                $table->timestamp('deleted_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('restored_at')->nullable()->index();
                $table->string('restored_by')->nullable();
                $table->timestamps();

                $table->index(['module', 'restored_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('ledger')->dropIfExists('archived_records');
    }
};
