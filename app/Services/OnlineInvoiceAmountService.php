<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OnlineInvoiceAmountService
{
    /** @var array<int, object|null> */
    private array $reportCache = [];

    /** @var array<int, object|null> */
    private array $productCache = [];

    /** @var array<int, array<string, object>> */
    private array $orderCache = [];

    /**
     * Resolve the exact amount displayed by the online-print invoice.
     *
     * Price precedence intentionally mirrors the online-print builder:
     * report product price, report item price, product online price,
     * saved item unit price, then product selling price.
     */
    public function resolve(int $reportId, string $invoiceNo = ''): float
    {
        $report = $this->report($reportId);
        if (!$report) {
            return 0.0;
        }

        $invoiceNumbers = $this->decodeArray($report->invoice_numbers ?? []);
        $notesData = $this->decodeArray($report->notes_data ?? []);
        $prices = $this->decodeArray($report->prices ?? []);

        $index = $this->invoiceIndex($invoiceNumbers, $invoiceNo);
        if ($index === null) {
            $index = count($invoiceNumbers) === 1 ? 0 : null;
        }

        $note = $index !== null ? ($notesData[$index] ?? null) : null;
        if (!is_array($note) && count($notesData) === 1 && is_array($notesData[0] ?? null)) {
            $note = $notesData[0];
        }

        $items = is_array($note) ? $this->decodeArray($note['items'] ?? []) : [];
        $total = 0.0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $itemId = $item['id'] ?? null;
            $price = 0.0;

            if ($productId > 0 && isset($prices[(string) $productId]) && (float) $prices[(string) $productId] > 0) {
                $price = (float) $prices[(string) $productId];
            }

            if ($price <= 0 && $index !== null && $itemId !== null) {
                $itemPriceKey = $index . '-' . $itemId;
                if (isset($prices[$itemPriceKey]) && (float) $prices[$itemPriceKey] > 0) {
                    $price = (float) $prices[$itemPriceKey];
                }
            }

            $product = $productId > 0 ? $this->product($productId) : null;
            if ($price <= 0 && $product && (float) ($product->price_online ?? 0) > 0) {
                $price = (float) $product->price_online;
            }
            if ($price <= 0 && (float) ($item['unit_price'] ?? 0) > 0) {
                $price = (float) $item['unit_price'];
            }
            if ($price <= 0 && $product && (float) ($product->selling_price ?? 0) > 0) {
                $price = (float) $product->selling_price;
            }

            $total += $quantity * $price;
        }

        if ($total > 0) {
            return round($total, 2);
        }

        $order = $this->onlineOrder($reportId, $invoiceNo);
        if ($order && (float) ($order->total_amount ?? 0) > 0) {
            return round((float) $order->total_amount, 2);
        }

        $netTotal = is_array($note) ? (float) ($note['net_total'] ?? 0) : 0.0;
        if ($netTotal > 0) {
            return round($netTotal, 2);
        }

        $subtotal = array_sum(array_map(
            static fn ($item) => is_array($item) ? (float) ($item['subtotal'] ?? 0) : 0.0,
            $items
        ));

        return round((float) $subtotal, 2);
    }

    public function onlineOrder(int $reportId, string $invoiceNo = ''): ?object
    {
        if (!array_key_exists($reportId, $this->orderCache)) {
            $orders = DB::connection('sales')->table('sales_orders')
                ->where('order_number', 'LIKE', 'ONL-' . $reportId . '-%')
                ->whereIn('status', ['Confirmed', 'Closed'])
                ->orderBy('id')
                ->get();

            $map = [];
            foreach ($orders as $order) {
                foreach ($this->invoiceValues($order->invoice_numbers ?? '') as $value) {
                    $map[$this->normalizeInvoice($value)] = $order;
                }
            }
            $this->orderCache[$reportId] = $map;
        }

        $key = $this->normalizeInvoice($invoiceNo);
        if ($key !== '' && isset($this->orderCache[$reportId][$key])) {
            return $this->orderCache[$reportId][$key];
        }

        return count($this->orderCache[$reportId]) === 1
            ? reset($this->orderCache[$reportId]) ?: null
            : null;
    }

    private function report(int $reportId): ?object
    {
        if (!array_key_exists($reportId, $this->reportCache)) {
            $this->reportCache[$reportId] = DB::connection('sales')->table('online_reports')
                ->where('id', $reportId)
                ->first(['id', 'prices', 'invoice_numbers', 'notes_data']);
        }

        return $this->reportCache[$reportId];
    }

    private function product(int $productId): ?object
    {
        if (!array_key_exists($productId, $this->productCache)) {
            $this->productCache[$productId] = DB::connection('masterlist')->table('products')
                ->where('id', $productId)
                ->first(['id', 'price_online', 'selling_price']);
        }

        return $this->productCache[$productId];
    }

    private function invoiceIndex(array $invoiceNumbers, string $invoiceNo): ?int
    {
        $needle = $this->normalizeInvoice($invoiceNo);
        if ($needle === '') {
            return null;
        }

        foreach ($invoiceNumbers as $index => $value) {
            if ($this->normalizeInvoice($value) === $needle) {
                return (int) $index;
            }
        }

        return null;
    }

    private function invoiceValues(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return array_map('trim', explode(',', $text));
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeInvoice(mixed $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }
}
