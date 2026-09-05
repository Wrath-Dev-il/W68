<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('accounting');
        $schema = $connection->getSchemaBuilder();
        $table = 'payable_cheque_vouchers';

        if ($schema->hasTable($table)) {
            if (!$schema->hasColumn($table, 'additional_discount')) {
                $connection->statement("ALTER TABLE `{$table}` ADD COLUMN `additional_discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `global_discount_amount`");
            }
            if (!$schema->hasColumn($table, 'additional_discount_amount')) {
                $connection->statement("ALTER TABLE `{$table}` ADD COLUMN `additional_discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `additional_discount`");
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('accounting');
        $schema = $connection->getSchemaBuilder();
        $table = 'payable_cheque_vouchers';

        if ($schema->hasTable($table)) {
            if ($schema->hasColumn($table, 'additional_discount_amount')) {
                $connection->statement("ALTER TABLE `{$table}` DROP COLUMN `additional_discount_amount`");
            }
            if ($schema->hasColumn($table, 'additional_discount')) {
                $connection->statement("ALTER TABLE `{$table}` DROP COLUMN `additional_discount`");
            }
        }
    }
};
