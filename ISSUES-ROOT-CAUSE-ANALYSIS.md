# Root Cause Analysis - Two Issues
## Payable Cheque Voucher

**Date:** June 19, 2026

---

## ISSUE #1: Edit Voucher Save Fails - "Undefined array key purchase_no"

### ERROR DETAILS
- **URL:** `payable-cheque-voucher/update-new/27`
- **HTTP Status:** 500 Internal Server Error
- **Error Message:** `Undefined array key "purchase_no"`
- **Stack Trace:** `window.pcvSubmitEditedVoucher` in `Payable-Cheque-Voucher-Enhancement.js`

---

### ROOT CAUSE ANALYSIS

#### A. EXACT FILE
`public/js/Payable-Cheque-Voucher-Enhancement.js`

#### B. EXACT FUNCTION
`window.pcvSubmitEditedVoucher` (Line 515-579)

#### C. EXACT LINE NUMBERS WHERE PROBLEM OCCURS
**Line 524-530:** Invoice data collection

```javascript
// Gather invoice data
document.querySelectorAll('.edit-amount-paid').forEach(input => {
    const remarks = document.querySelector(`.edit-remarks[data-invoice-id="${input.dataset.invoiceId}"]`);
    editData.invoices.push({
        id: input.dataset.invoiceId,
        amount_paid: Number(input.value) || 0,
        remarks: remarks?.value || ''
    });
});
```

**PROBLEM:** Invoice payload only includes:
- `id`
- `amount_paid`
- `remarks`

**MISSING:** The backend expects:
- `purchase_no` ✅ REQUIRED
- `invoice_no` ✅ REQUIRED
- `invoice_amount`
- `amount_due`
- `purchase_order_id`
- `payment_status`

#### D. BACKEND EXPECTATION

**File:** `app/Http/Controllers/PayableChequeVoucherController.php`
**Method:** `update` (Line 416-508)
**Line 442-451:**

```php
foreach ($request->input('invoices') as $invoice) {
    PayableChequeVoucherInvoice::create([
        'payable_cheque_voucher_id' => $voucher->id,
        'purchase_order_id' => $invoice['purchase_order_id'] ?? null,
        'purchase_no' => $invoice['purchase_no'], // ❌ UNDEFINED KEY
        'invoice_no' => $invoice['invoice_no'],   // ❌ UNDEFINED KEY
        'invoice_amount' => $invoice['invoice_amount'] ?? 0,
        'amount_due' => $invoice['amount_due'],
        'amount_paid' => $invoice['amount_paid'],
        'remarks' => $invoice['remarks'] ?? null,
        'payment_status' => $invoice['payment_status'] ?? 'Partial',
    ]);
}
```

#### E. WHY FIELDS DON'T EXIST

The edit modal HTML renders the invoice table (Line 371-397 in JS file):

```javascript
<tbody id="edit-invoice-tbody" class="divide-y divide-slate-100">
    ${(voucher.invoices || []).map(inv => `
        <tr>
            <td class="px-4 py-3 font-bold">${inv.purchase_no}</td>
            <td class="px-4 py-3">${inv.invoice_no}</td>
            <td class="px-4 py-3 text-right">PHP ${Number(inv.amount_due || 0).toFixed(2)}</td>
            <td class="px-4 py-3 text-right">
                <input type="number" class="edit-amount-paid w-24 px-2 py-1 border border-slate-300 rounded text-right" 
                    data-invoice-id="${inv.id}" 
                    value="${inv.amount_paid}" 
                    min="0" 
                    step="0.01">
            </td>
            <td class="px-4 py-3">
                <input type="text" class="edit-remarks w-full px-2 py-1 border border-slate-300 rounded" 
                    data-invoice-id="${inv.id}" 
                    value="${inv.remarks || ''}" 
                    placeholder="Enter remarks">
            </td>
        </tr>
    `).join('')}
</tbody>
```

**PROBLEM:** The inputs only store `data-invoice-id`. They DON'T store:
- `purchase_no`
- `invoice_no`
- `invoice_amount`
- `amount_due`
- `purchase_order_id`

#### F. COMPARISON WITH CREATE/STORE FLOW

**Store Method (Line 206-217):**
```php
foreach ($request->input('invoices') as $invoice) {
    PayableChequeVoucherInvoice::create([
        'payable_cheque_voucher_id' => $voucher->id,
        'purchase_order_id' => $invoice['purchase_order_id'] ?? null,
        'purchase_no' => $invoice['purchase_no'],
        'invoice_no' => $invoice['invoice_no'],
        'invoice_amount' => $invoice['invoice_amount'] ?? 0,
        'amount_due' => $invoice['amount_due'],
        'amount_paid' => $invoice['amount_paid'],
        'remarks' => $invoice['remarks'] ?? null,
        'payment_status' => $invoice['payment_status'] ?? 'Partial',
    ]);
}
```

**Conclusion:** Both `store` and `update` expect THE SAME invoice structure.

---

### SOLUTION

#### OPTION 1: Store Full Invoice Data in Hidden Inputs (RECOMMENDED)
Modify the edit modal to include hidden inputs with all invoice fields.

#### OPTION 2: Load Invoice Data from Voucher Object
Store the full voucher object in a JavaScript variable and reference it when building the payload.

#### OPTION 3: Add Data Attributes to Inputs
Store all required fields as `data-*` attributes on the input elements.

---

### FIX IMPLEMENTATION (OPTION 2 - Most Reliable)

**File:** `public/js/Payable-Cheque-Voucher-Enhancement.js`

**Location:** Line 287-579 (`pcvOpenEditModal` and `pcvSubmitEditedVoucher` functions)

**Changes Required:**

1. **Store voucher data globally** (after line 312):
```javascript
// Add global variable
let currentEditVoucher = null;

// In pcvOpenEditModal, after line 312:
currentEditVoucher = voucher;
```

2. **Fix invoice payload** (lines 524-530):
```javascript
// BEFORE:
document.querySelectorAll('.edit-amount-paid').forEach(input => {
    const remarks = document.querySelector(`.edit-remarks[data-invoice-id="${input.dataset.invoiceId}"]`);
    editData.invoices.push({
        id: input.dataset.invoiceId,
        amount_paid: Number(input.value) || 0,
        remarks: remarks?.value || ''
    });
});

// AFTER:
document.querySelectorAll('.edit-amount-paid').forEach(input => {
    const invoiceId = input.dataset.invoiceId;
    const remarks = document.querySelector(`.edit-remarks[data-invoice-id="${invoiceId}"]`);
    
    // Find original invoice data
    const originalInvoice = currentEditVoucher.invoices.find(inv => String(inv.id) === String(invoiceId));
    
    if (originalInvoice) {
        editData.invoices.push({
            id: invoiceId,
            purchase_order_id: originalInvoice.purchase_order_id,
            purchase_no: originalInvoice.purchase_no,
            invoice_no: originalInvoice.invoice_no,
            invoice_amount: originalInvoice.invoice_amount,
            amount_due: originalInvoice.amount_due,
            amount_paid: Number(input.value) || 0,
            remarks: remarks?.value || '',
            payment_status: Number(input.value) >= originalInvoice.amount_due ? 'Paid' : 'Partial'
        });
    }
});
```

---

## ISSUE #2: Tab UI State Broken

### ERROR DETAILS
- **Symptom:** History tab text only becomes yellow AFTER page reload
- **Immediately After Click:** Active styling inconsistent, wrong colors
- **Expected:** Immediate styling update without reload

---

### ROOT CAUSE ANALYSIS

#### A. EXACT FILE
`resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php`

#### B. EXACT FUNCTION
Tab switching event listeners (Lines 1933-1954)

#### C. EXACT LINE NUMBERS

**Line 1933-1943:** Payables tab click handler
**Line 1944-1954:** History tab click handler

#### D. ACTUAL CODE

```javascript
// Tab switching (Match Sales Note design with Gold text for active tabs)
$('pcv-tab-payables').addEventListener('click', () => {
    // Switch panels
    $('pcv-payables-panel').classList.remove('hidden');
    $('pcv-history-panel').classList.add('hidden');
    // Update tab styles
    $('pcv-tab-payables').classList.remove('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    $('pcv-tab-payables').classList.add('bg-maroon', 'text-gold');
    $('pcv-tab-history').classList.remove('bg-maroon', 'text-gold');
    $('pcv-tab-history').classList.add('bg-white', 'border', 'border-slate-200', 'text-slate-500');
});
$('pcv-tab-history').addEventListener('click', () => {
    // Switch panels
    $('pcv-payables-panel').classList.add('hidden');
    $('pcv-history-panel').classList.remove('hidden');
    // Load history if not loaded
    if (!vouchers || !vouchers.length) loadHistory();
    // Update tab styles
    $('pcv-tab-history').classList.remove('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    $('pcv-tab-history').classList.add('bg-maroon', 'text-gold');
    $('pcv-tab-payables').classList.remove('bg-maroon', 'text-gold');
    $('pcv-tab-payables').classList.add('bg-white', 'border', 'border-slate-200', 'text-slate-500');
});
```

#### E. WHY STYLING ONLY WORKS AFTER RELOAD

**PROBLEM:** The tab HTML has icons with their own elements:

```html
<button id="pcv-tab-history" class="... flex items-center gap-2">
    <i data-lucide="clock" class="w-4 h-4"></i>
    <span>History</span>
</button>
```

**The JavaScript changes classes on the BUTTON, but:**
1. Lucide icons need to be re-initialized after DOM changes
2. Icon color inherits from parent, but Lucide may have cached the initial state
3. No call to `lucide.createIcons()` after class changes

#### F. ADDITIONAL ISSUE: Missing Re-initialization

After changing classes, the code does NOT call:
```javascript
if (window.lucide) window.lucide.createIcons();
```

This causes icons to retain old styling until page reload.

---

### SOLUTION

Add `lucide.createIcons()` after updating tab styles to force icon re-rendering.

---

### FIX IMPLEMENTATION

**File:** `resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php`

**Location:** Lines 1933-1954

**Changes Required:**

```javascript
// Tab switching (Match Sales Note design with Gold text for active tabs)
$('pcv-tab-payables').addEventListener('click', () => {
    // Switch panels
    $('pcv-payables-panel').classList.remove('hidden');
    $('pcv-history-panel').classList.add('hidden');
    // Update tab styles
    $('pcv-tab-payables').classList.remove('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    $('pcv-tab-payables').classList.add('bg-maroon', 'text-gold');
    $('pcv-tab-history').classList.remove('bg-maroon', 'text-gold');
    $('pcv-tab-history').classList.add('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    // Re-initialize lucide icons to update colors
    if (window.lucide) window.lucide.createIcons();
});
$('pcv-tab-history').addEventListener('click', () => {
    // Switch panels
    $('pcv-payables-panel').classList.add('hidden');
    $('pcv-history-panel').classList.remove('hidden');
    // Load history if not loaded
    if (!vouchers || !vouchers.length) loadHistory();
    // Update tab styles
    $('pcv-tab-history').classList.remove('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    $('pcv-tab-history').classList.add('bg-maroon', 'text-gold');
    $('pcv-tab-payables').classList.remove('bg-maroon', 'text-gold');
    $('pcv-tab-payables').classList.add('bg-white', 'border', 'border-slate-200', 'text-slate-500');
    // Re-initialize lucide icons to update colors
    if (window.lucide) window.lucide.createIcons();
});
```

---

## SUMMARY OF FIXES

### ISSUE #1: Edit Voucher Save
**Problem:** Missing required fields in invoice payload
**Fix:** Include all invoice fields from original voucher data
**Lines Modified:** ~30 lines in `Payable-Cheque-Voucher-Enhancement.js`

### ISSUE #2: Tab UI State
**Problem:** Icons not re-initialized after class changes
**Fix:** Add `lucide.createIcons()` after tab switching
**Lines Modified:** 2 lines in `Payable-Cheque-Voucher.blade.php`

---

## TESTING PLAN

### ISSUE #1:
1. Open edit modal
2. Modify invoice amount/remarks
3. Save changes
4. Verify: No 500 error
5. Verify: Success message appears
6. Verify: Voucher updated in database

### ISSUE #2:
1. Click History tab
2. Verify: Immediate yellow text and icon
3. Click Payables tab
4. Verify: Immediate yellow text and icon
5. Verify: No page reload needed
6. Verify: No styling flash/flicker

