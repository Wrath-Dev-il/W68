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
        $table = 'payable_cheque_voucher_invoices';

        if ($schema->hasTable($table)) {
            if (!$schema->hasColumn($table, 'discount_1')) {
                $connection->statement("ALTER TABLE `{$table}` ADD COLUMN `discount_1` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `amount_due`");
            }
            if (!$schema->hasColumn($table, 'discount_2')) {
                $connection->statement("ALTER TABLE `{$table}` ADD COLUMN `discount_2` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `discount_1`");
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('accounting');
        $schema = $connection->getSchemaBuilder();
        $table = 'payable_cheque_voucher_invoices';

        if ($schema->hasTable($table)) {
            if ($schema->hasColumn($table, 'discount_2')) {
                $connection->statement("ALTER TABLE `{$table}` DROP COLUMN `discount_2`");
            }
            if ($schema->hasColumn($table, 'discount_1')) {
                $connection->statement("ALTER TABLE `{$table}` DROP COLUMN `discount_1`");
            }
        }
    }
};