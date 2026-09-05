<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\PayableChequeVoucher;
use App\Models\PayableChequeVoucherInvoice;
use App\Models\PayableChequeVoucherPayment;
use App\Models\PayableChequeVoucherSuddenReturn;
use App\Models\Supplier;
use Exception;

class PayableChequeVoucherController extends Controller
{
    /**
     * Save or update draft state for sudden return (auto-save)
     */
    public function saveDraft(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|integer',
                'draft_state' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $supplierId = $request->input('supplier_id');
            $draftState = $request->input('draft_state');

            // Find or create draft voucher
            $voucher = PayableChequeVoucher::where('supplier_id', $supplierId)
                ->where('is_draft', true)
                ->first();

            if (!$voucher) {
                // Get supplier name
                $supplier = DB::connection('masterlist')
                    ->table('suppliers')
                    ->where('id', $supplierId)
                    ->first();

                $supplierName = $supplier ? $supplier->supplier_name : 'Unknown Supplier';

                // Generate temporary voucher number
                $tempVoucherNo = 'DRAFT-' . date('YmdHis') . '-' . $supplierId;

                $voucher = PayableChequeVoucher::create([
                    'voucher_no' => $tempVoucherNo,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'voucher_date' => now()->format('Y-m-d'),
                    'status' => 'Draft',
                    'is_draft' => true,
                    'draft_state' => $draftState,
                    'total_paid' => 0,
                ]);
            } else {
                $voucher->draft_state = $draftState;
                $voucher->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Draft saved successfully',
                'draft_id' => $voucher->id,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get draft state for supplier
     */
    public function getDraft(Request $request)
    {
        try {
            $supplierId = $request->input('supplier_id');

            if (!$supplierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier ID is required',
                ], 422);
            }

            $voucher = PayableChequeVoucher::where('supplier_id', $supplierId)
                ->where('is_draft', true)
                ->first();

            if (!$voucher) {
                return response()->json([
                    'success' => true,
                    'has_draft' => false,
                    'draft_state' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'has_draft' => true,
                'draft_state' => $voucher->draft_state,
                'draft_id' => $voucher->id,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get draft: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear draft state
     */
    public function clearDraft(Request $request)
    {
        try {
            $supplierId = $request->input('supplier_id');

            if (!$supplierId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier ID is required',
                ], 422);
            }

            $deleted = PayableChequeVoucher::where('supplier_id', $supplierId)
                ->where('is_draft', true)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Draft cleared successfully',
                'deleted' => $deleted > 0,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear draft: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new voucher
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|integer',
                'supplier_name' => 'required|string',
                'voucher_date' => 'required|date',
                'reference_no' => 'nullable|string',
                'particulars' => 'nullable|string',
                'payment_method' => 'required|string',
                'total_paid' => 'required|numeric|min:0',
                'invoices' => 'required|array|min:1',
                'payment' => 'nullable|array',
                'payments' => 'nullable|array|min:1',
                'payments.*.payment_method' => 'required_with:payments|string',
                'payments.*.account_no' => 'nullable|string',
                'payments.*.bank_name' => 'nullable|string',
                'payments.*.check_no' => 'nullable|string',
                'payments.*.check_date' => 'nullable|date',
                'payments.*.payment_date' => 'nullable|date',
                'payments.*.reference_no' => 'nullable|string|max:255',
                'payments.*.credit_amount' => 'required_with:payments|numeric',
                'show_check_summary' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::connection('accounting')->beginTransaction();

            try {
                // Generate voucher number
                $latestVoucher = PayableChequeVoucher::where('is_draft', false)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = 1;
                if ($latestVoucher && preg_match('/PCV-(\d+)/', $latestVoucher->voucher_no, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                }
                $voucherNo = 'PCV-' . str_pad($nextNumber, 7, '0', STR_PAD_LEFT);

                // Create voucher
                $voucher = PayableChequeVoucher::create([
                    'voucher_no' => $voucherNo,
                    'supplier_id' => $request->input('supplier_id'),
                    'supplier_name' => $request->input('supplier_name'),
                    'voucher_date' => $request->input('voucher_date'),
                    'reference_no' => $request->input('reference_no'),
                    'particulars' => $request->input('particulars'),
                    'payment_method' => $request->input('payment_method'),
                    'total_paid' => $request->input('total_paid'),
                    'status' => 'Posted',
                    'is_draft' => false,
                    'show_check_summary' => (bool) $request->input('show_check_summary', false),
                ]);

                // Create invoice records
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

                // Create one or more payment detail rows. Negative credit amounts are valid
                // payment adjustments and are NEVER written to Returns/Sudden Returns.
                $payments = $request->input('payments', []);
                if (empty($payments)) {
                    $legacyPayment = $request->input('payment', []);
                    $payments = [[
                        'payment_method' => $legacyPayment['payment_method'] ?? $request->input('payment_method'),
                        'account_no' => $legacyPayment['account_no'] ?? null,
                        'bank_name' => $legacyPayment['bank_name'] ?? null,
                        'check_no' => $legacyPayment['check_no'] ?? null,
                        'check_date' => $legacyPayment['check_date'] ?? null,
                        'payment_date' => $legacyPayment['payment_date'] ?? null,
                        'reference_no' => (($legacyPayment['payment_method'] ?? $request->payment_method) === 'Gcash') ? ($legacyPayment['reference_no'] ?? null) : null,
                        'credit_amount' => $legacyPayment['credit_amount'] ?? 0,
                    ]];
                }
                foreach ($payments as $payment) {
                    PayableChequeVoucherPayment::create([
                        'payable_cheque_voucher_id' => $voucher->id,
                        'payment_method' => $payment['payment_method'] ?? $request->input('payment_method'),
                        'account_no' => $payment['account_no'] ?? null,
                        'bank_name' => $payment['bank_name'] ?? null,
                        'check_no' => $payment['check_no'] ?? null,
                        'check_date' => $payment['check_date'] ?? null,
                        'payment_date' => $payment['payment_date'] ?? null,
                        'reference_no' => (($payment['payment_method'] ?? $request->payment_method) === 'Gcash') ? ($payment['reference_no'] ?? null) : null,
                        'credit_amount' => (float) ($payment['credit_amount'] ?? 0),
                    ]);
                }

                // Clear draft if exists
                PayableChequeVoucher::where('supplier_id', $request->input('supplier_id'))
                    ->where('is_draft', true)
                    ->delete();

                // Audit trail
                if (function_exists('hatdogWriteAuditTrail')) {
                    $user = session('user');
                    $actor = function_exists('hatdogAuditActor') ? hatdogAuditActor($user) : ['name' => 'System', 'identifier' => null];

                    hatdogWriteAuditTrail([
                        'module' => 'Accounting',
                        'action' => 'Create Payable Cheque Voucher',
                        'source_connection' => 'accounting',
                        'source_table' => 'payable_cheque_vouchers',
                        'source_id' => $voucher->id,
                        'display_id' => $voucherNo,
                        'record_name' => $request->input('supplier_name'),
                        'user_name' => $actor['name'],
                        'user_identifier' => $actor['identifier'],
                        'audit_data' => [
                            'total_paid' => $request->input('total_paid'),
                            'payment_method' => $request->input('payment_method'),
                            'invoice_count' => count($request->input('invoices')),
                        ],
                    ]);
                }

                DB::connection('accounting')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Voucher created successfully',
                    'voucher_no' => $voucherNo,
                    'voucher_id' => $voucher->id,
                ]);

            } catch (Exception $e) {
                DB::connection('accounting')->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create voucher: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get voucher details for editing
     */
    public function show($id)
    {
        try {
            // Check user authentication
            $user = session('user');
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            // Find voucher with relationships
            $voucher = PayableChequeVoucher::with(['invoices', 'payments', 'suddenReturns'])
                ->where('id', $id)
                ->where('is_draft', false)
                ->first();

            if (!$voucher) {
                return response()->json([
                    'success' => false,
                    'message' => "Voucher with ID {$id} not found or is in draft state",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'voucher' => $voucher,
                'voucher_fields' => [
                    'additional_discount' => (float) ($voucher->additional_discount ?? 0),
                    'additional_discount_amount' => (float) ($voucher->additional_discount_amount ?? 0),
                    'global_discount' => (float) ($voucher->global_discount ?? 0),
                    'global_discount_amount' => (float) ($voucher->global_discount_amount ?? 0),
                    'total_amount' => (float) ($voucher->total_amount ?? 0),
                    'net_amount' => (float) ($voucher->net_amount ?? 0),
                    'check_amount' => (float) ($voucher->check_amount ?? 0),
                    'show_check_summary' => (bool) ($voucher->show_check_summary ?? false),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error("Model not found: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found',
            ], 404);
        } catch (\Exception $e) {
            \Log::error("Error in show method: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load voucher: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing voucher
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reference_no' => 'nullable|string',
                'particulars' => 'nullable|string',
                'payment_method' => 'required|string',
                'total_paid' => 'required|numeric|min:0',
                'invoices' => 'required|array|min:1',
                'payment' => 'nullable|array',
                'payments' => 'nullable|array|min:1',
                'payments.*.payment_method' => 'required_with:payments|string',
                'payments.*.account_no' => 'nullable|string',
                'payments.*.bank_name' => 'nullable|string',
                'payments.*.check_no' => 'nullable|string',
                'payments.*.check_date' => 'nullable|date',
                'payments.*.payment_date' => 'nullable|date',
                'payments.*.reference_no' => 'nullable|string|max:255',
                'payments.*.credit_amount' => 'required_with:payments|numeric',
                'additional_discount' => 'nullable|numeric|min:0',
                'additional_discount_amount' => 'nullable|numeric|min:0',
                'global_discount' => 'nullable|numeric|min:0|max:100',
                'global_discount_amount' => 'nullable|numeric|min:0',
                'total_amount' => 'nullable|numeric|min:0',
                'net_amount' => 'nullable|numeric|min:0',
                'check_amount' => 'nullable|numeric|min:0',
                'sudden_returns' => 'nullable|array',
                'sudden_returns.*.po_id' => 'required|integer',
                'sudden_returns.*.return_number' => 'required|string',
                'sudden_returns.*.total_amount' => 'required|numeric',
                'sudden_returns.*.remarks' => 'nullable|string',
                'show_check_summary' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::connection('accounting')->beginTransaction();

            try {
                // Resolve the voucher on the same explicit accounting connection used
                // for the transaction and every write below. This avoids a model/
                // connection mismatch and gives a clean 404 for stale IDs.
                $voucher = DB::connection('accounting')
                    ->table('payable_cheque_vouchers')
                    ->where('id', (int) $id)
                    ->where('is_draft', false)
                    ->first();

                if (!$voucher) {
                    DB::connection('accounting')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Voucher with ID {$id} was not found in core4_accounting or is still a draft. Refresh History and reopen the PCV before editing.",
                    ], 404);
                }

                // Update voucher (raw DB to bypass $fillable restrictions)
                DB::connection('accounting')->table('payable_cheque_vouchers')->where('id', $voucher->id)->update([
                    'reference_no' => $request->input('reference_no'),
                    'particulars' => $request->input('particulars'),
                    'payment_method' => $request->input('payment_method'),
                    'total_paid' => $request->input('total_paid'),
                    'global_discount' => $request->input('global_discount', 0),
                    'global_discount_amount' => $request->input('global_discount_amount', 0),
                    'additional_discount' => $request->input('additional_discount', 0),
                    'additional_discount_amount' => $request->input('additional_discount_amount', 0),
                    'show_check_summary' => (bool) $request->input('show_check_summary', false),
                    'updated_at' => now(),
                ]);

                // Delete existing invoices and create new ones
                DB::connection('accounting')->table('payable_cheque_voucher_invoices')
                    ->where('payable_cheque_voucher_id', $id)->delete();
                foreach ($request->input('invoices') as $invoice) {
                    $due = (float) ($invoice['amountDue'] ?? 0);
                    $paid = (float) ($invoice['amountPaid'] ?? 0);
                    $discount1 = (float) ($invoice['discount1'] ?? 0);
                    $netDue = $discount1 > 0 ? $due - ($due * $discount1 / 100) : $due;
                    $paymentStatus = $paid >= ($netDue - 0.005) ? 'Full' : 'Partial';
                    DB::connection('accounting')->table('payable_cheque_voucher_invoices')->insert([
                        'payable_cheque_voucher_id' => $voucher->id,
                        'purchase_order_id' => $invoice['sourceId'] ?? $invoice['purchase_order_id'] ?? null,
                        'purchase_no' => $invoice['purchaseNo'],
                        'invoice_no' => $invoice['invoiceNo'],
                        'invoice_amount' => $invoice['invoiceAmount'] ?? 0,
                        'amount_due' => $due,
                        'amount_paid' => $paid,
                        'discount_1' => $discount1,
                        'discount_2' => (float) ($invoice['discount2'] ?? 0),
                        'return_amount' => (float) ($invoice['returnAmount'] ?? 0),
                        'total_returns' => (int) ($invoice['totalReturns'] ?? 0),
                        'return_number' => $invoice['returnNumber'] ?? null,
                        'rs_details' => $invoice['rsDetails'] ?? null,
                        'remarks' => $invoice['remarks'] ?? null,
                        'payment_status' => $paymentStatus,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Delete existing sudden returns for this voucher and re-insert selected
                DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns')
                    ->where('payable_cheque_voucher_id', $id)->delete();
                $allInvoiceIds = DB::connection('accounting')->table('payable_cheque_voucher_invoices')
                    ->where('payable_cheque_voucher_id', $id)
                    ->pluck('purchase_order_id', 'id');
                foreach ($request->input('sudden_returns', []) as $sr) {
                    $targetInvoiceId = null;
                    $targetPoId = null;
                    foreach ($allInvoiceIds as $invId => $invPoId) {
                        if ((int) $invPoId === (int) $sr['po_id']) {
                            $targetInvoiceId = $invId;
                            $targetPoId = $invPoId;
                            break;
                        }
                    }
                    if ($targetInvoiceId) {
                        DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns')->insert([
                            'payable_cheque_voucher_id' => $voucher->id,
                            'payable_cheque_voucher_invoice_id' => $targetInvoiceId,
                            'purchase_order_id' => $targetPoId,
                            'return_number' => $sr['return_number'],
                            'return_date' => $sr['date'] ?? null,
                            'return_amount' => $sr['total_amount'],
                            'remarks' => $sr['remarks'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $sharePerInvoice = $allInvoiceIds->count() > 0 ? $sr['total_amount'] / $allInvoiceIds->count() : $sr['total_amount'];
                        foreach ($allInvoiceIds as $invId => $invPoId) {
                            DB::connection('accounting')->table('payable_cheque_voucher_sudden_returns')->insert([
                                'payable_cheque_voucher_id' => $voucher->id,
                                'payable_cheque_voucher_invoice_id' => $invId,
                                'purchase_order_id' => $invPoId,
                                'return_number' => $sr['return_number'],
                                'return_date' => $sr['date'] ?? null,
                                'return_amount' => $sharePerInvoice,
                                'remarks' => $sr['remarks'] ?? null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                // Replace payment details with the submitted rows. Negative credit amounts
                // remain payment adjustments only; return tables are not touched here.
                DB::connection('accounting')->table('payable_cheque_voucher_payments')
                    ->where('payable_cheque_voucher_id', $id)->delete();
                $payments = $request->input('payments', []);
                if (empty($payments)) {
                    $legacyPayment = $request->input('payment', []);
                    $payments = [[
                        'payment_method' => $request->input('payment_method'),
                        'account_no' => $legacyPayment['account_no'] ?? null,
                        'bank_name' => $legacyPayment['bank_name'] ?? null,
                        'check_no' => $legacyPayment['check_no'] ?? null,
                        'check_date' => $legacyPayment['check_date'] ?? null,
                        'payment_date' => $legacyPayment['payment_date'] ?? null,
                        'reference_no' => (($legacyPayment['payment_method'] ?? $request->payment_method) === 'Gcash') ? ($legacyPayment['reference_no'] ?? null) : null,
                        'credit_amount' => $legacyPayment['credit_amount'] ?? 0,
                    ]];
                }
                foreach ($payments as $payment) {
                    DB::connection('accounting')->table('payable_cheque_voucher_payments')->insert([
                        'payable_cheque_voucher_id' => $voucher->id,
                        'payment_method' => $payment['payment_method'] ?? $request->input('payment_method'),
                        'account_no' => $payment['account_no'] ?? null,
                        'bank_name' => $payment['bank_name'] ?? null,
                        'check_no' => $payment['check_no'] ?? null,
                        'check_date' => $payment['check_date'] ?? null,
                        'payment_date' => $payment['payment_date'] ?? null,
                        'reference_no' => (($payment['payment_method'] ?? $request->payment_method) === 'Gcash') ? ($payment['reference_no'] ?? null) : null,
                        'credit_amount' => (float) ($payment['credit_amount'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Audit trail
                if (function_exists('hatdogWriteAuditTrail')) {
                    $user = session('user');
                    $actor = function_exists('hatdogAuditActor') ? hatdogAuditActor($user) : ['name' => 'System', 'identifier' => null];

                    hatdogWriteAuditTrail([
                        'module' => 'Accounting',
                        'action' => 'Update Payable Cheque Voucher',
                        'source_connection' => 'accounting',
                        'source_table' => 'payable_cheque_vouchers',
                        'source_id' => $voucher->id,
                        'display_id' => $voucher->voucher_no,
                        'record_name' => $voucher->supplier_name,
                        'user_name' => $actor['name'],
                        'user_identifier' => $actor['identifier'],
                        'audit_data' => [
                            'total_paid' => $request->input('total_paid'),
                            'payment_method' => $request->input('payment_method'),
                            'invoice_count' => count($request->input('invoices')),
                        ],
                    ]);
                }

                DB::connection('accounting')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Voucher updated successfully',
                    'voucher_id' => (int) $voucher->id,
                    'voucher_no' => (string) $voucher->voucher_no,
                ]);

            } catch (Exception $e) {
                DB::connection('accounting')->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update voucher: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment recommendations (bank/account history) for a supplier.
     */
    public function paymentRecommendations($supplierId)
    {
        try {
            // Historical PCV rows use both `Bank` and `Bank Transfer`, and older
            // records sometimes have only the bank name OR the account number filled.
            // Treat either field as usable recommendation history instead of requiring both.
            $recommendations = DB::connection('accounting')
                ->table('payable_cheque_voucher_payments as p')
                ->join('payable_cheque_vouchers as v', 'v.id', '=', 'p.payable_cheque_voucher_id')
                ->where('v.supplier_id', (int) $supplierId)
                ->whereIn('p.payment_method', ['Cheque', 'Bank Transfer', 'Bank'])
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNotNull('p.bank_name')
                          ->whereRaw("TRIM(p.bank_name) <> ''");
                    })->orWhere(function ($q) {
                        $q->whereNotNull('p.account_no')
                          ->whereRaw("TRIM(p.account_no) <> ''");
                    });
                })
                ->selectRaw("TRIM(COALESCE(p.bank_name, '')) as bank_name")
                ->selectRaw("TRIM(COALESCE(p.account_no, '')) as account_no")
                ->selectRaw("CASE WHEN p.payment_method = 'Bank' THEN 'Bank Transfer' ELSE p.payment_method END as payment_method")
                ->selectRaw('MAX(COALESCE(p.payment_date, p.check_date, DATE(p.created_at))) as last_used_at')
                ->selectRaw('COUNT(*) as used_count')
                ->groupByRaw("TRIM(COALESCE(p.bank_name, '')), TRIM(COALESCE(p.account_no, '')), CASE WHEN p.payment_method = 'Bank' THEN 'Bank Transfer' ELSE p.payment_method END")
                ->orderByDesc('last_used_at')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
