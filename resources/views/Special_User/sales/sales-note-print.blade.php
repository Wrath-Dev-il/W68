<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Note Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial Rounded MT Bold', Arial, sans-serif; font-size: 13px; color: #000; padding: 5px; font-weight: bold; line-height: 1; }
        .report-title { text-align: center; font-size: 18px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; margin-bottom: 3px; }
        .header { width: 100%; margin-bottom: 10px; font-size: 16px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; }
        table.items { width: 100% !important; border-collapse: collapse; margin-bottom: 5px; table-layout: fixed; }
        table.items th { border: 1px solid #000 !important; padding: 2px 1px !important; font-size: 13px; font-weight: bold; font-family: 'Arial Black', Arial, sans-serif; text-align: center; text-transform: uppercase; background: #fff; word-wrap: break-word; overflow-wrap: break-word; }
        table.items td { border: 1px solid #000 !important; padding: 1px 1px !important; font-size: 11px; font-weight: bold; font-family: 'Arial Rounded MT Bold', Arial, sans-serif; word-wrap: break-word; overflow-wrap: break-word; white-space: normal; }
        table.items td.c { text-align: center; }
        table.items td.r { text-align: right; }
        .footer { margin-top: 50px; display: flex; justify-content: space-between; }
        .footer .sig { text-align: center; width: 30%; }
        .footer .sig .line { border-top: 1px solid #000; width: 80%; margin: 0 auto 5px auto; }
        .footer .sig span { font-size: 12px; text-transform: uppercase; font-weight: bold; font-family: 'Arial Black', 'Arial', sans-serif; }
        .totals { text-align: right; margin-top: 10px; font-size: 13px; font-weight: bold; font-family: 'Arial Rounded MT Bold', 'Arial', sans-serif; }
        .remarks { margin-top: 10px; font-size: 12px; font-weight: bold; font-family: 'Arial Rounded MT Bold', 'Arial', sans-serif; }
        .page-break { page-break-after: always; }
        @media print { 
            body { padding: 2px; margin: 0; } 
            @page { margin: 5mm; } 
            .no-print { display: none !important; }
            table.items { page-break-inside: auto; }
            table.items thead { display: table-header-group; }
            table.items tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    @include('partials.global.w68-loader')

    <div id="printable-content">
    @foreach($notes as $note)
    <div style="text-align:center;font-size:20px;font-weight:bold;font-family:'Arial Black','Arial',sans-serif;margin-bottom:10px;">W68 AUTOPARTS &amp; SERVICE CENTER</div>
    <div class="report-title">SALES NOTE REPORT</div>
    <div style="height:10px;"></div>

    <div class="header" style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
            <div style="font-size:16px;font-weight:bold;font-family:'Arial Black','Arial',sans-serif;">CUSTOMER: {{ $note['customer_name'] }}</div>
            <div style="font-size:16px;font-weight:bold;font-family:'Arial Black','Arial',sans-serif;margin-top:2px;">ADDRESS: {{ $note['customer_address'] }}</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:16px;font-weight:bold;font-family:'Arial Black','Arial',sans-serif;">SN No.: {{ $note['sales_number'] }}</div>
            <div style="font-size:16px;font-weight:bold;font-family:'Arial Black','Arial',sans-serif;margin-top:2px;">SN DATE: {{ $note['order_date'] }}</div>
        </div>
    </div>

    @php
        // Year range is now passed from the route controller
    @endphp
    <table class="items">
        <thead>
            <tr>
                <th style="width:5%" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">QTY</th>
                <th style="width:5%" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">UNIT</th>
                <th style="width:6%" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">ITEM CODE</th>
                <th style="width:8%" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">PART NUMBER</th>
                <th style="width:30%" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">DESCRIPTION</th>
                <th style="width:5%;font-size:11px" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">ON<br>HAND</th>
                <th style="width:6%;font-size:11px" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">UNIT PRICE</th>
                <th style="width:6%;font-size:11px" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">LATEST<br>COST</th>
                <th style="width:6%;font-size:11px" rowspan="{{ count($yearRange) > 0 ? 2 : 1 }}">PRICE<br>ONLINE</th>
                <th colspan="{{ count($yearRange) }}" style="width:{{ count($yearRange) * 5 }}%">OUT SALES</th>
            </tr>
            @if(count($yearRange) > 0)
            <tr>
                @foreach($yearRange as $yr)
                <th style="width:5%">{{ substr($yr, -2) }}</th>
                @endforeach
            </tr>
            @endif
        </thead>
        <tbody>
            @foreach($note['items'] as $item)
            @php
                $qtyLabel = $item['print_qty_label'] ?? null;
                $totalOut = (int) ($item['print_qty'] ?? ($item['quantity'] + ($item['additional_qty'] ?? 0)));
                $itemYear = $note['order_date'] ? date('Y', strtotime($note['order_date'])) : null;
            @endphp
            <tr>
                <td class="c">{{ $qtyLabel ? $qtyLabel : $totalOut }}</td>
                <td class="c">{{ $item['oum'] }}</td>
                <td>{{ $item['product_code'] }}</td>
                <td>{{ $item['part_number'] }}</td>
                <td>{{ $item['description'] }}</td>
                <td class="c">{{ $item['on_hand'] ?? 0 }}</td>
                <td class="r">{{ number_format($item['unit_price'], 2) }}</td>
                <td class="r">{{ number_format($item['cost'] ?? 0, 2) }}</td>
                <td class="r">{{ number_format($item['price_online'] ?? 0, 2) }}</td>
                @foreach($yearRange as $yr)
                <td class="c">{{ (!$qtyLabel && $itemYear == $yr) ? $totalOut : 0 }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">TOTAL: {{ number_format($note['net_total'], 2) }}</div>

    @if($note['remarks'])
    <div class="remarks">Remarks: {{ $note['remarks'] }}</div>
    @endif

    @if($paymentType !== 'none')
    <div style="text-align:center;margin-top:15px;margin-bottom:10px;">
        <span style="font-size:25px;font-family:'Arial Black','Arial',sans-serif;font-weight:bold;">
            @if($note['is_rush'])RUSH | @endif{{ $paymentType === 'cod-30' ? 'COD' : 'COD' }}
        </span>
        @if($paymentType === 'cod-30')
        <span style="font-size:20px;font-family:'Arial Black','Arial',sans-serif;font-weight:bold;"> 30 DAYS</span>
        @endif
    </div>
    @endif

    <div class="footer">
        <div class="sig">
            <div class="line"></div>
            <span>Prepared By</span>
            <p style="margin-top:3px;font-size:13px;">{{ $note['prepared_by'] ?? '' }}</p>
        </div>
        <div class="sig">
            <div class="line"></div>
            <span>Packed By</span>
            <p style="margin-top:3px;font-size:10px;">{{ $note['packed_by'] ?? '' }}</p>
        </div>
        <div class="sig">
            <div class="line"></div>
            <span>Checked By</span>
            <p style="margin-top:3px;font-size:10px;">{{ $note['checked_by'] ?? '' }}</p>
        </div>
    </div>

    <div class="page-break"></div>
    @endforeach
    </div>
    <div class="no-print" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:2px solid #800000;padding:10px 20px;display:flex;justify-content:center;gap:12px;z-index:999;box-shadow:0 -4px 20px rgba(0,0,0,0.08);">
        <button onclick="window.history.back()" style="padding:10px 24px;background:#fff;border:2px solid #e2e8f0;border-radius:10px;font-size:12px;font-weight:bold;color:#475569;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <span>&larr;</span> Go Back
        </button>
        <button onclick="window.print()" style="padding:10px 24px;background:#800000;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(128,0,0,0.3);display:flex;align-items:center;gap:6px;">
            Print Report
        </button>
        <button onclick="saveAsPDF()" id="pdf-btn" style="padding:10px 24px;background:#1e40af;border:none;border-radius:10px;font-size:12px;font-weight:bold;color:#fff;cursor:pointer;box-shadow:0 4px 12px rgba(30,64,175,0.3);display:flex;align-items:center;gap:6px;">
            Save as PDF
        </button>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer" class="no-print"></script>
    <script>
    window.onload = function() { window.print(); };
    function saveAsPDF() {
        const btn = document.getElementById('pdf-btn');
        btn.textContent = 'Generating PDF...';
        btn.disabled = true;
        const element = document.getElementById('printable-content');
        const opt = {
            margin:       [3, 3, 3, 3],
            filename:     'Sales-Note-Report-{{ $type === "note-unserved" ? "NOTE-UNSERVED" : "NOTE" }}.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true, 
                logging: false, 
                allowTaint: true,
                backgroundColor: '#ffffff',
                foreignObjectRendering: false,
                windowHeight: element.scrollHeight
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
        setTimeout(() => {
            btn.innerHTML = 'Save as PDF';
            btn.disabled = false;
        }, 1500);
    }
    </script>
</body>
</html>
