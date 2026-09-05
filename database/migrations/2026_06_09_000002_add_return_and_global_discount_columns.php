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

        $vouchersTable = 'payable_cheque_vouchers';
        if ($schema->hasTable($vouchersTable)) {
            if (!$schema->hasColumn($vouchersTable, 'global_discount')) {
                $connection->statement("ALTER TABLE `{$vouchersTable}` ADD COLUMN `global_discount` DECIMAL(5,2) NOT NULL DEFAULT 0.00");
            }
            if (!$schema->hasColumn($vouchersTable, 'global_discount_amount')) {
                $connection->statement("ALTER TABLE `{$vouchersTable}` ADD COLUMN `global_discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            }
        }

        $invoicesTable = 'payable_cheque_voucher_invoices';
        if ($schema->hasTable($invoicesTable)) {
            if (!$schema->hasColumn($invoicesTable, 'return_amount')) {
                $connection->statement("ALTER TABLE `{$invoicesTable}` ADD COLUMN `return_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            }
            if (!$schema->hasColumn($invoicesTable, 'total_returns')) {
                $connection->statement("ALTER TABLE `{$invoicesTable}` ADD COLUMN `total_returns` INT NOT NULL DEFAULT 0");
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('accounting');
        $schema = $connection->getSchemaBuilder();

        $vouchersTable = 'payable_cheque_vouchers';
        if ($schema->hasTable($vouchersTable)) {
            if ($schema->hasColumn($vouchersTable, 'global_discount_amount')) {
                $connection->statement("ALTER TABLE `{$vouchersTable}` DROP COLUMN `global_discount_amount`");
            }
            if ($schema->hasColumn($vouchersTable, 'global_discount')) {
                $connection->statement("ALTER TABLE `{$vouchersTable}` DROP COLUMN `global_discount`");
            }
        }

        $invoicesTable = 'payable_cheque_voucher_invoices';
        if ($schema->hasTable($invoicesTable)) {
            if ($schema->hasColumn($invoicesTable, 'total_returns')) {
                $connection->statement("ALTER TABLE `{$invoicesTable}` DROP COLUMN `total_returns`");
            }
            if ($schema->hasColumn($invoicesTable, 'return_amount')) {
                $connection->statement("ALTER TABLE `{$invoicesTable}` DROP COLUMN `return_amount`");
            }
        }
    }
};
