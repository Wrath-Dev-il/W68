# Fix: Missing columns on payable_cheque_voucher_sudden_returns (core4_accounting)

## Background

User reported: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sr.return_amount' in 'field list' (Connection: accounting, Database: core4_accounting)` from the query:

```sql
select i.purchase_order_id, COALESCE(SUM(sr.return_amount), 0) as sr_total
from payable_cheque_voucher_sudden_returns as sr
inner join payable_cheque_voucher_invoices as i
  on sr.payable_cheque_voucher_invoice_id = i.id
group by i.purchase_order_id
```

## Root cause

The WIP code (PayableChequeVoucherController.php:449-473, routes/web.php:19395-19428, SupplierComprehensiveProfileService.php:169-172, InvoiceGroupService.php:826, web.php:18459/19055/19189) writes/reads 4 columns on `payable_cheque_voucher_sudden_returns` that the original create migration (accounting/2026_06_18_000001) never added. The table only has: id, payable_cheque_voucher_id, po_id, return_number, po_number, date, total_amount, remarks, timestamps.

Missing columns (all in the model fillable/casts and in every recent insert):

| Column | Type |
|---|---|
| payable_cheque_voucher_invoice_id | bigint unsigned, nullable (join key; would error NEXT if only return_amount fixed) |
| purchase_order_id | bigint unsigned, nullable |
| return_date | date, nullable |
| return_amount | decimal(15,2), not null, default 0 |

Table is currently EMPTY (0 rows) — no data backfill needed. `payable_cheque_voucher_invoices` already has all its columns.

## Steps

1. Create `database/migrations/accounting/2026_08_17_000001_add_invoice_relationship_columns_to_payable_cheque_voucher_sudden_returns_table.php`
   - up(): `Schema::connection('accounting')->table(...)` guarded with `hasColumn`; adds the 4 columns in order (invoice_id after voucher_id + index `pcvsr_invoice_id_index`; purchase_order_id after invoice_id + index `pcvsr_purchase_order_id_index`; return_date after return_number; return_amount decimal(15,2) default 0.00 after return_date).
   - down(): drops the 4 columns (reverse order), guarded.
2. Run: `php artisan migrate --database=accounting --path=database/migrations/accounting/2026_08_17_000001_add_invoice_relationship_columns_to_payable_cheque_voucher_sudden_returns_table.php`
3. Verify:
   - `SHOW COLUMNS FROM payable_cheque_voucher_sudden_returns` shows all 4 new columns
   - Re-run the failing query — returns a result set, no 42S22 error

## Migration file content (final draft)

`database/migrations/accounting/2026_08_17_000001_add_invoice_relationship_columns_to_payable_cheque_voucher_sudden_returns_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('accounting')->table('payable_cheque_voucher_sudden_returns', function (Blueprint $table) {
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_sudden_returns', 'payable_cheque_voucher_invoice_id')) {
                $table->unsignedBigInteger('payable_cheque_voucher_invoice_id')->nullable()->after('payable_cheque_voucher_id')->index('pcvsr_invoice_id_index');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_sudden_returns', 'purchase_order_id')) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->after('payable_cheque_voucher_invoice_id')->index('pcvsr_purchase_order_id_index');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_sudden_returns', 'return_date')) {
                $table->date('return_date')->nullable()->after('return_number');
            }
            if (!Schema::connection('accounting')->hasColumn('payable_cheque_voucher_sudden_returns', 'return_amount')) {
                $table->decimal('return_amount', 15, 2)->default(0.00)->after('return_date');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('accounting')->table('payable_cheque_voucher_sudden_returns', function (Blueprint $table) {
            foreach (['return_amount', 'return_date', 'purchase_order_id', 'payable_cheque_voucher_invoice_id'] as $column) {
                if (Schema::connection('accounting')->hasColumn('payable_cheque_voucher_sudden_returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
```

## Deferred (previous tasks, not part of this migration)

- Issue 1: 403 on admin ledger-items endpoints (web.php:6889/6900) — relax allowed types [1] -> [1,2,3,4,5] (decision never confirmed by user).
- Issue 2: online-print cost — root cause (missing `supplier_name` column) already fixed in ProductPurchaseCostService.php (untracked, hasColumn guard); needs browser verification only.