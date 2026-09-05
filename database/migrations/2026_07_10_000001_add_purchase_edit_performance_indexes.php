<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('purchase_notes', 'pnotes_supplier_status_idx', function (Blueprint $table) {
            $table->index(['supplier_id', 'status'], 'pnotes_supplier_status_idx');
        });

        $this->addIndexIfMissing('purchase_orders', 'po_reference_idx', function (Blueprint $table) {
            $table->index('reference_number', 'po_reference_idx');
        });

        $this->addIndexIfMissing('purchase_note_items', 'pni_note_product_idx', function (Blueprint $table) {
            $table->index(['purchase_note_id', 'product_id'], 'pni_note_product_idx');
        });

        $this->addIndexIfMissing('purchase_order_items', 'poi_order_product_idx', function (Blueprint $table) {
            $table->index(['purchase_order_id', 'product_id'], 'poi_order_product_idx');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('purchase_order_items', 'poi_order_product_idx');
        $this->dropIndexIfExists('purchase_note_items', 'pni_note_product_idx');
        $this->dropIndexIfExists('purchase_orders', 'po_reference_idx');
        $this->dropIndexIfExists('purchase_notes', 'pnotes_supplier_status_idx');
    }

    private function addIndexIfMissing(string $table, string $indexName, callable $callback): void
    {
        if (!Schema::connection('purchase')->hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::connection('purchase')->table($table, $callback);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::connection('purchase')->hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::connection('purchase')->table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $database = config('database.connections.purchase.database');

        return DB::connection('purchase')
            ->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
