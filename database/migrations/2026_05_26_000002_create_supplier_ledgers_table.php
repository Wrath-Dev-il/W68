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
        if (!Schema::connection('ledger')->hasTable('supplier_ledgers')) {
            Schema::connection('ledger')->create('supplier_ledgers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id'); // Association to masterlist.suppliers
                $table->date('date');
                $table->string('transaction_code'); // Trans# or Receiving No#
                $table->string('module_type'); // Purchase Note, Payment, etc.
                $table->string('title'); // Description of the transaction
                $table->decimal('credit_amount', 15, 2)->default(0);
                $table->decimal('debit_amount', 15, 2)->default(0);
                $table->string('reference_no')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('ledger')->dropIfExists('supplier_ledgers');
    }
};
