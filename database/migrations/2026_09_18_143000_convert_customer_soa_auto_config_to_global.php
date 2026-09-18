<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql');
        $db = DB::connection('mysql');

        if (!$schema->hasTable('customer_soa_auto_configs')) {
            return;
        }

        $legacy = null;
        if ($schema->hasColumn('customer_soa_auto_configs', 'customer_id')) {
            $legacy = $db->table('customer_soa_auto_configs')
                ->orderByDesc('enabled')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            // The global design must contain only one configuration, never one row per customer.
            $db->table('customer_soa_auto_configs')->delete();

            $indexes = collect($db->select('SHOW INDEX FROM `customer_soa_auto_configs`'));
            foreach (['customer_soa_auto_configs_customer_unique', 'customer_soa_auto_enabled_customer_idx'] as $indexName) {
                if ($indexes->contains(fn ($row) => (string) ($row->Key_name ?? '') === $indexName)) {
                    $db->statement("ALTER TABLE `customer_soa_auto_configs` DROP INDEX `{$indexName}`");
                }
            }

            Schema::connection('mysql')->table('customer_soa_auto_configs', function (Blueprint $table) {
                $table->dropColumn('customer_id');
            });
        }

        if (!$schema->hasColumn('customer_soa_auto_configs', 'config_key')) {
            Schema::connection('mysql')->table('customer_soa_auto_configs', function (Blueprint $table) {
                $table->string('config_key', 32)->nullable()->after('id');
            });
        }

        // Unconfigured/disabled means NULL lead settings until someone saves SOA(AUTO).
        $db->statement('ALTER TABLE `customer_soa_auto_configs` MODIFY `lead_value` INT UNSIGNED NULL DEFAULT NULL');
        $db->statement('ALTER TABLE `customer_soa_auto_configs` MODIFY `lead_unit` VARCHAR(20) NULL DEFAULT NULL');

        $enabled = (int) ($legacy->enabled ?? 0);
        $leadValue = $enabled ? ($legacy->lead_value ?? null) : null;
        $leadUnit = $enabled ? ($legacy->lead_unit ?? null) : null;

        if (!$db->table('customer_soa_auto_configs')->where('config_key', 'global')->exists()) {
            $db->table('customer_soa_auto_configs')->insert([
                'config_key' => 'global',
                'enabled' => $enabled ? 1 : 0,
                'lead_value' => $leadValue,
                'lead_unit' => $leadUnit,
                'last_sent_at' => $legacy->last_sent_at ?? null,
                'last_error' => $legacy->last_error ?? null,
                'updated_by' => $legacy->updated_by ?? null,
                'created_at' => $legacy->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }

        // Enforce a single named global row key.
        $db->statement("ALTER TABLE `customer_soa_auto_configs` MODIFY `config_key` VARCHAR(32) NOT NULL DEFAULT 'global'");

        $indexes = collect($db->select('SHOW INDEX FROM `customer_soa_auto_configs`'));
        $hasUniqueConfigKey = $indexes->contains(function ($row) {
            return (string) ($row->Column_name ?? '') === 'config_key'
                && (int) ($row->Non_unique ?? 1) === 0;
        });

        if (!$hasUniqueConfigKey) {
            $db->statement('ALTER TABLE `customer_soa_auto_configs` ADD UNIQUE KEY `customer_soa_auto_configs_key_unique` (`config_key`)');
        }
    }

    public function down(): void
    {
        // Deliberately no automatic rollback to the incorrect per-customer design.
    }
};
