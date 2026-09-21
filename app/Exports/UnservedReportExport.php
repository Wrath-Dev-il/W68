<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class UnservedReportExport implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        private readonly array $report,
        private readonly array $filters = []
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach (($this->report['rows'] ?? []) as $row) {
            $soNo = trim((string) ($row['so_no'] ?? ''));
            $status = strtoupper(trim((string) ($row['status'] ?? '')));

            if ($status !== '') {
                $soNo .= ' [' . $status . ']';
            }

            $rows[] = [
                $this->safeText($soNo),
                $this->safeText($row['customer'] ?? ''),
                $this->safeText($row['order_date'] ?? ''),
                $this->safeText($row['product_code'] ?? ''),
                $this->safeText($row['part_number'] ?? ''),
                $this->safeText($row['description'] ?? ''),
                (float) ($row['on_hand'] ?? 0),
                (float) ($row['served'] ?? 0),
                (float) ($row['unserved'] ?? 0),
                (float) ($row['unit_price'] ?? 0),
                (float) ($row['total_amount'] ?? 0),
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Unserved Report';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $dataCount = count($this->report['rows'] ?? []);

                // Reserve rows for title/filter information + header.
                $sheet->insertNewRowBefore(1, 6);

                $heading = match (strtolower((string) ($this->filters['status_filter'] ?? 'all'))) {
                    'open' => 'OPEN UNSERVED',
                    'partial' => 'PARTIAL UNSERVED',
                    default => 'OPEN/PARTIAL UNSERVED',
                };

                $sheet->setCellValue('A1', 'W68 AUTOPARTS AND SERVICE CENTER');
                $sheet->mergeCells('A1:K1');

                $sheet->setCellValue('A2', $heading);
                $sheet->mergeCells('A2:K2');

                $sheet->setCellValue(
                    'A3',
                    'PERIOD: ' . strtoupper((string) ($this->report['period_label'] ?? ''))
                    . '    |    GENERATED: '
                    . strtoupper((string) ($this->report['generated_at'] ?? ''))
                );
                $sheet->mergeCells('A3:K3');

                $sheet->setCellValue('A4', $this->filterSummary());
                $sheet->mergeCells('A4:K4');

                $headers = [
                    'S.O. NO. / STATUS',
                    'NAME',
                    'DATE',
                    'PRODUCT CODE',
                    'PART NO.',
                    'DESCRIPTION',
                    'ON HAND',
                    'SERVED',
                    'UNSERVED',
                    'UNIT PRICE',
                    'TOTAL AMOUNT',
                ];

                foreach ($headers as $index => $header) {
                    $column = chr(ord('A') + $index);
                    $sheet->setCellValue($column . '6', $header);
                }

                $sheet->getStyle('A1:K1')->getFont()
                    ->setBold(true)
                    ->setSize(16)
                    ->getColor()->setRGB('000000');

                $sheet->getStyle('A2:K2')->getFont()
                    ->setBold(true)
                    ->setSize(13)
                    ->getColor()->setRGB('000000');

                $sheet->getStyle('A3:K4')->getFont()
                    ->setSize(10)
                    ->getColor()->setRGB('000000');

                $sheet->getStyle('A1:K4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('A6:K6')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4A0612'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                $dataStart = 7;

                if ($dataCount > 0) {
                    $lastRow = $dataStart + $dataCount - 1;

                    $sheet->getStyle("A{$dataStart}:K{$lastRow}")->applyFromArray([
                        'font' => [
                            'size' => 11,
                            'color' => ['rgb' => '000000'],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_TOP,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                    $sheet->getStyle("A{$dataStart}:F{$lastRow}")
                        ->getAlignment()
                        ->setWrapText(true);

                    $sheet->getStyle("G{$dataStart}:K{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("G{$dataStart}:I{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.##');

                    $sheet->getStyle("J{$dataStart}:K{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');

                    // Rush rows remain red, matching print behavior.
                    foreach (($this->report['rows'] ?? []) as $index => $row) {
                        if (!empty($row['is_rush'])) {
                            $excelRow = $dataStart + $index;

                            $sheet->getStyle("A{$excelRow}:K{$excelRow}")
                                ->getFont()
                                ->getColor()
                                ->setRGB('DC2626');
                        }
                    }

                    $sheet->setAutoFilter("A6:K{$lastRow}");
                } else {
                    $sheet->setCellValue(
                        'A7',
                        'No unserved items found for the selected filters.'
                    );
                    $sheet->mergeCells('A7:K7');
                    $sheet->getStyle('A7:K7')->getFont()
                        ->setSize(11)
                        ->getColor()->setRGB('000000');
                    $sheet->getStyle('A7:K7')->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $widths = [
                    'A' => 22,
                    'B' => 30,
                    'C' => 14,
                    'D' => 22,
                    'E' => 22,
                    'F' => 45,
                    'G' => 12,
                    'H' => 12,
                    'I' => 12,
                    'J' => 15,
                    'K' => 16,
                ];

                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }

                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(21);
                $sheet->getRowDimension(6)->setRowHeight(28);

                $sheet->freezePane('A7');

                $pageSetup = $sheet->getPageSetup();
                $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LETTER);
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
            },
        ];
    }

    private function filterSummary(): string
    {
        $customer = trim((string) ($this->filters['customer'] ?? ''));
        $salesman = trim((string) ($this->filters['salesman'] ?? ''));

        $status = match (strtolower((string) ($this->filters['status_filter'] ?? 'all'))) {
            'open' => 'OPEN',
            'partial' => 'PARTIAL',
            default => 'ALL',
        };

        $stock = match (strtolower((string) ($this->filters['stock_filter'] ?? 'all'))) {
            'with' => 'WITH STOCK',
            'without' => 'WITHOUT STOCK',
            default => 'ALL',
        };

        $rush = match (strtolower((string) ($this->filters['rush_filter'] ?? 'all'))) {
            'rush' => 'RUSH',
            'not-rush' => 'NOT RUSH',
            default => 'ALL',
        };

        return 'CUSTOMER: ' . strtoupper($customer !== '' ? $customer : 'ALL')
            . '    |    SALES MAN: ' . strtoupper($salesman !== '' ? $salesman : 'ALL')
            . '    |    STATUS: ' . $status
            . '    |    STOCK: ' . $stock
            . '    |    RUSH: ' . $rush;
    }

    private function safeText(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        // Prevent spreadsheet formula injection from imported/user-entered text.
        if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}