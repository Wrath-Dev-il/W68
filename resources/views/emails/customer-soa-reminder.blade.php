<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>W68 Statement of Account</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:680px;margin:0 auto;padding:28px 18px;">
        <div style="background:#4b0000;color:#f6d34a;padding:22px 26px;border-radius:14px 14px 0 0;">
            <div style="font-size:23px;font-weight:800;letter-spacing:.04em;">W68 AUTOPARTS &amp; SERVICE CENTER</div>
            <div style="font-size:12px;color:#fff;margin-top:6px;">Statement of Account — Payment Reminder</div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-top:0;padding:26px;border-radius:0 0 14px 14px;">
            <p style="margin:0 0 16px;">Dear <strong>{{ $customerName }}</strong>,</p>

            <p style="margin:0 0 16px;line-height:1.6;">
                This is an automatic payment reminder from W68 Autoparts &amp; Service Center.
                Your Statement of Account is attached as a PDF and includes your currently unpaid finalized invoices.
            </p>

            <table style="width:100%;border-collapse:collapse;margin:18px 0;">
                <tr>
                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Customer Terms</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;">{{ $terms ?: 'Not specified' }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Outstanding Balance</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;font-weight:800;">PHP {{ number_format((float) $totalBalance, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f8fafc;font-weight:700;">Unpaid Invoices on SOA</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;">{{ number_format((int) $invoiceCount) }}</td>
                </tr>
            </table>

            <p style="margin:0 0 16px;line-height:1.6;">
                Please review the attached Statement of Account and arrange payment according to your agreed terms.
                If payment has already been made, please disregard this reminder or contact W68 so the payment can be verified.
            </p>

            <p style="margin:24px 0 0;font-size:12px;color:#6b7280;line-height:1.6;">
                This message was generated automatically on {{ $generatedAt->format('F d, Y h:i A') }} (Asia/Manila).
                The attached PDF follows the W68 Sales Report Statement of Account format.
            </p>
        </div>
    </div>
</body>
</html>
