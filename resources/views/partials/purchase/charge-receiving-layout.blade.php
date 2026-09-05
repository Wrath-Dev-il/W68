<style>
    .charge-receiving-layout {
        font-family: Arial, sans-serif;
        padding: 30px;
        font-size: 11px;
        max-width: 8.5in;
        margin: 0 auto;
        background: white;
    }

    .charge-receiving-layout .header {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #000;
    }

    .charge-receiving-layout .header h1 {
        font-size: 18px;
        font-weight: bold;
        margin: 0 0 5px 0;
        letter-spacing: 0.5px;
    }

    .charge-receiving-layout .header .title {
        font-size: 16px;
        font-weight: bold;
        margin-top: 15px;
        text-decoration: underline;
        letter-spacing: 1px;
    }

    .charge-receiving-layout .info-row {
        display: flex;
        margin-bottom: 10px;
        align-items: center;
    }

    .charge-receiving-layout .info-group {
        display: flex;
        align-items: center;
        flex: 1;
    }

    .charge-receiving-layout .info-label {
        font-weight: bold;
        white-space: nowrap;
        font-size: 11px;
        margin-right: 10px;
    }

    .charge-receiving-layout .info-value {
        border-bottom: 1px solid #000;
        padding: 2px 5px;
        flex: 1;
        min-height: 20px;
        font-size: 11px;
    }

    .charge-receiving-layout .separator {
        border-top: 2px solid #000;
        margin: 15px 0;
    }

    .charge-receiving-layout table.items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    
    .charge-receiving-layout table.items-table tbody {
        min-height: 200px; /* Maintain visual height even with few rows */
    }

    .charge-receiving-layout table.items-table th,
    .charge-receiving-layout table.items-table td {
        border: 1px solid #000;
        padding: 6px 8px;
        text-align: left;
    }

    .charge-receiving-layout table.items-table th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
        font-size: 10px;
        letter-spacing: 0.3px;
    }

    .charge-receiving-layout table.items-table td {
        font-size: 10px;
    }

    .charge-receiving-layout .text-right {
        text-align: right;
    }

    .charge-receiving-layout .text-center {
        text-align: center;
    }

    .charge-receiving-layout .totals-section {
        margin-top: 15px;
        text-align: right;
        font-size: 11px;
        font-weight: bold;
    }

    .charge-receiving-layout .totals-section > div {
        margin: 8px 0;
        padding: 3px 0;
    }

    .charge-receiving-layout .particular-section {
        margin-top: 25px;
        margin-bottom: 30px;
    }

    .charge-receiving-layout .particular-label {
        font-weight: bold;
        margin-bottom: 8px;
        font-size: 11px;
    }

    .charge-receiving-layout .particular-content {
        border: 1px solid #000;
        padding: 12px;
        min-height: 70px;
    }

    .charge-receiving-layout .signatures {
        display: table;
        width: 100%;
        margin-top: 50px;
    }

    .charge-receiving-layout .signature-cell {
        display: table-cell;
        text-align: center;
        width: 33.33%;
        padding: 10px;
    }

    .charge-receiving-layout .signature-line {
        border-top: 1px solid #000;
        margin-bottom: 8px;
        padding-top: 40px;
    }

    .charge-receiving-layout .signature-label {
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
</style>

<div class="charge-receiving-layout">
    <!-- Header -->
    <div class="header">
        <h1>W68 AUTO PARTS & SERVICE CENTER</h1>
        <div class="title">CHARGE RECEIVING</div>
    </div>

    <!-- Invoice and Receiving Info -->
    <div style="margin-bottom: 20px;">
        <div class="info-row">
            <div class="info-group" style="flex: 1;">
                <div class="info-label">Invoice No.</div>
                <div class="info-value" id="cr-invoice-no" style="flex: 1;"></div>
            </div>
            <div style="width: 30px;"></div>
            <div class="info-group" style="flex: 1;">
                <div class="info-label">Receiving No.</div>
                <div class="info-value" id="cr-receiving-no" style="flex: 1;"></div>
            </div>
        </div>
        
        <div class="info-row">
            <div class="info-group" style="flex: 1;">
                <div class="info-label">Supplier:</div>
                <div class="info-value" id="cr-supplier-name" style="flex: 1;"></div>
            </div>
            <div style="width: 30px;"></div>
            <div class="info-group" style="flex: 1;">
                <div class="info-label">Date:</div>
                <div class="info-value" id="cr-date" style="flex: 1;"></div>
            </div>
        </div>
    </div>

    <div class="separator"></div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">QTY</th>
                <th style="width: 10%;">UNIT</th>
                <th style="width: 18%;">PART NO.</th>
                <th style="width: 28%;">DESCRIPTION</th>
                <th style="width: 12%;">UNIT PRICE</th>
                <th style="width: 10%;">DISC%</th>
                <th style="width: 14%;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody id="cr-items-tbody">
            <!-- Items will be populated here -->
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <div class="separator"></div>

    <!-- Totals -->
    <div class="totals-section">
        <div style="border-top: 1px solid #ccc; padding-top: 5px; margin-top: 5px;">SUBTOTAL: <span id="cr-subtotal">₱ 0.00</span></div>
        <div style="color: #d97706; font-weight: bold;">ADDITIONAL DISCOUNT: <span id="cr-additional-discount">0.00%</span></div>
        <div style="border-top: 2px solid #000; padding-top: 5px; margin-top: 5px; font-size: 13px;">NET TOTAL: <span id="cr-net-total">₱ 0.00</span></div>
    </div>

    <!-- Particular -->
    <div class="particular-section">
        <div class="particular-label">PARTICULAR:</div>
        <div class="particular-content" id="cr-particular">
        </div>
    </div>

    <!-- Signatures -->
    <div class="signatures">
        <div class="signature-cell">
            <div class="signature-line"></div>
            <div class="signature-label">PREPARED BY</div>
        </div>
        <div class="signature-cell">
            <div class="signature-line"></div>
            <div class="signature-label">CHECKED BY</div>
        </div>
        <div class="signature-cell">
            <div class="signature-line"></div>
            <div class="signature-label">APPROVED BY</div>
        </div>
    </div>
</div>
