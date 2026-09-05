<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProductPriceListExport implements FromCollection, WithEvents
{
    public function __construct(private readonly array $filters = [])
    {
    }

    public function collection(): Collection
    {
        $products = $this->query()->get();
        $rows = [];
        $lastDesc = null;

        foreach ($products as $product) {
            $desc = strtoupper(trim($this->cleanText($product->description) ?: 'NO DESCRIPTION'));

            // Insert description group row when description changes
            if ($desc !== $lastDesc) {
                $rows[] = [$desc, '', '', '', ''];
                $lastDesc = $desc;
            }

            // Application + Position combined per print layout
            $app = $this->cleanText($product->application ?: $product->Application ?? '');
            $pos = $this->cleanText($product->position ?: $product->Position ?? '');

            $appPos = '';
            if ($app && $pos) {
                $appPos = $app . "\nPOS: " . $pos;
            } elseif ($app) {
                $appPos = $app;
            } elseif ($pos) {
                $appPos = "POS: " . $pos;
            }

            $rows[] = [
                $this->cleanText($product->product_code),
                $this->cleanText($product->part_number),
                $appPos,
                $this->cleanText($product->category),
                (float) ($product->selling_price ?? 0),
            ];
        }

        return collect($rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ── 1. Title rows ──
                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'W68 AUTOPARTS & SERVICE CENTER');
                $sheet->mergeCells('A1:E1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->setCellValue('A2', 'PRICE LIST');
                $sheet->mergeCells('A2:E2');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->setCellValue('A3', strtoupper(now()->format('M d, Y')));
                $sheet->mergeCells('A3:E3');
                $sheet->getStyle('A3')->getFont()->setSize(11);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Data rows start at row 4
                $dataStartRow = 4;
                $lastRow = $sheet->getHighestRow();

                // ── 2. Style each data row ──
                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    $valA = $sheet->getCell("A{$row}")->getValue();
                    $valB = $sheet->getCell("B{$row}")->getValue();
                    $valC = $sheet->getCell("C{$row}")->getValue();
                    $valD = $sheet->getCell("D{$row}")->getValue();

                    // Description group row has text in A, empty on columns B-E
                    $isDescGroup = $valA !== null
                        && $valA !== ''
                        && ($valB === null || $valB === '')
                        && ($valC === null || $valC === '')
                        && ($valD === null || $valD === '');

                    if ($isDescGroup) {
                        // Full-width bold group header matching print layout
                        $sheet->mergeCells("A{$row}:E{$row}");
                        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
                        $sheet->getStyle("A{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                            ->setVertical(Alignment::VERTICAL_CENTER);
                        $sheet->getStyle("A{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F1F5F9');
                        $sheet->getRowDimension($row)->setRowHeight(24);
                        // Medium top border, thin bottom to separate from items
                        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                            'borders' => [
                                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                                'left'   => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                                'right'  => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                            ],
                        ]);
                    } else {
                        // Item row — thin borders all around like print
                        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
                            'borders' => [
                                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                            ],
                        ]);
                    }

                    // Price (E) — right-aligned with peso format (like ₱#,##0.00 in print)
                    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Wrap text for Part No (B) and App+Pos (C) like print layout
                    $sheet->getStyle("B{$row}")->getAlignment()->setWrapText(true);
                    $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
                }

                // ── 3. Column widths match print proportions ──
                $sheet->getColumnDimension('A')->setWidth(18);  // Product Code   (15%)
                $sheet->getColumnDimension('B')->setWidth(22);  // Part No.       (20%)
                $sheet->getColumnDimension('C')->setWidth(40);  // App + Position (35%)
                $sheet->getColumnDimension('D')->setWidth(18);  // Category       (15%)
                $sheet->getColumnDimension('E')->setWidth(18);  // Price          (15%)

                // ── 4. Freeze pane below titles ──
                $sheet->freezePane('A4');

                // ── 5. Page setup — landscape, A4, fit to width ──
                $ps = $sheet->getPageSetup();
                $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
                $ps->setFitToWidth(1);
                $ps->setFitToHeight(0);
            },
        ];
    }

    private function query()
    {
        $query = Product::where('is_selected_for_report', true);

        if ($description = $this->filterValue('description')) {
            $query->where('description', 'like', '%' . $this->escapeLike($description) . '%');
        }

        if ($brand = $this->filterValue('brand')) {
            $noSpace = str_replace(' ', '', $this->escapeLike($brand));
            $query->whereRaw('REPLACE(category, " ", "") LIKE ?', ['%' . $noSpace . '%']);
        }

        if ($application = $this->filterValue('application')) {
            $query->where('application', 'like', '%' . $this->escapeLike($application) . '%');
        }

        if ($year = $this->filterValue('year')) {
            $query->whereYear('created_at', (int) $year);
        }

        return $query
            ->orderBy('description', 'asc')
            ->orderBy('category', 'asc')
            ->orderBy('application', 'asc')
            ->orderBy('product_code', 'asc');
    }

    private function filterValue(string $key): ?string
    {
        $value = trim((string) ($this->filters[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function cleanText(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));

        return in_array(strtolower($text), ['null', '---', 'n/a'], true) ? '' : $text;
    }
}
