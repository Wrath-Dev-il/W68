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
        Schema::connection('masterlist')->table('suppliers', function (Blueprint $table) {
            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'record_type')) {
                $table->string('record_type')->default('supplier')->after('id');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'customer_type')) {
                $table->string('customer_type')->nullable()->after('status');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'pricing_remarks')) {
                $table->text('pricing_remarks')->nullable()->after('customer_type');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_code')) {
                $table->string('bank_code')->nullable()->after('pricing_remarks');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_code');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bank_account_number');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_contact_number')) {
                $table->string('bank_contact_number')->nullable()->after('bank_name');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_contact_person')) {
                $table->string('bank_contact_person')->nullable()->after('bank_contact_number');
            }

            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'bank_address')) {
                $table->text('bank_address')->nullable()->after('bank_contact_person');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'bank_address',
                'bank_contact_person',
                'bank_contact_number',
                'bank_name',
                'bank_account_number',
                'bank_code',
                'pricing_remarks',
                'customer_type',
                'record_type',
            ]);
        });
    }
};
