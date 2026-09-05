<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('purchase')->hasTable('viber_lists')) {
            Schema::connection('purchase')->create('viber_lists', function (Blueprint $table) {
                $table->id();
                $table->string('status'); // Entry, To Shipped, Arrived, Not Arrived, Partial
                $table->unsignedBigInteger('supplier_id');
                $table->string('supplier_code');
                $table->string('supplier_name');
                $table->string('contact_no')->nullable();
                $table->string('contact_person')->nullable();
                $table->text('billing_address')->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::connection('purchase')->hasTable('viber_list_items')) {
            Schema::connection('purchase')->create('viber_list_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('viber_list_id');
                $table->unsignedBigInteger('product_id');
                $table->string('item_code');
                $table->string('part_no')->nullable();
                $table->string('description');
                $table->string('application')->nullable();
                $table->decimal('last_cost', 15, 2)->default(0);
                $table->decimal('new_cost', 15, 2)->default(0);
                $table->decimal('order_qty', 15, 2)->default(0);
                $table->date('ordered_date')->nullable();
                $table->text('remarks')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('purchase')->dropIfExists('viber_list_items');
        Schema::connection('purchase')->dropIfExists('viber_lists');
    }
};
