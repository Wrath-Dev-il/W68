<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::connection('masterlist')->hasTable('customer_types')) {
            Schema::connection('masterlist')->create('customer_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });

            DB::connection('masterlist')->table('customer_types')->insert([
                ['name' => 'Regular', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'B2B Customer', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Casual', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (!Schema::connection('masterlist')->hasTable('customers')) {
            Schema::connection('masterlist')->create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_type_id')->constrained('customer_types')->cascadeOnUpdate()->restrictOnDelete();
                $table->string('name');
                $table->string('contact_number')->nullable();
                $table->string('contact_person')->nullable();
                $table->text('address')->nullable();
                $table->string('tin')->nullable();
                $table->text('pricing_remarks')->nullable();
                $table->timestamps();

                $table->index('customer_type_id');
                $table->index('name');
            });
        }

        if (!Schema::connection('masterlist')->hasTable('customer_bank_accounts')) {
            Schema::connection('masterlist')->create('customer_bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->cascadeOnDelete();
                $table->string('bank_code')->nullable();
                $table->string('account_number')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_contact_number')->nullable();
                $table->string('bank_contact_person')->nullable();
                $table->text('bank_address')->nullable();
                $table->timestamps();

                $table->index('customer_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->dropIfExists('customer_bank_accounts');
        Schema::connection('masterlist')->dropIfExists('customers');
        Schema::connection('masterlist')->dropIfExists('customer_types');
    }
};
