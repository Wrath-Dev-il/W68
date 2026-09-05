<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('payment_invoice_groups')) {
            Schema::connection('accounting')->create('payment_invoice_groups', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('created_by')->nullable();
                $table->date('grouped_date');
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index('status');
                $table->index('grouped_date');
            });
        }

        if (!Schema::connection('accounting')->hasTable('payment_invoice_group_items')) {
            Schema::connection('accounting')->create('payment_invoice_group_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_invoice_group_id');
                $table->string('source_type');
                $table->unsignedBigInteger('invoice_id');
                $table->string('invoice_no');
                $table->timestamps();

                $table->foreign('payment_invoice_group_id')
                    ->references('id')
                    ->on('payment_invoice_groups')
                    ->onDelete('cascade');

                $table->unique(['payment_invoice_group_id', 'source_type', 'invoice_id'], 'uk_group_invoice');
                $table->index('invoice_no');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('accounting')->dropIfExists('payment_invoice_group_items');
        Schema::connection('accounting')->dropIfExists('payment_invoice_groups');
    }
};
