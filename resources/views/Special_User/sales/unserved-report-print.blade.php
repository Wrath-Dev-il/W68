<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unserved Details</title>
    <link rel="stylesheet" href="{{ asset('css/unserved-report.css') }}?v={{ @filemtime(public_path('css/unserved-report.css')) ?: time() }}">
</head>
<body class="unserved-print-body">
    @include('partials.global.w68-loader')

    <main class="unserved-print-sheet">
        <header class="unserved-print-header unserved-print-main-header">
            <h1>W68 AUTOPARTS AND SERVICE CENTER</h1>
            <h2>UNSERVED</h2>
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
                            <tr>
                                <td>{{ $row['so_no'] }}</td>
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
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="customer-total-label">CUSTOMER TOTAL</td>
                            <td class="num strong">{{ rtrim(rtrim(number_format((float) ($group['summary']['total_unserved'] ?? 0), 2, '.', ','), '0'), '.') }}</td>
                            <td></td>
                            <td class="num strong">{{ number_format((float) ($group['summary']['total_amount'] ?? 0), 2) }}</td>
                        </tr>
                    </tfoot>
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
                        <tr><td colspan="9" class="empty">No unserved items found for the selected filters.</td></tr>
                    </tbody>
                </table>
            </section>
        @endforelse

        <footer class="unserved-print-footer">
            <span>Open / Partial Notes: {{ number_format($summary['open_partial_notes']) }}</span>
            <span>Lines: {{ number_format($summary['line_items']) }}</span>
            <span>Total Unserved: {{ rtrim(rtrim(number_format((float) $summary['total_unserved'], 2, '.', ','), '0'), '.') }}</span>
            <span>Total Amount: {{ number_format((float) ($summary['total_amount'] ?? 0), 2) }}</span>
        </footer>
    </main>

    <script src="{{ asset('js/unserved-report-print.js') }}?v={{ @filemtime(public_path('js/unserved-report-print.js')) ?: time() }}"></script>
</body>
</html>
