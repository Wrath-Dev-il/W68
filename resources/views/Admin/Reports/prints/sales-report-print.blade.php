<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Statement of Account</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #000;
            line-height: 1.3;
        }
        .page {
            page-break-after: always;
            display: flex;
            flex-direction: column;
            min-height: 267mm;
        }
        .page:last-child {
            page-break-after: auto;
        }

        .statement-table-wrapper {
            flex: 1;
        }

        /* Company Header */
        .company-header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 6px;
            padding-bottom: 4px;
            border-bottom: 2px solid #000;
        }
        .company-header .company-name {
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .company-header .company-address {
            font-size: 15px;
            font-weight: normal;
            margin-top: 2px;
        }
        .company-header .company-contact {
            font-size: 15px;
            font-weight: normal;
            margin-top: 1px;
        }
        .company-header .statement-title {
            font-size: 15px;
            margin-top: 3px;
            text-transform: uppercase;
        }
        .company-header .soa-date {
            font-size: 13px;
            font-weight: normal;
            margin-top: 2px;
        }

        /* Date Range */
        .date-range {
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            margin: 4px 0 6px 0;
        }

        /* Customer Box */
        .customer-box {
            border: 2px solid #000;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 15px;
        }
        .customer-box .customer-name {
            font-size: 15px;
            text-transform: uppercase;
        }
        .customer-box .customer-code {
            margin-top: 1px;
        }

        /* Table */
        table.soa-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        table.soa-table thead th {
            border-bottom: 2px solid #000;
            border-top: 2px solid #000;
            padding: 4px 4px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 15px;
        }
        table.soa-table thead th.right {
            text-align: right;
        }
        table.soa-table tbody td {
            border-bottom: 1px solid #ccc;
            padding: 3px 4px;
            vertical-align: top;
            font-size: 13px;
            font-weight: bold;
        }
        table.soa-table tbody td.right {
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
        }
        table.soa-table tbody td.mono {
            font-family: 'Courier New', Courier, monospace;
        }
        table.soa-table th.col-remarks,
        table.soa-table td.col-remarks {
            padding-left: 14px;
        }

        /* Payment Summary */
        .payment-summary {
            text-align: right;
            font-weight: bold;
            font-size: 15px;
            padding-top: 4px;
            border-top: 1px solid #000;
        }
        .payment-summary .amount {
            font-size: 15px;
        }

        /* Payment Instruction Footer */
        .payment-instruction {
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            padding-top: 6px;
            border-top: 1px solid #000;
        }

        /* Page Footer */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 13px;
            text-align: left;
            border-top: 1px solid #999;
            padding-top: 3px;
            margin-top: 6px;
        }
        .page-footer .page-number {
            float: right;
        }

        /* Generic helpers */
        .clearfix::after {
            content: '';
            display: table;
            clear: both;
        }


        /* Print-only editor. Changes are never submitted or saved. */
        #sales-report-print-document[contenteditable="true"] {
            outline: 2px dashed #2563eb;
            outline-offset: 6px;
            cursor: text;
        }
        #sales-report-print-document[contenteditable="true"]:focus {
            outline-color: #1d4ed8;
        }
        .edit-status {
            display: none;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 700;
            color: #92400e;
        }
        body.edit-mode .edit-status { display: inline; }

        @media screen {
            body { padding: 16px 16px 82px; }
        }
        @media print {
            .no-print { display: none !important; }
            #sales-report-print-document,
            #sales-report-print-document[contenteditable="true"] {
                outline: none !important;
            }
        }
    </style>
</head>
<body>
    @include('partials.global.w68-loader')


<main id="sales-report-print-document">
@foreach($pages as $page)
    <div class="page">
        <!-- Company Header -->
        <div class="company-header">
            <div class="company-name">W68 Autoparts &amp; Service Center</div>
            <div class="company-address">48 Timothy St. Multinational Village Parañaque City</div>
            <div class="company-contact">Tel. Nos. 8553-9092 / 8829-0480 Fax. No. 8846-3985 Mobile No. 09338137652 Mobile/Viber 09173239605</div>
            <div class="statement-title">STATEMENT OF ACCOUNT</div>
            <div class="soa-date">{{ date('F d, Y') }}</div>
        </div>

        <!-- Date Range -->
        <div class="date-range">{{ $dateRangeLabel }}</div>

        <!-- Customer Box (shown only on first page of each customer) -->
        @if($page['show_customer_box'])
            <div class="customer-box">
                <div class="customer-name">{{ $page['customer']['name'] }}</div>
                <div>{{ $page['customer']['address'] }}</div>
                <div class="customer-code">{{ str_pad($page['customer']['id'], 8, '0', STR_PAD_LEFT) }}</div>
            </div>
        @endif

        <!-- Transactions Table -->
        <div class="statement-table-wrapper">
        <table class="soa-table">
            <thead>
                <tr>
                    <th style="width: 105px;">Date</th>
                    <th style="width: 140px;">Invoice No.</th>
                    <th style="width: 85px;" class="right">Debits</th>
                    <th style="width: 85px;" class="right">Credits</th>
                    <th style="width: 85px;" class="right">Balance</th>
                    <th class="col-remarks">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($page['rows'] as $row)
                    <tr>
                        <td>{{ $row['date'] ? \Carbon\Carbon::parse($row['date'])->format('d-M-y') : '' }}</td>
                        <td class="mono">{{ $row['invoice_no'] }}</td>
                        <td class="right">{{ $row['debits'] > 0 ? number_format($row['debits'], 2) : '' }}</td>
                        <td class="right">{{ $row['credits'] > 0 ? number_format($row['credits'], 2) : '' }}</td>
                        <td class="right">{{ number_format($row['balance'], 2) }}</td>
                        <td class="col-remarks">{{ $row['remarks'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <!-- Payment Summary (shown only on last page of each customer) -->
        @if($page['show_payment_summary'])
            <div class="payment-summary">
                Please pay this amount &nbsp;&nbsp;&nbsp;&nbsp;
                <span class="amount">{{ number_format($page['total_balance'], 2) }}</span>
            </div>

            <div class="payment-instruction">
                Please make check payable only to<br>
                <strong>W68 AUTOPARTS &amp; SERVICE CENTER</strong>
            </div>
        @endif

        <!-- Page Footer -->
        <div class="page-footer"></div>
    </div>
@endforeach
</main>

<div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:10px 20px;display:flex;justify-content:center;align-items:center;gap:12px;z-index:9999;flex-wrap:wrap;font-family:Arial,sans-serif;">
    <button id="edit-print-btn" type="button" onclick="toggleEditMode()" style="padding:10px 24px;background:#0f766e;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Edit Print</button>
    <button id="reset-print-btn" type="button" onclick="resetPrint()" style="padding:10px 24px;background:#fff7ed;border:1px solid #fdba74;border-radius:10px;font-size:12px;font-weight:bold;color:#9a3412;cursor:pointer;">Reset</button>
    <span class="edit-status">EDIT MODE ON — click any report text and type. Changes are print-only.</span>
    <button type="button" onclick="printReport()" style="padding:10px 24px;background:#800000;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Print Report</button>
    <button id="export-pdf-btn" type="button" onclick="exportPdf()" style="padding:10px 24px;background:#1e40af;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;">Export PDF</button>
    <button type="button" onclick="window.close()" style="padding:10px 24px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:bold;color:#475569;cursor:pointer;">Close</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    let originalReportHtml = '';
    let reportEditMode = false;

    function setEditMode(enabled) {
        const report = document.getElementById('sales-report-print-document');
        const btn = document.getElementById('edit-print-btn');
        reportEditMode = !!enabled;

        report.setAttribute('contenteditable', reportEditMode ? 'true' : 'false');
        report.setAttribute('spellcheck', 'false');
        document.body.classList.toggle('edit-mode', reportEditMode);

        if (btn) {
            btn.textContent = reportEditMode ? 'Finish Editing' : 'Edit Print';
            btn.style.background = reportEditMode ? '#b45309' : '#0f766e';
        }

        if (reportEditMode) {
            report.focus();
        } else if (document.activeElement === report) {
            report.blur();
        }
    }

    function toggleEditMode() {
        setEditMode(!reportEditMode);
    }

    function resetPrint() {
        const report = document.getElementById('sales-report-print-document');
        report.innerHTML = originalReportHtml;
        setEditMode(false);
    }

    function printReport() {
        const wasEditing = reportEditMode;
        setEditMode(false);
        window.print();
        if (wasEditing) {
            setEditMode(true);
        }
    }

    function insertPlainText(event) {
        if (!reportEditMode) return;
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text/plain');
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return;
        selection.deleteFromDocument();
        const range = selection.getRangeAt(0);
        const node = document.createTextNode(text);
        range.insertNode(node);
        range.setStartAfter(node);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }

    window.onload = function () {
        const report = document.getElementById('sales-report-print-document');
        originalReportHtml = report.innerHTML;
        report.addEventListener('paste', insertPlainText);
        setEditMode(false);
    };

    function exportPdf() {
        const btn = document.getElementById('export-pdf-btn');
        const wasEditing = reportEditMode;
        const report = document.getElementById('sales-report-print-document');
        setEditMode(false);
        btn.textContent = 'Generating PDF...';
        btn.disabled = true;

        if (typeof html2pdf !== 'function') {
            btn.textContent = 'Export PDF';
            btn.disabled = false;
            if (wasEditing) setEditMode(true);
            alert('PDF library could not be loaded. You can still use Print Report and choose Save as PDF.');
            return;
        }

        const options = {
            margin: [12, 15, 12, 15],
            filename: 'statement-of-account.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak: { mode: ['css', 'legacy'], after: '.page' }
        };

        html2pdf().set(options).from(report).save().then(function () {
            btn.textContent = 'Export PDF';
            btn.disabled = false;
            if (wasEditing) setEditMode(true);
        }).catch(function () {
            btn.textContent = 'Export PDF';
            btn.disabled = false;
            if (wasEditing) setEditMode(true);
        });
    }
</script>

</body>
</html>
