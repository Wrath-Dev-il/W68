<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('accounting')->hasTable('payable_cheque_voucher_sudden_returns')) {
            Schema::connection('accounting')->create('payable_cheque_voucher_sudden_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payable_cheque_voucher_id');
                $table->unsignedBigInteger('po_id')->nullable();
                $table->string('return_number');
                $table->string('po_number')->nullable();
                $table->date('date')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index('payable_cheque_voucher_id');
                $table->index('po_id');
                $table->index('return_number');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('accounting')->dropIfExists('payable_cheque_voucher_sudden_returns');
    }
};
