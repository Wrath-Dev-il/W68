<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes Product Ledger OUT movements when a Sales Order invoice is deleted.
 *
 * Current rows are matched by the durable source_type/source_id/source_item_id
 * linkage. Conservative legacy fallbacks are kept for rows created before
 * those linkage columns were populated.
 */
class SalesInvoiceLedgerDeleteService
{
    /**
     * Delete Product Ledger rows for a Local Sales Order invoice.
     *
     * @return array{deleted_entries:int,affected_products:array<int,int>}
     */
    public function deleteForSalesOrder($salesOrder): array
    {
        $salesOrderId = (int) ($salesOrder->id ?? 0);
        if ($salesOrderId <= 0) {
            return $this->emptyResult();
        }

        $items = DB::connection('sales')
            ->table('sales_order_items')
            ->where('sales_order_id', $salesOrderId)
            ->get(['id', 'product_id']);

        $itemIds = $items
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $productIds = $items
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $ledgerIds = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('source_type', 'sales_order')
            ->where(function ($query) use ($salesOrderId, $itemIds) {
                $query->where('source_id', $salesOrderId);

                if ($itemIds !== []) {
                    $query->orWhereIn('source_item_id', $itemIds);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Legacy Local Sales Order movements used SalesNote.sales_number as
        // transaction_number. The old delete route incorrectly searched with
        // SalesOrder.order_number, which is why those ledger rows survived.
        $salesNumber = '';
        $salesNoteId = (int) ($salesOrder->sales_note_id ?? 0);
        if ($salesNoteId > 0) {
            $salesNumber = trim((string) DB::connection('sales')
                ->table('sales_notes')
                ->where('id', $salesNoteId)
                ->value('sales_number'));
        }

        $invoiceNumbers = trim((string) ($salesOrder->invoice_numbers ?? ''));
        $invoiceReferences = $this->referenceValues($invoiceNumbers);

        if ($salesNumber !== '') {
            $legacyQuery = DB::connection('ledger')
                ->table('product_ledgers')
                ->where('transaction_type', 'OUT')
                ->where('transaction_number', $salesNumber)
                ->where('remarks', 'like', 'Sales Order Processing%')
                ->where(function ($query) use ($salesOrderId) {
                    $query->whereNull('source_type')
                        ->orWhere('source_type', '')
                        ->orWhere(function ($linked) use ($salesOrderId) {
                            $linked->where('source_type', 'sales_order')
                                ->where(function ($source) use ($salesOrderId) {
                                    $source->whereNull('source_id')
                                        ->orWhere('source_id', 0)
                                        ->orWhere('source_id', $salesOrderId);
                                });
                        });
                });

            if ($productIds !== []) {
                $legacyQuery->whereIn('product_id', $productIds);
            }

            if ($invoiceReferences !== []) {
                $legacyQuery->whereIn('reference_number', $invoiceReferences);
            }

            $legacyIds = $legacyQuery
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ledgerIds = array_merge($ledgerIds, $legacyIds);
        }

        $result = $this->deleteAndRebuild($ledgerIds, 'local_sales_order', $salesOrderId);

        Log::info('[SALES INVOICE DELETE] Local invoice ledger cleanup completed.', [
            'sales_order_id' => $salesOrderId,
            'order_number' => (string) ($salesOrder->order_number ?? ''),
            'invoice_numbers' => $invoiceNumbers,
            'deleted_entries' => $result['deleted_entries'],
            'affected_products' => $result['affected_products'],
        ]);

        return $result;
    }

    /**
     * Delete Product Ledger rows for an Online Report invoice.
     *
     * @return array{deleted_entries:int,affected_products:array<int,int>}
     */
    public function deleteForOnlineReport($report): array
    {
        $reportId = (int) ($report->id ?? 0);
        if ($reportId <= 0) {
            return $this->emptyResult();
        }

        // Current authoritative linkage.
        $ledgerIds = DB::connection('ledger')
            ->table('product_ledgers')
            ->where('source_type', 'online_report')
            ->where('source_id', $reportId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Durable Online Report -> Product Ledger links can recover rows that
        // were created before the source fields were stamped on product_ledgers.
        if (Schema::connection('sales')->hasTable('online_report_product_ledger_links')) {
            $linkedIds = DB::connection('sales')
                ->table('online_report_product_ledger_links')
                ->where('report_id', $reportId)
                ->pluck('product_ledger_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->all();

            $ledgerIds = array_merge($ledgerIds, $linkedIds);
        }

        // Legacy Online Report rows used SalesNote.sales_number and the
        // "Online Report Generation" remark instead of source_type/source_id.
        $noteIds = array_values(array_filter(array_map(
            fn ($value) => (int) trim((string) $value),
            explode(',', (string) ($report->sales_note_ids ?? ''))
        ), fn ($value) => $value > 0));

        if ($noteIds !== []) {
            $salesNumbers = DB::connection('sales')
                ->table('sales_notes')
                ->whereIn('id', $noteIds)
                ->pluck('sales_number')
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->values()
                ->all();

            if ($salesNumbers !== []) {
                $reportProductIds = $this->onlineReportProductIds($reportId, $report);

                $legacyQuery = DB::connection('ledger')
                    ->table('product_ledgers')
                    ->where('transaction_type', 'OUT')
                    ->whereIn('transaction_number', $salesNumbers)
                    ->where('remarks', 'like', 'Online Report Generation%')
                    ->where(function ($query) use ($reportId) {
                        $query->whereNull('source_type')
                            ->orWhere('source_type', '')
                            ->orWhere(function ($linked) use ($reportId) {
                                $linked->where('source_type', 'online_report')
                                    ->where(function ($source) use ($reportId) {
                                        $source->whereNull('source_id')
                                            ->orWhere('source_id', 0)
                                            ->orWhere('source_id', $reportId);
                                    });
                            });
                    });

                if ($reportProductIds !== []) {
                    $legacyQuery->whereIn('product_id', $reportProductIds);
                }

                $legacyIds = $legacyQuery
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $ledgerIds = array_merge($ledgerIds, $legacyIds);
            }
        }

        $result = $this->deleteAndRebuild($ledgerIds, 'online_report', $reportId);

        // Remove stale link records only after the matching Product Ledger rows
        // have been deleted successfully. The report's normal delete flow stays
        // unchanged in routes/web.php.
        if (Schema::connection('sales')->hasTable('online_report_product_ledger_links')) {
            DB::connection('sales')
                ->table('online_report_product_ledger_links')
                ->where('report_id', $reportId)
                ->delete();
        }

        Log::info('[SALES INVOICE DELETE] Online invoice ledger cleanup completed.', [
            'online_report_id' => $reportId,
            'deleted_entries' => $result['deleted_entries'],
            'affected_products' => $result['affected_products'],
        ]);

        return $result;
    }

    /**
     * Delete selected rows, rebuild all later running balances, then synchronize
     * products.on_hand from the latest authoritative Product Ledger balance.
     *
     * @return array{deleted_entries:int,affected_products:array<int,int>}
     */
    private function deleteAndRebuild(array $ledgerIds, string $sourceType, int $sourceId): array
    {
        $ledgerIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $ledgerIds
        ), fn ($id) => $id > 0)));

        if ($ledgerIds === []) {
            return $this->emptyResult();
        }

        $earliestDateByProduct = [];
        $deletedEntries = 0;

        DB::connection('ledger')->transaction(function () use (
            $ledgerIds,
            &$earliestDateByProduct,
            &$deletedEntries
        ) {
            $rows = DB::connection('ledger')
                ->table('product_ledgers')
                ->whereIn('id', $ledgerIds)
                ->orderBy('product_id')
                ->orderBy('date')
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'product_id', 'date']);

            if ($rows->isEmpty()) {
                return;
            }

            foreach ($rows as $row) {
                $productId = (int) ($row->product_id ?? 0);
                if ($productId <= 0) {
                    continue;
                }

                $date = trim((string) ($row->date ?? ''));
                if ($date === '') {
                    $date = '0000-00-00';
                }

                if (
                    !isset($earliestDateByProduct[$productId])
                    || strcmp($date, $earliestDateByProduct[$productId]) < 0
                ) {
                    $earliestDateByProduct[$productId] = $date;
                }
            }

            $deleteIds = $rows
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $deletedEntries = DB::connection('ledger')
                ->table('product_ledgers')
                ->whereIn('id', $deleteIds)
                ->delete();
        });

        $affectedProducts = array_map('intval', array_keys($earliestDateByProduct));

        foreach ($earliestDateByProduct as $productId => $fromDate) {
            SalesOrderLedgerService::recalculateFrom((int) $productId, $fromDate);
        }

        if ($affectedProducts !== []) {
            ProductStockSyncService::syncProducts($affectedProducts);
        }

        Log::info('[SALES INVOICE DELETE] Product Ledger rows deleted and balances rebuilt.', [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'deleted_entries' => (int) $deletedEntries,
            'affected_products' => $affectedProducts,
        ]);

        return [
            'deleted_entries' => (int) $deletedEntries,
            'affected_products' => array_values(array_unique($affectedProducts)),
        ];
    }

    /**
     * Product IDs belonging to an Online Report, preferring generated ONL sales
     * rows and falling back to the report's notes_data snapshot.
     *
     * @return array<int,int>
     */
    private function onlineReportProductIds(int $reportId, $report): array
    {
        $productIds = DB::connection('sales')
            ->table('sales_orders as so')
            ->join('sales_order_items as soi', 'soi.sales_order_id', '=', 'so.id')
            ->where('so.order_number', 'like', 'ONL-' . $reportId . '-%')
            ->pluck('soi.product_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds !== []) {
            return $productIds;
        }

        $notesData = $report->notes_data ?? [];
        if (is_string($notesData)) {
            $notesData = json_decode($notesData, true) ?: [];
        }

        if (!is_array($notesData)) {
            return [];
        }

        $ids = [];
        foreach ($notesData as $note) {
            $items = is_array($note) ? ($note['items'] ?? []) : [];
            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId > 0) {
                    $ids[$productId] = $productId;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<int,string>
     */
    private function referenceValues(string $value): array
    {
        $values = [];
        $value = trim($value);

        if ($value !== '') {
            $values[$value] = true;
        }

        foreach (preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $values[$part] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * @return array{deleted_entries:int,affected_products:array<int,int>}
     */
    private function emptyResult(): array
    {
        return [
            'deleted_entries' => 0,
            'affected_products' => [],
        ];
    }
}
