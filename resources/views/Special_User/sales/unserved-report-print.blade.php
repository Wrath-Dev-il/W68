<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partial Unserved Details</title>
    {{-- W68_UNSERVED_VISIBILITY_PRINT_FIX_20260918 --}}
    <link rel="stylesheet" href="{{ asset('css/unserved-report.css') }}?v={{ @filemtime(public_path('css/unserved-report.css')) ?: time() }}">

    {{--
        W68_UNSERVED_PORTRAIT_12PX_FIX_20260921

        Shared print view for Admin, Regular and Special users.

        Fixes:
        - Force Letter PORTRAIT.
        - Table body text = 12px.
        - Customer details = 12px.
        - Keep CUSTOMER TOTAL inside tbody (not tfoot) to avoid giant blank
          customer-total areas during Chromium print/PDF pagination.
        - Customer sections may continue naturally across pages.
        - Table headers repeat when a customer table spans pages.
    --}}
    <style>
        @media print {
            @page {
                size: Letter portrait;
                margin: 5mm;
            }

            html,
            body.unserved-print-body {
                width: 100%;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .unserved-print-sheet {
                width: 100%;
                max-width: none;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box;
            }

            .unserved-print-main-header {
                margin: 0 0 2mm !important;
            }

            .unserved-print-header h1 {
                margin: 0 !important;
                font-size: 18px !important;
                line-height: 1.1 !important;
            }

            .unserved-print-header h2 {
                margin: 1mm 0 .5mm !important;
                font-size: 14px !important;
                line-height: 1.1 !important;
            }

            .unserved-print-header p {
                margin: .5mm 0 !important;
                font-size: 11px !important;
                line-height: 1.15 !important;
            }

            .unserved-print-customer-section {
                width: 100%;
                margin: 0 0 2mm !important;
                padding: 0 !important;
                border: 0 !important;
                break-inside: auto !important;
                page-break-inside: auto !important;
            }

            .unserved-print-customer-section + .unserved-print-customer-section {
                margin-top: 2mm !important;
                padding-top: 1mm !important;
                border-top: 1px solid #000 !important;
                break-before: auto !important;
                page-break-before: auto !important;
            }

            .unserved-print-customer-details {
                width: 100% !important;
                max-width: none !important;
                margin: 0 0 1mm !important;
                padding: .8mm 1mm !important;
                gap: .4mm 3mm !important;
                grid-template-columns: 1fr 1fr !important;
                box-sizing: border-box !important;
                border-top: 1px solid #000 !important;
                border-bottom: 1px solid #000 !important;
                font-size: 12px !important;
                line-height: 1.15 !important;
                break-after: avoid !important;
                page-break-after: avoid !important;
            }

            .unserved-print-customer-details > div:nth-child(2) {
                text-align: right !important;
            }

            .unserved-print-customer-details .terms {
                grid-column: 1 / -1 !important;
                text-align: left !important;
            }

            .unserved-print-table {
                width: 100% !important;
                margin: 0 !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
                font-size: 12px !important;
            }

            .unserved-print-table thead {
                display: table-header-group !important;
            }

            .unserved-print-table tbody {
                display: table-row-group !important;
            }

            .unserved-print-table th {
                border: 1px solid #000 !important;
                padding: .8mm .55mm !important;
                font-size: 10px !important;
                line-height: 1.1 !important;
                text-align: center !important;
                font-weight: 800 !important;
                background: #eee !important;
                vertical-align: middle !important;
                overflow-wrap: anywhere !important;
                word-break: normal !important;
            }

            .unserved-print-table td {
                border: 1px solid #000 !important;
                padding: .8mm .55mm !important;
                font-size: 12px !important;
                line-height: 1.15 !important;
                vertical-align: top !important;
                overflow-wrap: anywhere !important;
                word-break: normal !important;
                height: auto !important;
                min-height: 0 !important;
            }

            .unserved-print-table tr {
                height: auto !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            /*
             * 11-column Letter portrait allocation.
             * Product Code / Part No. / Description get the most room.
             */
            .unserved-print-table th:nth-child(1)  { width: 8% !important; }
            .unserved-print-table th:nth-child(2)  { width: 10% !important; }
            .unserved-print-table th:nth-child(3)  { width: 8% !important; }
            .unserved-print-table th:nth-child(4)  { width: 16% !important; }
            .unserved-print-table th:nth-child(5)  { width: 11% !important; }
            .unserved-print-table th:nth-child(6)  { width: 15% !important; }
            .unserved-print-table th:nth-child(7)  { width: 5% !important; }
            .unserved-print-table th:nth-child(8)  { width: 5% !important; }
            .unserved-print-table th:nth-child(9)  { width: 5% !important; }
            .unserved-print-table th:nth-child(10) { width: 8% !important; }
            .unserved-print-table th:nth-child(11) { width: 9% !important; }

            .unserved-print-table .customer-total-row {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .unserved-print-table .customer-total-row td {
                height: auto !important;
                min-height: 0 !important;
                padding: .8mm .55mm !important;
                font-size: 12px !important;
                line-height: 1.15 !important;
                background: #f3f3f3 !important;
                vertical-align: middle !important;
            }

            .unserved-print-table .customer-total-label {
                text-align: right !important;
                font-weight: 800 !important;
            }

            .unserved-print-table td.num {
                text-align: right !important;
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
            }

            .unserved-print-table td.strong {
                font-weight: 800 !important;
            }

            .unserved-print-footer {
                display: flex !important;
                flex-wrap: wrap !important;
                justify-content: flex-end !important;
                gap: 1.5mm 4mm !important;
                margin-top: 2mm !important;
                font-size: 11px !important;
                line-height: 1.15 !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .unserved-print-footer span {
                padding-top: .6mm !important;
            }

            .unserved-print-body,
            .unserved-print-table tr.rush-row td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="unserved-print-body">
    @include('partials.global.w68-loader')

    <main class="unserved-print-sheet">
        <header class="unserved-print-header unserved-print-main-header">
            <h1>W68 AUTOPARTS AND SERVICE CENTER</h1>
            <h2>PARTIAL UNSERVED</h2>
            {{-- W68_UNSERVED_ALL_USERS_PARTIAL_FIX_20260918 --}}
            <p>GENERATED: {{ strtoupper($generatedAt) }} &nbsp; | &nbsp; DATE: {{ strtoupper($periodLabel) }}</p>
            @if($salesman)
                <p class="unserved-print-filters">SALES MAN: {{ strtoupper($salesman) }}</p>
            @endif
        </header>

        @forelse($customerGroups as $group)
            <section class="unserved-print-customer-section">
                <div class="unserved-print-customer-details">
                    <div><strong>CUSTOMER:</strong> {{ strtoupper($group['customer']['name'] ?? '—') }}</div>
                    <div><strong>TIN:</strong> {{ strtoupper($group['customer']['tin'] ?? '—') }}</div>
                    <div class="terms"><strong>TERMS:</strong> {{ strtoupper($group['customer']['terms'] ?? '—') }}</div>
                </div>

                <table class="unserved-print-table">
                    <thead>
                        <tr>
                            <th>S.O. NO.</th>
                            <th>NAME</th>
                            <th>DATE</th>
                            <th>PRODUCT CODE</th>
                            <th>PART NO.</th>
                            <th>DESCRIPTION</th>
                            <th>ON HAND</th>
                            <th>SERVED</th>
                            <th>UNSERVED</th>
                            <th>UNIT PRICE</th>
                            <th>TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['rows'] as $row)
                            <tr class="{{ !empty($row['is_rush']) ? 'rush-row' : '' }}">
                                <td>{{ $row['so_no'] }}</td>
                                <td>{{ $row['customer'] }}</td>
                                <td>{{ $row['order_date'] }}</td>
                                <td>{{ $row['product_code'] }}</td>
                                <td>{{ $row['part_number'] }}</td>
                                <td>{{ $row['description'] }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $row['on_hand'], 2, '.', ','), '0'), '.') }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $row['served'], 2, '.', ','), '0'), '.') }}</td>
                                <td class="num strong">{{ rtrim(rtrim(number_format((float) $row['unserved'], 2, '.', ','), '0'), '.') }}</td>
                                <td class="num">{{ number_format((float) $row['unit_price'], 2) }}</td>
                                <td class="num strong">{{ number_format((float) $row['total_amount'], 2) }}</td>
                            </tr>
                        @endforeach

                        <tr class="customer-total-row">
                            <td colspan="8" class="customer-total-label">CUSTOMER TOTAL</td>
                            <td class="num strong">{{ rtrim(rtrim(number_format((float) ($group['summary']['total_unserved'] ?? 0), 2, '.', ','), '0'), '.') }}</td>
                            <td></td>
                            <td class="num strong">{{ number_format((float) ($group['summary']['total_amount'] ?? 0), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        @empty
            <section class="unserved-print-customer-section">
                <div class="unserved-print-customer-details">
                    <div><strong>CUSTOMER:</strong> {{ strtoupper($customerDetails['name'] ?? 'ALL CUSTOMERS') }}</div>
                    <div><strong>TIN:</strong> {{ strtoupper($customerDetails['tin'] ?? '—') }}</div>
                    <div class="terms"><strong>TERMS:</strong> {{ strtoupper($customerDetails['terms'] ?? '—') }}</div>
                </div>

                <table class="unserved-print-table">
                    <thead>
                        <tr>
                            <th>S.O. NO.</th>
                            <th>NAME</th>
                            <th>DATE</th>
                            <th>PRODUCT CODE</th>
                            <th>PART NO.</th>
                            <th>DESCRIPTION</th>
                            <th>ON HAND</th>
                            <th>SERVED</th>
                            <th>UNSERVED</th>
                            <th>UNIT PRICE</th>
                            <th>TOTAL AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="11" class="empty">No unserved items found for the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        @endforelse

        <footer class="unserved-print-footer">
            <span>Partial Notes: {{ number_format($summary['partial_notes'] ?? $summary['open_partial_notes'] ?? 0) }}</span>
            <span>Lines: {{ number_format($summary['line_items']) }}</span>
            <span>Unserved With Stocks: {{ rtrim(rtrim(number_format((float) ($summary['unserved_with_stock_qty'] ?? 0), 2, '.', ','), '0'), '.') }}</span>
            <span>Total Unserved: {{ rtrim(rtrim(number_format((float) $summary['total_unserved'], 2, '.', ','), '0'), '.') }}</span>
            <span>Total Amount: {{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</span>
        </footer>
    </main>

    <script src="{{ asset('js/unserved-report-print.js') }}?v={{ @filemtime(public_path('js/unserved-report-print.js')) ?: time() }}"></script>
</body>
</html>
