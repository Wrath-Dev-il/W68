<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sales')->hasTable('online_reports')) {
            Schema::connection('sales')->create('online_reports', function (Blueprint $table) {
                $table->id();
                $table->text('sales_note_ids'); // comma-separated sales note IDs
                $table->string('created_by')->nullable();
                $table->json('date_ranges')->nullable();
                $table->json('prices')->nullable();
                $table->json('counter_parts')->nullable();
                $table->json('invoice_numbers')->nullable();
                $table->json('addresses')->nullable();
                $table->json('notes_data')->nullable(); // snapshot of note data at generation time
                $table->string('status')->default('generated'); // generated, printed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('online_reports');
    }
};
