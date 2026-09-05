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
            if (!$schema->hasColumn($table, 'rs_details')) {
                $connection->statement("ALTER TABLE `{$table}` ADD COLUMN `rs_details` TEXT NULL DEFAULT NULL AFTER `total_returns`");
            }
        }
    }

    public function down(): void
    {
        $connection = DB::connection('accounting');
        $schema = $connection->getSchemaBuilder();
        $table = 'payable_cheque_voucher_invoices';

        if ($schema->hasTable($table)) {
            if ($schema->hasColumn($table, 'rs_details')) {
                $connection->statement("ALTER TABLE `{$table}` DROP COLUMN `rs_details`");
            }
        }
    }
};
