<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Carbon\Carbon;

class SlowMovingProductsExport implements FromCollection, WithEvents
{
    /**
     * Low-movement threshold — total OUT movement across all tracked years
     * must be <= this value for a product to be included.
     */
    protected int $threshold;

    /** Highest column letter (dynamically computed). */
    protected string $highestColumn = 'N';

    /** Total column count (fixed + dynamic yearly columns). */
    protected int $columnCount = 14;

    /**
     * Column headers array — built dynamically during collection().
     *
     * @var array<string, string> letter => label
     */
    protected array $headers = [];

    /**
     * Years with OUT movement data — detected from ledger.
     *
     * @var array<int>
     */
    protected array $outYears = [];

    /**
     * Optional date range start (Y-m-d) for filtering OUT movements.
     */
    protected ?string $startDate = null;

    /**
     * Optional date range end (Y-m-d) for filtering OUT movements.
     */
    protected ?string $endDate = null;

    /**
     * Human-readable date range label (e.g. "2020 - 2026").
     */
    protected string $dateRangeLabel = '';

    /**
     * Parallel array mapping each flat-row index to styling info.
     *
     * @var array<int, array{isGroup: bool, fill?: string}>
     */
    protected array $rowStyles = [];

    public function __construct(int $threshold = 5, ?string $startDate = null, ?string $endDate = null, ?string $dateRangeLabel = '')
    {
        $this->threshold = $threshold;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->dateRangeLabel = $dateRangeLabel ?? '';
    }


    /**
     * Load in-stock product master data and ledger movement metadata without a
     * SQL join between the masterlist and ledger MariaDB services.
     */
    protected function loadStockProducts(): Collection
    {
        $ledgerRows = collect(DB::connection('ledger')->select("
            SELECT
                lp.product_id,
                lp.balance_stock AS on_hand,
                latest_in.in_date AS latest_purchase_date,
                latest_in.supplier_name,
                latest_in.remarks,
                latest_in.cost AS last_cost,
                latest_out.out_date AS latest_sale_date
            FROM (
                SELECT pl2.product_id, pl2.balance_stock
                FROM product_ledgers pl2
                INNER JOIN (
                    SELECT product_id, MAX(id) AS max_id
                    FROM product_ledgers
                    GROUP BY product_id
                ) latest_pl ON latest_pl.max_id = pl2.id
                WHERE pl2.balance_stock > 0
            ) lp
            LEFT JOIN (
                SELECT
                    pl3.product_id,
                    pl3.date AS in_date,
                    pl3.entity_name AS supplier_name,
                    pl3.remarks,
                    pl3.cost
                FROM product_ledgers pl3
                INNER JOIN (
                    SELECT product_id, MAX(id) AS max_id
                    FROM product_ledgers
                    WHERE quantity_in > 0
                    GROUP BY product_id
                ) latest_in_id ON latest_in_id.max_id = pl3.id
            ) latest_in ON latest_in.product_id = lp.product_id
            LEFT JOIN (
                SELECT pl4.product_id, pl4.date AS out_date
                FROM product_ledgers pl4
                INNER JOIN (
                    SELECT product_id, MAX(id) AS max_id
                    FROM product_ledgers
                    WHERE quantity_out > 0
                    GROUP BY product_id
                ) latest_out_id ON latest_out_id.max_id = pl4.id
            ) latest_out ON latest_out.product_id = lp.product_id
        "))->keyBy(fn ($row) => (int) $row->product_id);

        if ($ledgerRows->isEmpty()) {
            return collect();
        }

        return DB::connection('masterlist')->table('products')
            ->whereIn('id', $ledgerRows->keys()->all())
            ->orderBy('description')
            ->orderBy('product_code')
            ->get([
                'id', 'product_code', 'part_number', 'description', 'application',
                'Position', 'selling_price', 'price_online',
            ])
            ->map(function ($product) use ($ledgerRows) {
                $ledger = $ledgerRows->get((int) $product->id);
                $product->product_id = (int) $product->id;
                $product->on_hand = (int) ($ledger->on_hand ?? 0);
                $product->latest_purchase_date = $ledger->latest_purchase_date ?? null;
                $product->supplier_name = $ledger->supplier_name ?? null;
                $product->remarks = $ledger->remarks ?? null;
                $product->last_cost = (float) ($ledger->last_cost ?? 0);
                $product->latest_sale_date = $ledger->latest_sale_date ?? null;
                return $product;
            })
            ->values();
    }

    /**
     * Detect years that have OUT movement in the product ledger.
     */
    protected function detectOutYears(): array
    {
        if (!empty($this->outYears)) {
            return $this->outYears;
        }

        $query = DB::connection('ledger')
            ->table('product_ledgers')
            ->select(DB::raw('YEAR(date) as yr'))
            ->where('quantity_out', '>', 0);

        if ($this->startDate) {
            $query->where('date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->where('date', '<=', $this->endDate);
        }

        $rows = $query->groupBy(DB::raw('YEAR(date)'))
            ->orderBy(DB::raw('YEAR(date)'))
            ->get();

        $this->outYears = $rows->pluck('yr')->map(fn ($v) => (int) $v)->toArray();

        return $this->outYears;
    }

    /**
     * Build headers and column map based on detected OUT years.
     *
     * Column order:
     * A=Product Code, B=Part Number, C=Description, D=Application, E=Position,
     * F=Supplier, G=Latest Cost, H=Selling Price, I=Online Price, J=On Hand,
     * K=Latest Purchase Date, L=Latest Sale Date, M=Aging Days, N=Remarks,
     * O+=Yearly OUT columns
     */
    protected function buildHeaders(): void
    {
        $outYears = $this->detectOutYears();

        $this->headers = [
            'A' => 'Product Code',
            'B' => 'Part Number',
            'C' => 'Description',
            'D' => 'Application',
            'E' => 'Position',
            'F' => 'Supplier',
            'G' => 'Latest Cost',
            'H' => 'Selling Price',
            'I' => 'Online Price',
            'J' => 'On Hand',
            'K' => 'Latest Purchase Date',
            'L' => 'Latest Sale Date',
            'M' => 'Aging Days',
            'N' => 'Remarks',
        ];

        $colLetter = 'O';
        foreach ($outYears as $year) {
            $this->headers[$colLetter] = (string) $year;
            $colLetter++;
        }

        $this->highestColumn = count($this->headers) > 14
            ? chr(ord('A') + count($this->headers) - 1)
            : 'N';
        $this->columnCount = count($this->headers);
    }

    /**
     * Build the flat row collection with description-group headers.
     */
    public function collection(): Collection
    {
        $this->buildHeaders();
        $outYears = $this->detectOutYears();
        $hc = $this->highestColumn;

        // ── 1. Get products with balance_stock > 0 from ledger ──
        // Ledger and masterlist are separate HostForge services, so the merge
        // is performed in Laravel rather than with a cross-database SQL JOIN.
        $products = $this->loadStockProducts();

        // ── 2. Get yearly OUT totals per product from ledger ──
        $yq = DB::connection('ledger')
            ->table('product_ledgers')
            ->select('product_id', DB::raw('YEAR(date) as yr'), DB::raw('SUM(quantity_out) as total_out'))
            ->where('quantity_out', '>', 0);

        if ($this->startDate) {
            $yq->where('date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $yq->where('date', '<=', $this->endDate);
        }

        $yearlyOutRaw = $yq->groupBy('product_id', DB::raw('YEAR(date)'))
            ->get();

        $yearlyOutMap = [];
        foreach ($yearlyOutRaw as $row) {
            $pid = (int) $row->product_id;
            $yr = (int) $row->yr;
            $yearlyOutMap[$pid][$yr] = (int) $row->total_out;
        }

        // ── 3. Assemble flat rows with threshold filter ──
        $flatRows = [];
        $this->rowStyles = [];
        $lastDesc = null;

        foreach ($products as $product) {
            $pid = (int) $product->product_id;
            $desc = strtoupper(trim($this->cleanText($product->description ?: 'NO DESCRIPTION')));

            // Calculate total OUT across all years
            $prodYearly = $yearlyOutMap[$pid] ?? [];
            $totalOut = array_sum($prodYearly);

            // Threshold filter: skip if total OUT > threshold
            if ($totalOut > $this->threshold) {
                continue;
            }

            // ── Description group header row ──
            if ($desc !== $lastDesc) {
                $groupRow = [$desc];
                for ($i = 1; $i < $this->columnCount; $i++) {
                    $groupRow[] = '';
                }
                $flatRows[] = $groupRow;
                $this->rowStyles[] = ['isGroup' => true];
                $lastDesc = $desc;
            }

            // ── Application + Position ──
            $app = $this->cleanText($product->application ?? '');
            $pos = $this->cleanText($product->Position ?? '');

            // ── Date formatting ──
            $latestPurchaseDate = $product->latest_purchase_date
                ? Carbon::parse($product->latest_purchase_date)->format('Y-m-d')
                : '';
            $latestSaleDate = $product->latest_sale_date
                ? Carbon::parse($product->latest_sale_date)->format('Y-m-d')
                : '';

            // ── Aging Days (corrected logic) ──
            $agingDays = '';
            if ($product->latest_sale_date) {
                // OUT exists — aging from latest OUT date
                $agingDays = (int) Carbon::parse($product->latest_sale_date)->diffInDays(now());
            } elseif ($product->latest_purchase_date) {
                // No OUT — aging from latest IN date
                $agingDays = (int) Carbon::parse($product->latest_purchase_date)->diffInDays(now());
            }

            // ── Build row with NEW column order ──
            // A=Product Code, B=Part Number, C=Description, D=Application, E=Position,
            // F=Supplier, G=Latest Cost, H=Selling Price, I=Online Price, J=On Hand,
            // K=Latest Purchase Date, L=Latest Sale Date, M=Aging Days, N=Remarks
            $row = [
                $this->cleanText($product->product_code),          // A
                $this->cleanText($product->part_number),           // B
                $this->cleanText($product->description),           // C
                $app,                                              // D
                $pos,                                              // E
                $this->cleanText($product->supplier_name ?? ''),  // F — Supplier
                (float) ($product->last_cost ?? 0),                // G — Latest Cost
                (float) ($product->selling_price ?? 0),            // H — Selling Price
                (float) ($product->price_online ?? 0),             // I — Online Price
                (int) ($product->on_hand ?? 0),                    // J — On Hand
                $latestPurchaseDate,                               // K — Latest Purchase Date
                $latestSaleDate,                                   // L — Latest Sale Date
                $agingDays,                                        // M — Aging Days
                $this->cleanText($product->remarks ?? ''),         // N — Remarks
            ];

            // ── Append dynamic yearly OUT columns ──
            foreach ($outYears as $year) {
                $row[] = (int) ($prodYearly[$year] ?? 0);
            }

            // ── Row colour ──
            $color = $this->getRowColor($product);

            $flatRows[] = $row;
            $this->rowStyles[] = ['isGroup' => false, 'fill' => $color];
        }

        return collect($flatRows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $hc = $this->highestColumn;

                // ── 1. Insert title rows + header row ──
                $sheet->insertNewRowBefore(1, 4);

                $sheet->setCellValue('A1', 'W68 AUTOPARTS & SERVICE CENTER');
                $sheet->mergeCells("A1:{$hc}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $titleLabel = 'SLOW MOVING PRODUCTS';
                if ($this->dateRangeLabel) {
                    $titleLabel .= ' — ' . strtoupper($this->dateRangeLabel);
                }
                $sheet->setCellValue('A2', $titleLabel);
                $sheet->mergeCells("A2:{$hc}2");
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->setCellValue('A3', strtoupper(now()->format('M d, Y')));
                $sheet->mergeCells("A3:{$hc}3");
                $sheet->getStyle('A3')->getFont()->setSize(11);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // ── Column header row (row 4) ──
                foreach ($this->headers as $col => $label) {
                    $sheet->setCellValue("{$col}4", $label);
                }
                $sheet->getStyle("A4:{$hc}4")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['rgb' => 'FFC72C'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4A0A15'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                    ],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(20);

                // Data rows start at row 5
                $dataStartRow = 5;
                $lastRow = $sheet->getHighestRow();

                // ── 2. Range-based column formatting ──
                if ($lastRow >= $dataStartRow) {
                    $sheet->getStyle("A{$dataStartRow}:{$hc}{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        ],
                    ]);

                    // Wrap text on Part Number (B), Application (D), Position (E), Supplier (F), Remarks (N)
                    $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("E{$dataStartRow}:E{$lastRow}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("F{$dataStartRow}:F{$lastRow}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("N{$dataStartRow}:N{$lastRow}")->getAlignment()->setWrapText(true);

                    // Number format #,##0.00 on Latest Cost (G), Selling Price (H), Online Price (I)
                    if (isset($this->headers['G'])) {
                        $sheet->getStyle("G{$dataStartRow}:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    if (isset($this->headers['H'])) {
                        $sheet->getStyle("H{$dataStartRow}:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    if (isset($this->headers['I'])) {
                        $sheet->getStyle("I{$dataStartRow}:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }

                    // Right-align on Latest Cost (G), Selling Price (H), Online Price (I)
                    if (isset($this->headers['G'])) {
                        $sheet->getStyle("G{$dataStartRow}:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    if (isset($this->headers['H'])) {
                        $sheet->getStyle("H{$dataStartRow}:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    if (isset($this->headers['I'])) {
                        $sheet->getStyle("I{$dataStartRow}:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    // Center-align On Hand (J) and Aging Days (M)
                    $centerCols = ['J', 'M'];
                    foreach ($centerCols as $cc) {
                        if (isset($this->headers[$cc])) {
                            $sheet->getStyle("{$cc}{$dataStartRow}:{$cc}{$lastRow}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                    }
                    // Center-align yearly OUT columns (O+)
                    $dynamicStartCol = 'O';
                    if (isset($this->headers[$dynamicStartCol])) {
                        $dynCol = $dynamicStartCol;
                        while (isset($this->headers[$dynCol])) {
                            $sheet->getStyle("{$dynCol}{$dataStartRow}:{$dynCol}{$lastRow}")->getAlignment()
                                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            $dynCol++;
                        }
                    }
                }

                // ── 3. Collect rows by style type ──
                $redRanges = [];
                $yellowRanges = [];
                $greenRanges = [];
                $groupHeaderRows = [];

                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    $flatIndex = $row - $dataStartRow;
                    $styleInfo = $this->rowStyles[$flatIndex] ?? null;
                    if (!$styleInfo) {
                        continue;
                    }

                    if ($styleInfo['isGroup']) {
                        $groupHeaderRows[] = $row;
                    } else {
                        $cellRange = "A{$row}:{$hc}{$row}";
                        switch ($styleInfo['fill'] ?? 'FFFFFF') {
                            case 'FFE0E0':
                                $redRanges[] = $cellRange;
                                break;
                            case 'FFF9C4':
                                $yellowRanges[] = $cellRange;
                                break;
                            case 'E8F5E9':
                                $greenRanges[] = $cellRange;
                                break;
                        }
                    }
                }

                // ── 4. Apply item fill colours via merged consecutive ranges ──
                foreach ($this->mergeConsecutiveRanges($redRanges, $hc) as $range) {
                    $sheet->getStyle($range)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFE0E0'],
                        ],
                    ]);
                }
                foreach ($this->mergeConsecutiveRanges($yellowRanges, $hc) as $range) {
                    $sheet->getStyle($range)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF9C4'],
                        ],
                    ]);
                }
                foreach ($this->mergeConsecutiveRanges($greenRanges, $hc) as $range) {
                    $sheet->getStyle($range)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E8F5E9'],
                        ],
                    ]);
                }

                // ── 5. Group header styling via merged consecutive ranges ──
                if ($groupHeaderRows) {
                    $groupRanges = $this->mergeConsecutiveRanges(
                        array_map(fn($r) => "A{$r}:{$hc}{$r}", $groupHeaderRows),
                        $hc
                    );
                    foreach ($groupRanges as $gRange) {
                        $sheet->getStyle($gRange)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 12,
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F1F5F9'],
                            ],
                            'borders' => [
                                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                                'bottom' => ['borderStyle' => Border::BORDER_THIN,  'color' => ['rgb' => '000000']],
                                'left'   => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                                'right'  => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                            ],
                        ]);
                    }

                    foreach ($groupHeaderRows as $ghRow) {
                        $sheet->mergeCells("A{$ghRow}:{$hc}{$ghRow}");
                        $sheet->getStyle("A{$ghRow}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                        $sheet->getRowDimension($ghRow)->setRowHeight(24);
                    }
                }

                // ── 6. Column widths (match new order) ──
                $widths = [
                    'A' => 16,  // Product Code
                    'B' => 20,  // Part Number (widened for text wrap)
                    'C' => 26,  // Description
                    'D' => 28,  // Application
                    'E' => 14,  // Position (widened for text wrap)
                    'F' => 24,  // Supplier
                    'G' => 14,  // Latest Cost
                    'H' => 14,  // Selling Price
                    'I' => 14,  // Online Price
                    'J' => 10,  // On Hand
                    'K' => 18,  // Latest Purchase Date
                    'L' => 18,  // Latest Sale Date
                    'M' => 12,  // Aging Days
                    'N' => 28,  // Remarks (widened for text wrap)
                ];
                foreach ($widths as $col => $width) {
                    if (isset($this->headers[$col])) {
                        $sheet->getColumnDimension($col)->setWidth($width);
                    }
                }
                // Dynamic yearly columns (O+) — width 10
                $dynCol = 'O';
                while (isset($this->headers[$dynCol])) {
                    $sheet->getColumnDimension($dynCol)->setWidth(10);
                    $dynCol++;
                }

                // ── 7. Freeze pane below header row ──
                $sheet->freezePane('A5');

                // ── 8. Page setup — landscape, A4, fit to width ──
                $ps = $sheet->getPageSetup();
                $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
                $ps->setFitToWidth(1);
                $ps->setFitToHeight(0);
            },
        ];
    }

    /**
     * Determine row background colour based on OUT movement.
     *
     * - Red (FFE0E0): No OUT movement at all.
     * - Yellow (FFF9C4): Latest OUT >= 180 days ago.
     * - Green (E8F5E9): Latest OUT < 180 days ago but still low total.
     */
    protected function getRowColor($product): string
    {
        if (empty($product->latest_sale_date)) {
            return 'FFE0E0'; // Red — no OUT movement
        }

        $agingDays = Carbon::parse($product->latest_sale_date)->diffInDays(now());

        if ($agingDays >= 180) {
            return 'FFF9C4'; // Yellow — 6+ months stale
        }

        return 'E8F5E9'; // Green — recent but low volume
    }

    /**
     * Merge single-row ranges into continuous multi-row ranges.
     */
    protected function mergeConsecutiveRanges(array $ranges, string $hc): array
    {
        if (empty($ranges)) {
            return [];
        }

        $rows = [];
        foreach ($ranges as $r) {
            if (preg_match('/^A\d+:' . preg_quote($hc, '/') . '(\d+)$/', $r, $m)) {
                $rows[] = (int) $m[1];
            }
        }
        sort($rows);

        if (empty($rows)) {
            return [];
        }

        $merged = [];
        $blockStart = $rows[0];
        $prev = $blockStart;

        for ($i = 1, $len = count($rows); $i < $len; $i++) {
            if ($rows[$i] > $prev + 1) {
                $merged[] = "A{$blockStart}:{$hc}{$prev}";
                $blockStart = $rows[$i];
            }
            $prev = $rows[$i];
        }
        $merged[] = "A{$blockStart}:{$hc}{$prev}";

        return $merged;
    }

    /**
     * Clean a text value — trim and convert null/---/n/a to empty string.
     */
    protected function cleanText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return in_array(strtolower($text), ['null', '---', 'n/a'], true) ? '' : $text;
    }

    /**
     * Get total count of products meeting the slow-moving threshold.
     * Used by the route for the response message.
     */
    public function getProductCount(): int
    {
        $outYears = $this->detectOutYears();

        // Get all products with positive latest ledger balance.  The helper
        // keeps the masterlist/ledger merge cross-server safe.
        $pids = $this->loadStockProducts()->pluck('product_id')->map('intval')->toArray();

        if (empty($pids)) {
            return 0;
        }

        // Get yearly OUT totals for these products
        $yq2 = DB::connection('ledger')
            ->table('product_ledgers')
            ->select('product_id', DB::raw('SUM(quantity_out) as total_out'))
            ->whereIn('product_id', $pids)
            ->where('quantity_out', '>', 0);

        if ($this->startDate) {
            $yq2->where('date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $yq2->where('date', '<=', $this->endDate);
        }

        $yearlyOut = $yq2->groupBy('product_id')
            ->get();

        $count = 0;
        foreach ($yearlyOut as $row) {
            if ((int) $row->total_out <= $this->threshold) {
                $count++;
            }
        }

        // Products with zero OUT also qualify
        $hasOut = $yearlyOut->pluck('product_id')->map('intval')->toArray();
        $noOutCount = count(array_diff($pids, $hasOut));

        return $count + $noOutCount;
    }
}
