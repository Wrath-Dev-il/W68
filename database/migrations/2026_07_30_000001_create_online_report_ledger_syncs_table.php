<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sales')->create('online_report_product_ledger_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->string('status', 50)->default('pending'); // pending, processing, completed, failed
            $table->integer('items_processed')->default(0);
            $table->integer('items_synced')->default(0);
            $table->integer('items_failed')->default(0);
            $table->text('errors')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index('report_id');
        });

        Schema::connection('sales')->create('online_report_product_ledger_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('sales_order_id');
            $table->unsignedBigInteger('sales_order_item_id');
            $table->unsignedBigInteger('product_ledger_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->timestamps();
            $table->index('report_id');
            $table->index('sales_order_item_id');
            $table->index('product_ledger_id');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('online_report_product_ledger_syncs');
        Schema::connection('sales')->dropIfExists('online_report_product_ledger_links');
    }
};
