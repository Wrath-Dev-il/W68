# TASK 3, 4, 5 Implementation - Admin Role Only

## Overview
This document describes the implementation of the remaining tasks (3, 4, 5) for the Payable Cheque Voucher system, applied to **Admin role only** as instructed.

---

## TASK 3: History Must Group By Supplier

### Backend Changes

#### File: `routes/web.php`

**New Route Added (lines ~12227):**
```php
Route::get('/admin/accounting/payable-cheque-voucher/history/supplier/{supplierId}/vouchers', function ($supplierId) {
    // Fetches all vouchers for a specific supplier
    // Includes has_sudden_returns flag for each voucher
    // Returns: voucher_no, voucher_date, total_invoices, total_amount, has_sudden_returns
});
```

**Existing History Route (lines ~12171):**
- Already modified to return `suppliers` array instead of `vouchers`
- Each supplier includes: supplier_id, supplier_code, supplier_name, voucher_count, total_invoices, total_amount, last_voucher_date

### Frontend Changes

#### File: `resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php`

**Function: `loadHistory()` (lines ~1599)**
- Changed to store `data.suppliers` instead of `data.vouchers`
- Comment added: "Store suppliers instead of vouchers"

**Function: `renderHistory()` (lines ~1610)**
- Now displays supplier rows instead of voucher rows
- Shows: supplier_code, supplier_name, voucher_count
- Only Print button displayed (removed View and Edit buttons)
- Click handler calls `openPrintModal(supplierId)` instead of individual voucher actions

**Function: `openPrintModal()` (lines ~1796)**
- Now accepts `supplierId` parameter instead of `voucherId`
- Fetches vouchers for the specific supplier via new endpoint
- Stores vouchers in `supplierVouchers` array (new variable)
- Each voucher includes `has_sudden_returns` flag

**Result:**
- History table shows one row per supplier
- Clicking Print button opens modal with all vouchers for that supplier
- Vouchers are grouped under their supplier parent

---

## TASK 4: Gold Highlight For Vouchers With Sudden Returns

### Backend Changes

#### File: `routes/web.php`

**New Route (supplier vouchers endpoint):**
```php
// Check which vouchers have sudden returns
$vouchersWithSuddenReturns = DB::connection('accounting')
    ->table('payable_cheque_voucher_sudden_returns')
    ->whereIn('payable_cheque_voucher_id', $voucherIds)
    ->select('payable_cheque_voucher_id')
    ->distinct()
    ->pluck('payable_cheque_voucher_id')
    ->toArray();

// Add has_sudden_returns flag to each voucher
'has_sudden_returns' => in_array($v->id, $vouchersWithSuddenReturns),
```

### Frontend Changes

#### File: `resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php`

**Function: `renderPrintTable()` (lines ~1758)**
```javascript
const rowClass = v.has_sudden_returns ? 'bg-amber-100' : '';
```
- Applied to `<tr>` element in voucher row rendering
- Uses Tailwind CSS class `bg-amber-100` for gold background

**Result:**
- Vouchers with at least one sudden return record are highlighted with gold background
- Normal vouchers remain unchanged

---

## TASK 5: Add Voucher Filter Dropdown

### Frontend Changes

#### File: `resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php`

**HTML: Print Modal (lines ~414)**
```html
<!-- TASK 5: Filter Dropdown -->
<div class="mb-4 flex items-center gap-3">
    <label for="pcv-print-filter" class="text-xs font-black uppercase tracking-widest text-slate-600">Filter:</label>
    <select id="pcv-print-filter" class="px-3 py-2 text-xs font-bold border border-slate-300 rounded-lg bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-maroon-500">
        <option value="all">All Vouchers</option>
        <option value="with">With Sudden Return</option>
        <option value="without">Without Sudden Return</option>
    </select>
</div>
```

**JavaScript Variables (lines ~1738)**
```javascript
let printFilter = 'all'; // TASK 5: Filter state for sudden returns
let supplierVouchers = []; // Store vouchers for the selected supplier
```

**Function: `filteredPrintVouchers()` (lines ~1741)**
```javascript
// TASK 5: Apply sudden return filter
if (printFilter === 'with') {
    filtered = filtered.filter(v => v.has_sudden_returns);
} else if (printFilter === 'without') {
    filtered = filtered.filter(v => !v.has_sudden_returns);
}
```

**Function: `openPrintModal()` (lines ~1814)**
```javascript
// TASK 5: Reset sudden return filter dropdown
const filterDropdown = $('pcv-print-filter');
if (filterDropdown) {
    filterDropdown.value = 'all';
    printFilter = 'all';
}
```

**Event Listener (lines ~1925)**
```javascript
// TASK 5: Print modal sudden return filter dropdown
const printFilterDropdown = $('pcv-print-filter');
if (printFilterDropdown) {
    printFilterDropdown.addEventListener('change', () => { 
        printFilter = printFilterDropdown.value; 
        printPage = 1; 
        renderPrintTable(); 
    });
}
```

**Result:**
- Dropdown appears above the voucher table in print modal
- Filtering works without page reload
- "All Vouchers" - shows everything
- "With Sudden Return" - shows only vouchers with sudden returns
- "Without Sudden Return" - shows only vouchers without sudden returns

---

## Files Modified

1. **routes/web.php**
   - Added new route: `/admin/accounting/payable-cheque-voucher/history/supplier/{supplierId}/vouchers`
   - Route includes SQL logic to detect `has_sudden_returns` flag

2. **resources/views/Admin/Accounting/Payable-Cheque-Voucher.blade.php**
   - Updated `loadHistory()` function
   - Updated `renderHistory()` function
   - Updated `openPrintModal()` function
   - Updated `filteredPrintVouchers()` function
   - Updated `renderPrintTable()` function
   - Added filter dropdown HTML
   - Added event listeners for filter dropdown and print column inputs

---

## NOT Applied To

As instructed by user: **"do not applied it on regular_user and special_user it yet"**

The following files were NOT modified:
- `resources/views/Regular_User/Accounting/Payable-Cheque-Voucher.blade.php`
- `resources/views/Special_User/Accounting/Payable-Cheque-Voucher.blade.php`

---

## Testing Checklist

### TASK 3: History Grouping
- [ ] History page displays suppliers (not individual vouchers)
- [ ] Each supplier shows: code, name, voucher count
- [ ] Clicking Print button opens modal with supplier's vouchers
- [ ] All vouchers for that supplier are displayed

### TASK 4: Gold Highlighting
- [ ] Vouchers with sudden returns have gold (`bg-amber-100`) background
- [ ] Vouchers without sudden returns have normal background
- [ ] Highlighting is visible and distinguishable

### TASK 5: Filter Dropdown
- [ ] Dropdown is visible above voucher table in print modal
- [ ] "All Vouchers" option shows all vouchers
- [ ] "With Sudden Return" option shows only vouchers with sudden returns
- [ ] "Without Sudden Return" option shows only vouchers without sudden returns
- [ ] Filtering works immediately without page reload
- [ ] Pagination updates correctly after filtering
- [ ] Filter resets to "All Vouchers" when opening print modal

---

## Integration with Previous Tasks

### TASK 1 & 2 (Already Completed for Admin)
- Excess Amount Logic and Display already implemented in Admin blade
- `calculateDisplayTotals()` function prevents negative totals
- Excessive amount displayed in:
  - Review modal
  - Print preview
  - View modal

### Combined Behavior
When a voucher has sudden returns:
1. It appears with gold background in print modal (TASK 4)
2. Can be filtered using dropdown (TASK 5)
3. When viewed, excessive amount is shown if deductions exceed invoice total (TASK 1 & 2)
4. Parent supplier is shown in history table (TASK 3)

---

## Database Tables Used

### Queried Tables
- `accounting.payable_cheque_vouchers` - Main voucher data
- `accounting.payable_cheque_voucher_invoices` - Invoice details
- `accounting.payable_cheque_voucher_sudden_returns` - Sudden return records (for detecting flag)
- `masterlist.suppliers` - Supplier codes and details

### Key Logic
```sql
-- Check if voucher has sudden returns
SELECT DISTINCT payable_cheque_voucher_id 
FROM payable_cheque_voucher_sudden_returns 
WHERE payable_cheque_voucher_id IN (...)
```

---

## Next Steps

If user requests to apply TASK 1 & 2 to Regular_User and Special_User:
1. Copy `calculateDisplayTotals()` function implementation
2. Update review modal totals sections
3. Update print preview totals sections
4. Update view modal totals sections

If user requests to apply TASK 3, 4, 5 to Regular_User and Special_User:
1. Create corresponding routes for those roles
2. Copy all JavaScript function changes
3. Copy HTML structure changes (filter dropdown)
4. Add event listeners

---

## Implementation Date
June 19, 2026

## Status
✅ **TASK 3** - Complete (Admin only)
✅ **TASK 4** - Complete (Admin only)
✅ **TASK 5** - Complete (Admin only)
