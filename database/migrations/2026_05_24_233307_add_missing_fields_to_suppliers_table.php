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
            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'telefax')) {
                $table->string('telefax')->nullable()->after('tin');
            }
            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }
            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::connection('masterlist')->hasColumn('suppliers', 'supplier_picture')) {
                $table->longText('supplier_picture')->nullable()->after('end_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('masterlist')->table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['telefax', 'start_date', 'end_date', 'supplier_picture']);
        });
    }
};
