<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InvoiceGroupService
{
    private bool $groupRemarksColumnReady = false;
    private bool $groupDiscountColumnsReady = false;

    public function __construct(
        private readonly OnlineInvoiceAmountService $onlineInvoiceAmountService
    ) {
    }

    public function create(Request $request, $user, $processClosure = null): array
    {
        $this->ensureGroupRemarksColumn();
        $this->ensureGroupDiscountColumns();
        $this->ensurePaidGroupColumns();

        $title = trim((string) $request->input('title', ''));
        $items = $request->input('items');

        if ($title === '') {
            return ['success' => false, 'message' => 'Group title is required.'];
        }
        if (!is_array($items) || count($items) === 0) {
            return ['success' => false, 'message' => 'Select at least one invoice to create a group.'];
        }
        if (!$processClosure) {
            return ['success' => false, 'message' => 'Automatic payment handler is unavailable.'];
        }

        $validation = $this->validateGroupItems($items);
        if (!$validation['success']) {
            return $validation;
        }

        try {
            return DB::connection('accounting')->transaction(function () use ($title, $items, $user, $processClosure) {
                $groupId = DB::connection('accounting')->table('payment_invoice_groups')->insertGetId([
                    'title' => $title,
                    'created_by' => $this->actorName($user),
                    'grouped_date' => now()->toDateString(),
                    'status' => 'active',
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $groupItemIds = [];
                foreach ($items as $item) {
                    $groupItemIds[] = (int) DB::connection('accounting')->table('payment_invoice_group_items')->insertGetId([
                        'payment_invoice_group_id' => $groupId,
                        'source_type' => (string) $item['source_type'],
                        'invoice_id' => (int) $item['source_id'],
                        'invoice_no' => (string) $item['invoice_no'],
                        'remarks' => $this->cleanRemarks($item['remarks'] ?? null),
                        'process_payment_invoice_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $paymentResult = $this->autoPayGroupItems($groupId, $groupItemIds, $processClosure);
                if (!$paymentResult['success']) {
                    throw new \RuntimeException($paymentResult['message'] ?? 'Failed to automatically pay the grouped invoices.');
                }

                $this->refreshGroupStatus($groupId);

                return [
                    'success' => true,
                    'group_id' => $groupId,
                    'payments_created' => (int) ($paymentResult['payments_created'] ?? 0),
                    'payment_ids' => $paymentResult['payment_ids'] ?? [],
                    'payment_nos' => $paymentResult['payment_nos'] ?? [],
                    'group_status' => $this->currentGroupStatus($groupId),
                ];
            });
        } catch (Throwable $e) {
            if ((string) $e->getCode() === '23000') {
                return ['success' => false, 'message' => 'One or more invoices are already part of this group.'];
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addItems(Request $request, int $groupId, $processClosure = null): array
    {
        $this->ensureGroupRemarksColumn();
        $this->ensureGroupDiscountColumns();
        $this->ensurePaidGroupColumns();
        $items = $request->input('items');

        if (!is_array($items) || count($items) === 0) {
            return ['success' => false, 'message' => 'Select at least one invoice to add to a group.'];
        }
        if (!$processClosure) {
            return ['success' => false, 'message' => 'Automatic payment handler is unavailable.'];
        }

        $group = DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->first();

        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $validation = $this->validateGroupItems($items);
        if (!$validation['success']) {
            return $validation;
        }

        $existingKeys = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->get()
            ->mapWithKeys(function ($row) {
                return [$this->invoiceKey([
                    'source_type' => $row->source_type,
                    'source_id' => $row->invoice_id,
                    'invoice_no' => $row->invoice_no,
                ]) => (int) $row->id];
            })
            ->all();

        try {
            return DB::connection('accounting')->transaction(function () use ($items, $groupId, $existingKeys, $processClosure) {
                $addedIds = [];
                $payItemIds = [];
                $added = 0;
                $skipped = 0;

                foreach ($items as $item) {
                    $sourceType = (string) $item['source_type'];
                    $sourceId = (int) $item['source_id'];
                    $invoiceNo = (string) $item['invoice_no'];
                    $key = $this->invoiceKey([
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'invoice_no' => $invoiceNo,
                    ]);

                    if (isset($existingKeys[$key])) {
                        $remarks = $this->cleanRemarks($item['remarks'] ?? null);
                        if ($remarks !== null && (int) $existingKeys[$key] > 0) {
                            DB::connection('accounting')->table('payment_invoice_group_items')
                                ->where('id', $existingKeys[$key])
                                ->update(['remarks' => $remarks, 'updated_at' => now()]);
                        }
                        // Existing group rows must still be rechecked for settlement.
                        // This lets an unpaid/partially-paid invoice already in the group
                        // be auto-paid when the user adds it to the existing group again.
                        $payItemIds[] = (int) $existingKeys[$key];
                        $skipped++;
                        continue;
                    }

                    try {
                        $newId = (int) DB::connection('accounting')->table('payment_invoice_group_items')->insertGetId([
                            'payment_invoice_group_id' => $groupId,
                            'source_type' => $sourceType,
                            'invoice_id' => $sourceId,
                            'invoice_no' => $invoiceNo,
                            'remarks' => $this->cleanRemarks($item['remarks'] ?? null),
                            'process_payment_invoice_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (Throwable $e) {
                        if ((string) $e->getCode() === '23000') {
                            $skipped++;
                            continue;
                        }
                        throw $e;
                    }

                    $existingKeys[$key] = $newId;
                    $addedIds[] = $newId;
                    $payItemIds[] = $newId;
                    $added++;
                }

                $paymentResult = [
                    'success' => true,
                    'payments_created' => 0,
                    'payment_ids' => [],
                    'payment_nos' => [],
                ];
                if ($payItemIds) {
                    $paymentResult = $this->autoPayGroupItems($groupId, $payItemIds, $processClosure);
                    if (!$paymentResult['success']) {
                        throw new \RuntimeException($paymentResult['message'] ?? 'Failed to automatically pay the invoices added to this group.');
                    }
                }

                $this->refreshGroupStatus($groupId);

                return [
                    'success' => true,
                    'group_id' => $groupId,
                    'added' => $added,
                    'skipped' => $skipped,
                    'total_invoices' => (int) DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('payment_invoice_group_id', $groupId)
                        ->count(),
                    'total_amount' => (float) ($this->groupTotalsForIds([$groupId])[$groupId] ?? 0),
                    'status' => $this->currentGroupStatus($groupId),
                    'group_status' => $this->currentGroupStatus($groupId),
                    'payments_created' => (int) ($paymentResult['payments_created'] ?? 0),
                    'payment_ids' => $paymentResult['payment_ids'] ?? [],
                    'payment_nos' => $paymentResult['payment_nos'] ?? [],
                ];
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function list(Request $request): array
    {
        $search = trim((string) $request->input('search', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 15; // W68_PAYMENTS_GROUP_PAGE_15_20260909
        $activity = strtolower(trim((string) $request->input('activity', 'active')));
        if (!in_array($activity, ['active', 'not_active', 'all'], true)) {
            $activity = 'active';
        }

        // A group is Active while at least one invoice was inserted today or
        // within the previous 3 calendar days. Once the latest invoice is
        // older than that window (4+ calendar days untouched), it is Not Active.
        // This is a display/filter state only; it does not rewrite g.status.
        $activityCutoff = \Carbon\Carbon::now('Asia/Manila')->startOfDay()->subDays(3)->setTimezone(config('app.timezone', 'UTC'));

        $groups = DB::connection('accounting')->table('payment_invoice_groups as g')
            ->leftJoin('payment_invoice_group_items as gi', 'gi.payment_invoice_group_id', '=', 'g.id')
            ->select(
                'g.id',
                'g.title',
                'g.grouped_date',
                'g.status',
                'g.created_by',
                DB::raw('COUNT(gi.id) as total_invoices'),
                DB::raw('MAX(gi.created_at) as last_invoice_added_at')
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('g.title', 'like', '%' . $search . '%')
                        ->orWhere('g.grouped_date', 'like', '%' . $search . '%')
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->select(DB::raw(1))
                                ->from('payment_invoice_group_items as gi2')
                                ->whereColumn('gi2.payment_invoice_group_id', 'g.id')
                                ->where('gi2.invoice_no', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($activity === 'active', function ($q) use ($activityCutoff) {
                $q->whereExists(function ($sub) use ($activityCutoff) {
                    $sub->select(DB::raw(1))
                        ->from('payment_invoice_group_items as gia')
                        ->whereColumn('gia.payment_invoice_group_id', 'g.id')
                        ->where('gia.created_at', '>=', $activityCutoff);
                });
            })
            ->when($activity === 'not_active', function ($q) use ($activityCutoff) {
                $q->whereNotExists(function ($sub) use ($activityCutoff) {
                    $sub->select(DB::raw(1))
                        ->from('payment_invoice_group_items as gia')
                        ->whereColumn('gia.payment_invoice_group_id', 'g.id')
                        ->where('gia.created_at', '>=', $activityCutoff);
                });
            })
            ->groupBy('g.id', 'g.title', 'g.grouped_date', 'g.status', 'g.created_by')
            ->orderByDesc('g.id');

        // Counting groups is cheap compared with resolving every invoice amount.
        // Do not call groupTotalAmount() here: that caused an N+1 explosion on
        // groups/data because every group reopened every invoice individually.
        $total = (clone $groups)->get()->count();
        $pageRows = $groups->forPage($page, $perPage)->get();
        $groupIds = $pageRows->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $totals = $this->groupTotalsForIds($groupIds);

        $rows = $pageRows->map(function ($row) use ($totals, $activityCutoff) {
            $lastAddedAt = $row->last_invoice_added_at ? \Carbon\Carbon::parse($row->last_invoice_added_at) : null;
            $activityStatus = $lastAddedAt && $lastAddedAt->greaterThanOrEqualTo($activityCutoff)
                ? 'active'
                : 'not_active';

            return [
                'id' => (int) $row->id,
                'title' => $row->title,
                'grouped_date' => $row->grouped_date,
                'status' => $row->status,
                'created_by' => $row->created_by ?: '---',
                'total_invoices' => (int) $row->total_invoices,
                'total_amount' => round((float) ($totals[(int) $row->id] ?? 0), 2),
                'activity_status' => $activityStatus,
                'last_invoice_added_at' => $lastAddedAt?->copy()->setTimezone('Asia/Manila')->format('Y-m-d H:i:s'),
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int) $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'activity' => $activity,
        ];
    }

    public function detail(int $groupId): array
    {
        $this->ensureGroupRemarksColumn();
        $this->ensureGroupDiscountColumns();
        $this->ensurePaidGroupColumns();
        $this->ensureGroupManualReturnsTable();

        $group = DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->first();

        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->orderBy('id')
            ->get();

        $linkedIds = $items->pluck('process_payment_invoice_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $linkedInvoices = $linkedIds->isEmpty()
            ? collect()
            : DB::connection('accounting')->table('process_payment_invoices as ppi')
                ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
                ->whereIn('ppi.id', $linkedIds)
                ->select('ppi.*', 'pp.payment_no', 'pp.payment_date')
                ->get()
                ->keyBy('id');

        $returnMap = $this->buildReturnMap();
        $settlementMap = $this->buildSettlementMap();
        $rows = [];

        foreach ($items as $item) {
            $isOnline = $item->source_type === 'online_report';
            $invoiceNo = $isOnline ? (string) $item->invoice_no : '';
            $sourceAmount = (float) $this->sourceDocumentAmount($item->source_type, (int) $item->invoice_id, $invoiceNo);
            $returned = $this->lookupReturnedAmount((string) $item->invoice_no, $returnMap);
            $linked = $item->process_payment_invoice_id ? $linkedInvoices->get((int) $item->process_payment_invoice_id) : null;

            if (!$linked) {
                $linked = $this->latestPaymentInvoiceForItem($item);
                if ($linked && !empty($linked->id)) {
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('id', $item->id)
                        ->update(['process_payment_invoice_id' => (int) $linked->id, 'updated_at' => now()]);
                }
            }

            $paidKey = $item->source_type . ':' . $item->invoice_id . ($isOnline ? ':' . $item->invoice_no : '');
            $settlement = $settlementMap[$paidKey] ?? ['paid' => 0.0, 'adjustment' => 0.0];
            $paidToDate = (float) ($settlement['paid'] ?? 0);
            $recordedAdjustment = (float) ($settlement['adjustment'] ?? 0);
            $effectiveAdjustment = max($returned, $recordedAdjustment);
            $invoiceDate = $this->sourceDocumentDate((string) $item->source_type, (int) $item->invoice_id, $invoiceNo);

            $rows[] = [
                'id' => (int) $item->id,
                'source_type' => (string) $item->source_type,
                'source_id' => (int) $item->invoice_id,
                'invoice_no' => (string) $item->invoice_no,
                'invoice_amount' => round((float) ($linked->invoice_amount ?? $sourceAmount), 2),
                'due_amount' => round(max($sourceAmount - $paidToDate - $effectiveAdjustment, 0), 2),
                'returned_amount' => round($returned, 2),
                'paid_to_date' => round($paidToDate, 2),
                'adjustment' => round((float) ($linked->adjustment ?? $recordedAdjustment), 2),
                'paid_amount' => round((float) ($linked->paid_amount ?? $paidToDate), 2),
                'payment_no' => (string) ($linked->payment_no ?? '---'),
                'payment_id' => isset($linked->process_payment_id) ? (int) $linked->process_payment_id : 0,
                'process_payment_invoice_id' => isset($linked->id) ? (int) $linked->id : 0,
                'can_edit_paid_amount' => isset($linked->id) && (int) $linked->id > 0,
                'date' => $invoiceDate,
                'invoice_date' => $invoiceDate,
                'remarks' => (string) ($item->remarks ?? ''),
            ];
        }

        $savedOnlinePercent = (float) ($group->online_percent ?? 0);
        $savedOnlinePayment = (float) ($group->online_payment ?? 0);

        // Backward-compatible fallback for Groups printed before the Group-level
        // Online Percent / Online Payment columns existed. Use only the most
        // recently updated set of linked Collection records so separate older
        // print runs are not added together.
        if ($savedOnlinePercent == 0.0 && $savedOnlinePayment == 0.0
            && Schema::connection('accounting')->hasColumn('process_payments', 'payment_invoice_group_id')
            && Schema::connection('accounting')->hasColumn('process_payments', 'online_percent')
            && Schema::connection('accounting')->hasColumn('process_payments', 'online_payment')) {
            $legacySavedRows = DB::connection('accounting')->table('process_payments')
                ->where('payment_invoice_group_id', $groupId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(['id', 'online_percent', 'online_payment', 'updated_at']);

            if ($legacySavedRows->isNotEmpty()) {
                $latestUpdatedAt = (string) ($legacySavedRows->first()->updated_at ?? '');
                $latestRows = $legacySavedRows->filter(fn ($row) => (string) ($row->updated_at ?? '') === $latestUpdatedAt);
                $savedOnlinePercent = (float) ($latestRows->first()->online_percent ?? 0);
                $savedOnlinePayment = round((float) $latestRows->sum(fn ($row) => (float) ($row->online_payment ?? 0)), 2);

                if ($savedOnlinePercent != 0.0 || $savedOnlinePayment != 0.0) {
                    DB::connection('accounting')->table('payment_invoice_groups')
                        ->where('id', $groupId)
                        ->update([
                            'online_percent' => $savedOnlinePercent,
                            'online_payment' => $savedOnlinePayment,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        $savedReturns = $this->groupManualReturns($groupId);

        return [
            'success' => true,
            'group' => [
                'id' => (int) $group->id,
                'title' => (string) $group->title,
                'grouped_date' => (string) $group->grouped_date,
                'status' => (string) $group->status,
                'created_by' => (string) ($group->created_by ?? ''),
                'discount_percent' => (float) ($group->discount_percent ?? 0),
                'discount_amount' => (float) ($group->discount_amount ?? 0),
                'online_percent' => $savedOnlinePercent,
                'online_payment' => $savedOnlinePayment,
            ],
            'items' => $rows,
            'returns' => $savedReturns,
        ];
    }

    public function updateItem(int $groupId, int $itemId, array $payload): array
    {
        $item = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return ['success' => false, 'message' => 'Group invoice was not found.'];
        }

        $hasRemarks = array_key_exists('remarks', $payload);
        $hasPaidAmount = array_key_exists('paid_amount', $payload);
        if (!$hasRemarks && !$hasPaidAmount) {
            return ['success' => false, 'message' => 'Nothing to save.'];
        }

        $remarks = $hasRemarks ? $this->cleanRemarks($payload['remarks'] ?? null) : ($item->remarks ?? null);
        $paidAmount = null;
        if ($hasPaidAmount) {
            if (!is_numeric($payload['paid_amount'])) {
                return ['success' => false, 'message' => 'Amount Paid must be a valid number.'];
            }
            $paidAmount = round(max(0, (float) $payload['paid_amount']), 2);
        }

        try {
            return DB::connection('accounting')->transaction(function () use ($groupId, $item, $hasRemarks, $remarks, $hasPaidAmount, $paidAmount) {
                if ($hasRemarks) {
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('id', (int) $item->id)
                        ->update([
                            'remarks' => $remarks,
                            'updated_at' => now(),
                        ]);
                }

                $linked = null;
                $groupItemsHavePaymentLink = Schema::connection('accounting')
                    ->hasColumn('payment_invoice_group_items', 'process_payment_invoice_id');
                if ($groupItemsHavePaymentLink && !empty($item->process_payment_invoice_id)) {
                    $linked = DB::connection('accounting')->table('process_payment_invoices')
                        ->where('id', (int) $item->process_payment_invoice_id)
                        ->first();
                }
                if (!$linked) {
                    $linked = $this->latestPaymentInvoiceForItem($item);
                    if ($groupItemsHavePaymentLink && $linked && !empty($linked->id)) {
                        DB::connection('accounting')->table('payment_invoice_group_items')
                            ->where('id', (int) $item->id)
                            ->update([
                                'process_payment_invoice_id' => (int) $linked->id,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($hasPaidAmount) {
                    if (!$linked) {
                        throw new \RuntimeException('This grouped invoice does not have a linked Process Payment record yet.');
                    }

                    $dueAmount = max(0, (float) ($linked->due_amount ?? 0));
                    $invoiceStatus = $paidAmount >= ($dueAmount - 0.005) ? 'Full' : 'Partial';
                    $invoiceUpdate = [
                        'paid_amount' => $paidAmount,
                        'payment_status' => $invoiceStatus,
                        'updated_at' => now(),
                    ];
                    if ($hasRemarks) {
                        $invoiceUpdate['remarks'] = $remarks;
                    }

                    DB::connection('accounting')->table('process_payment_invoices')
                        ->where('id', (int) $linked->id)
                        ->update($invoiceUpdate);

                    $processPaymentId = (int) ($linked->process_payment_id ?? 0);
                    if ($processPaymentId > 0) {
                        $paymentTotal = round((float) DB::connection('accounting')->table('process_payment_invoices')
                            ->where('process_payment_id', $processPaymentId)
                            ->sum('paid_amount'), 2);

                        DB::connection('accounting')->table('process_payments')
                            ->where('id', $processPaymentId)
                            ->update([
                                'total_paid' => $paymentTotal,
                                'updated_at' => now(),
                            ]);
                    }
                } elseif ($hasRemarks && $linked) {
                    DB::connection('accounting')->table('process_payment_invoices')
                        ->where('id', (int) $linked->id)
                        ->update([
                            'remarks' => $remarks,
                            'updated_at' => now(),
                        ]);
                }

                // Keep the parent Process Payment remarks synchronized with edits made
                // from the Payment Group modal. This applies to remarks-only edits and
                // edits submitted together with Amount Paid.
                if ($hasRemarks && $linked && !empty($linked->process_payment_id)) {
                    DB::connection('accounting')->table('process_payments')
                        ->where('id', (int) $linked->process_payment_id)
                        ->update([
                            'remarks' => $remarks,
                            'updated_at' => now(),
                        ]);
                }

                // Remarks-only edits must not be blocked by payment/status resolution.
                // Recalculate the group status only when Amount Paid actually changed.
                if ($hasPaidAmount) {
                    $this->refreshGroupStatus($groupId);
                }

                $freshPaid = $linked
                    ? (float) DB::connection('accounting')->table('process_payment_invoices')->where('id', (int) $linked->id)->value('paid_amount')
                    : 0.0;

                return [
                    'success' => true,
                    'group_status' => $this->currentGroupStatus($groupId),
                    'item' => [
                        'id' => (int) $item->id,
                        'paid_amount' => round($freshPaid, 2),
                        'remarks' => (string) ($remarks ?? ''),
                    ],
                ];
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteItem(int $groupId, int $itemId): array
    {
        $item = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return ['success' => false, 'message' => 'Group invoice was not found.'];
        }

        try {
            return DB::connection('accounting')->transaction(function () use ($groupId, $item) {
                $linked = null;
                if (!empty($item->process_payment_invoice_id)) {
                    $linked = DB::connection('accounting')->table('process_payment_invoices')
                        ->where('id', (int) $item->process_payment_invoice_id)
                        ->first();
                }
                if (!$linked) {
                    $linked = $this->latestPaymentInvoiceForItem($item);
                }

                $deletedPaymentId = null;
                $deletedPaymentNo = null;
                if ($linked) {
                    $ppiId = (int) $linked->id;
                    $paymentId = (int) $linked->process_payment_id;
                    $parent = DB::connection('accounting')->table('process_payments')
                        ->where('id', $paymentId)
                        ->first();
                    $deletedPaymentId = $paymentId;
                    $deletedPaymentNo = $parent?->payment_no;

                    // Clear every Group reference before removing the payment invoice.
                    // This prevents old Group rows from pointing to a deleted PPI id.
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('process_payment_invoice_id', $ppiId)
                        ->update([
                            'process_payment_invoice_id' => null,
                            'updated_at' => now(),
                        ]);

                    DB::connection('accounting')->table('process_payment_returns')
                        ->where('process_payment_invoice_id', $ppiId)
                        ->delete();
                    DB::connection('accounting')->table('process_payment_invoices')
                        ->where('id', $ppiId)
                        ->delete();

                    $remainingInvoices = DB::connection('accounting')->table('process_payment_invoices')
                        ->where('process_payment_id', $paymentId)
                        ->get(['invoice_amount', 'paid_amount']);

                    if ($remainingInvoices->isEmpty()) {
                        DB::connection('accounting')->table('process_payment_returns')
                            ->where('process_payment_id', $paymentId)
                            ->delete();
                        DB::connection('accounting')->table('process_payment_checks')
                            ->where('process_payment_id', $paymentId)
                            ->delete();
                        DB::connection('accounting')->table('process_payments')
                            ->where('id', $paymentId)
                            ->delete();
                    } else {
                        $update = [
                            'total_paid' => round((float) $remainingInvoices->sum(fn ($row) => (float) $row->paid_amount), 2),
                            'updated_at' => now(),
                        ];
                        if (Schema::connection('accounting')->hasColumn('process_payments', 'retotal_amount')) {
                            $update['retotal_amount'] = round((float) $remainingInvoices->sum(fn ($row) => (float) $row->invoice_amount), 2);
                        }
                        DB::connection('accounting')->table('process_payments')
                            ->where('id', $paymentId)
                            ->update($update);
                    }
                }

                DB::connection('accounting')->table('payment_invoice_group_items')
                    ->where('payment_invoice_group_id', $groupId)
                    ->where('id', (int) $item->id)
                    ->delete();

                $this->refreshGroupStatus($groupId);

                return [
                    'success' => true,
                    'group_status' => $this->currentGroupStatus($groupId),
                    'deleted_item_id' => (int) $item->id,
                    'deleted_payment_id' => $deletedPaymentId,
                    'deleted_payment_no' => $deletedPaymentNo,
                ];
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteGroup(int $groupId): array
    {
        $group = DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->first();

        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->orderBy('id')
            ->get();

        try {
            return DB::connection('accounting')->transaction(function () use ($groupId, $group, $items) {
                $normalizeInvoice = static fn ($value) => strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
                $groupKeys = [];
                $sourceIdsByType = [];

                foreach ($items as $item) {
                    $sourceType = (string) $item->source_type;
                    $sourceId = (int) $item->invoice_id;
                    $invoiceKey = $sourceType === 'online_report'
                        ? $normalizeInvoice($item->invoice_no)
                        : '';
                    $groupKeys[$sourceType . ':' . $sourceId . ':' . $invoiceKey] = true;
                    $sourceIdsByType[$sourceType][] = $sourceId;
                }

                // Resolve every settlement for the grouped invoices, not only the latest
                // linked row. This is required so deleting the group makes each invoice
                // fully unpaid again even when it had multiple/partial Payment Nos.
                $paymentInvoices = collect();
                foreach ($sourceIdsByType as $sourceType => $sourceIds) {
                    $sourceIds = array_values(array_unique(array_map('intval', $sourceIds)));
                    if (!$sourceIds) continue;

                    $candidates = DB::connection('accounting')->table('process_payment_invoices')
                        ->where('source_type', $sourceType)
                        ->whereIn('source_id', $sourceIds)
                        ->get();

                    foreach ($candidates as $candidate) {
                        $invoiceKey = $sourceType === 'online_report'
                            ? $normalizeInvoice($candidate->invoice_no)
                            : '';
                        $key = $sourceType . ':' . (int) $candidate->source_id . ':' . $invoiceKey;
                        if (isset($groupKeys[$key])) {
                            $paymentInvoices->push($candidate);
                        }
                    }
                }

                $paymentInvoices = $paymentInvoices->unique('id')->values();
                $ppiIds = $paymentInvoices->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                $paymentIds = $paymentInvoices->pluck('process_payment_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

                if ($ppiIds) {
                    // Remove all Group pointers before deleting their settlement rows.
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->whereIn('process_payment_invoice_id', $ppiIds)
                        ->update(['process_payment_invoice_id' => null, 'updated_at' => now()]);

                    DB::connection('accounting')->table('process_payment_returns')
                        ->whereIn('process_payment_invoice_id', $ppiIds)
                        ->delete();

                    DB::connection('accounting')->table('process_payment_invoices')
                        ->whereIn('id', $ppiIds)
                        ->delete();
                }

                // A Payment No. can theoretically contain invoices outside this group.
                // Delete the whole payment only when it is now empty; otherwise preserve
                // unrelated invoices and retotal the parent payment from the survivors.
                foreach ($paymentIds as $paymentId) {
                    $remaining = DB::connection('accounting')->table('process_payment_invoices')
                        ->where('process_payment_id', $paymentId)
                        ->get(['invoice_amount', 'paid_amount']);

                    if ($remaining->isEmpty()) {
                        DB::connection('accounting')->table('process_payment_returns')
                            ->where('process_payment_id', $paymentId)
                            ->delete();
                        DB::connection('accounting')->table('process_payment_checks')
                            ->where('process_payment_id', $paymentId)
                            ->delete();
                        DB::connection('accounting')->table('process_payments')
                            ->where('id', $paymentId)
                            ->delete();
                        continue;
                    }

                    $update = [
                        'total_paid' => round((float) $remaining->sum(fn ($row) => (float) $row->paid_amount), 2),
                        'updated_at' => now(),
                    ];
                    if (Schema::connection('accounting')->hasColumn('process_payments', 'retotal_amount')) {
                        $update['retotal_amount'] = round((float) $remaining->sum(fn ($row) => (float) $row->invoice_amount), 2);
                    }
                    DB::connection('accounting')->table('process_payments')
                        ->where('id', $paymentId)
                        ->update($update);
                }

                if (Schema::connection('accounting')->hasTable('payment_invoice_group_returns')) {
                    DB::connection('accounting')->table('payment_invoice_group_returns')
                        ->where('payment_invoice_group_id', $groupId)
                        ->delete();
                }

                DB::connection('accounting')->table('payment_invoice_group_items')
                    ->where('payment_invoice_group_id', $groupId)
                    ->delete();

                DB::connection('accounting')->table('payment_invoice_groups')
                    ->where('id', $groupId)
                    ->delete();

                return [
                    'success' => true,
                    'deleted_group_id' => $groupId,
                    'deleted_group_title' => (string) ($group->title ?? ''),
                    'unpaid_invoices' => (int) $items->count(),
                    'deleted_payment_invoice_rows' => count($ppiIds),
                    'affected_payment_ids' => $paymentIds,
                ];
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build Collection Invoice data for selected paid group items.
     * The selected invoice total is preserved as Re-Total while Online Percent
     * and Online Payment are stored independently as print metadata.
     */
    public function printableItems(int $groupId, array $itemIds, float $onlinePercent = 0, float $onlinePayment = 0, array $manualReturns = []): array
    {
        $this->ensureGroupRemarksColumn();
        $this->ensureGroupDiscountColumns();
        $this->ensurePaidGroupColumns();

        $group = DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->first();

        if (!$group) {
            return ['success' => false, 'message' => 'Group not found.'];
        }

        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds), fn ($id) => $id > 0)));
        if (!$itemIds) {
            return ['success' => false, 'message' => 'Select at least one paid invoice to print.'];
        }

        // Keep the exact item order supplied by the Group modal. This lets the
        // Regular Payments drag-and-drop arrangement flow through to Collection
        // Invoice printing without adding or changing any database columns.
        $itemRows = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy(fn ($row) => (int) $row->id);

        $items = collect($itemIds)
            ->map(fn ($id) => $itemRows->get((int) $id))
            ->filter()
            ->values();

        if ($items->count() !== count($itemIds)) {
            return ['success' => false, 'message' => 'One or more selected invoices do not belong to this group.'];
        }

        $resolved = [];
        foreach ($items as $sortOrder => $item) {
            $ppi = null;
            if (!empty($item->process_payment_invoice_id)) {
                $ppi = DB::connection('accounting')->table('process_payment_invoices')
                    ->where('id', (int) $item->process_payment_invoice_id)
                    ->first();
            }
            if (!$ppi) {
                $ppi = $this->latestPaymentInvoiceForItem($item);
            }
            if (!$ppi) {
                return ['success' => false, 'message' => 'Invoice ' . $item->invoice_no . ' does not have a paid Collection record yet.'];
            }
            if (empty($item->process_payment_invoice_id)) {
                DB::connection('accounting')->table('payment_invoice_group_items')
                    ->where('id', $item->id)
                    ->update(['process_payment_invoice_id' => (int) $ppi->id, 'updated_at' => now()]);
            }
            $resolved[] = [
                'group_item' => $item,
                'invoice' => $ppi,
                'sort_order' => (int) $sortOrder,
            ];
        }

        $onlinePercent = max(0, min(100, $onlinePercent));
        $onlinePayment = round($onlinePayment, 2);
        $manualReturns = $this->normalizeManualReturns($manualReturns);
        $byPayment = collect($resolved)->groupBy(fn ($row) => (int) $row['invoice']->process_payment_id);
        $totalRetotal = round(collect($resolved)->sum(fn ($row) => (float) $row['invoice']->invoice_amount), 2);
        $remainingOnlinePayment = $onlinePayment;
        $receipts = [];
        $paymentIds = $byPayment->keys()->values()->all();

        foreach ($byPayment as $paymentId => $rows) {
            $payment = DB::connection('accounting')->table('process_payments')->where('id', $paymentId)->first();
            if (!$payment) {
                return ['success' => false, 'message' => 'A Collection record linked to this group is missing.'];
            }

            $receiptRetotal = round($rows->sum(fn ($row) => (float) $row['invoice']->invoice_amount), 2);
            $isLast = ((int) $paymentId === (int) end($paymentIds));
            $allocatedOnlinePayment = $isLast
                ? $remainingOnlinePayment
                : round($totalRetotal != 0 ? $onlinePayment * ($receiptRetotal / $totalRetotal) : 0, 2);
            $remainingOnlinePayment = round($remainingOnlinePayment - $allocatedOnlinePayment, 2);

            DB::connection('accounting')->table('process_payments')->where('id', $paymentId)->update([
                'payment_invoice_group_id' => $groupId,
                'online_percent' => $onlinePercent,
                'online_payment' => $allocatedOnlinePayment,
                'retotal_amount' => $receiptRetotal,
                'updated_at' => now(),
            ]);

            $customer = DB::connection('masterlist')->table('customers')->where('id', $payment->customer_id)->first();
            $returns = $manualReturns;

            $invoiceRows = $rows->map(function ($row) use ($group) {
                $item = $row['group_item'];
                $inv = $row['invoice'];
                $invoiceNo = $item->source_type === 'online_report' ? (string) $item->invoice_no : '';
                $invoiceDate = $this->sourceDocumentDate((string) $item->source_type, (int) $item->invoice_id, $invoiceNo) ?: '';
                return [
                    'id' => (int) $item->id,
                    'process_payment_invoice_id' => (int) $inv->id,
                    'source_type' => (string) $item->source_type,
                    'source_id' => (int) $item->invoice_id,
                    'invoice_no' => (string) $item->invoice_no,
                    'date' => $invoiceDate,
                    'invoice_date' => $invoiceDate,
                    'invoice_amount' => (float) $inv->invoice_amount,
                    'adjustment' => (float) $inv->adjustment,
                    'paid_amount' => (float) $inv->paid_amount,
                    'remarks' => (string) ($item->remarks ?? $inv->remarks ?? ''),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                ];
            })->values()->all();

            $receipts[] = [
                'payment_id' => (int) $paymentId,
                'collection_no' => (string) $payment->payment_no,
                'payment_date' => (string) $payment->payment_date,
                'customer' => $customer ? (array) $customer : [
                    'id' => (int) $payment->customer_id,
                    'name' => (string) $payment->customer_name,
                    'address' => '',
                ],
                'invoices' => $invoiceRows,
                'returns' => $returns,
                'subtotal' => round(collect($invoiceRows)->sum(fn ($inv) => (float) $inv['paid_amount']), 2),
                'online_percent' => $onlinePercent,
                'online_payment' => $allocatedOnlinePayment,
                'retotal_amount' => $receiptRetotal,
            ];
        }

        // Persist the manual print Returns and Online values on this exact Group.
        // These Returns are user-entered print metadata only; they never create or
        // modify process_payment_returns and never affect invoice adjustments.
        $this->saveGroupManualReturns($groupId, $manualReturns);
        DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->update([
                'online_percent' => $onlinePercent,
                'online_payment' => $onlinePayment,
                'updated_at' => now(),
            ]);

        return [
            'success' => true,
            'group' => [
                'id' => (int) $group->id,
                'title' => (string) $group->title,
                'grouped_date' => (string) $group->grouped_date,
                'status' => (string) $group->status,
                'online_percent' => $onlinePercent,
                'online_payment' => $onlinePayment,
                'returns' => $manualReturns,
            ],
            'returns' => $manualReturns,
            'receipts' => $receipts,
        ];
    }

    private function normalizeManualReturns(array $returns): array
    {
        $normalized = [];
        foreach ($returns as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $returnOrderId = trim((string) ($row['return_order_id'] ?? ''));
            $returnNumber = trim((string) ($row['return_number'] ?? ''));
            $walletRaw = $row['wallet_adjustment'] ?? null;
            $walletAdjustment = ($walletRaw === '' || $walletRaw === null) ? null : round((float) $walletRaw, 2);

            if ($returnOrderId === '' && $returnNumber === '' && $walletAdjustment === null) {
                continue;
            }

            $normalized[] = [
                'return_order_id' => substr($returnOrderId, 0, 255),
                'wallet_adjustment' => $walletAdjustment,
                'return_number' => substr($returnNumber, 0, 255),
                'sort_order' => count($normalized),
            ];
        }

        return $normalized;
    }

    private function groupManualReturns(int $groupId): array
    {
        $this->ensureGroupManualReturnsTable();

        return DB::connection('accounting')->table('payment_invoice_group_returns')
            ->where('payment_invoice_group_id', $groupId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'return_order_id' => (string) ($row->return_order_id ?? ''),
                'wallet_adjustment' => $row->wallet_adjustment === null ? null : (float) $row->wallet_adjustment,
                'return_number' => (string) ($row->return_number ?? ''),
            ])
            ->values()
            ->all();
    }

    private function saveGroupManualReturns(int $groupId, array $returns): void
    {
        $this->ensureGroupManualReturnsTable();

        DB::connection('accounting')->table('payment_invoice_group_returns')
            ->where('payment_invoice_group_id', $groupId)
            ->delete();

        foreach ($returns as $index => $row) {
            DB::connection('accounting')->table('payment_invoice_group_returns')->insert([
                'payment_invoice_group_id' => $groupId,
                'return_order_id' => (string) ($row['return_order_id'] ?? ''),
                'wallet_adjustment' => $row['wallet_adjustment'] ?? null,
                'return_number' => (string) ($row['return_number'] ?? ''),
                'sort_order' => (int) ($row['sort_order'] ?? $index),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureGroupManualReturnsTable(): void
    {
        $schema = Schema::connection('accounting');

        if (!$schema->hasTable('payment_invoice_group_returns')) {
            $schema->create('payment_invoice_group_returns', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('payment_invoice_group_id');
                $table->string('return_order_id', 255)->nullable();
                $table->decimal('wallet_adjustment', 15, 2)->nullable();
                $table->string('return_number', 255)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index('payment_invoice_group_id', 'pig_returns_group_id_index');
            });

            return;
        }

        if (!$schema->hasColumn('payment_invoice_group_returns', 'payment_invoice_group_id')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->unsignedBigInteger('payment_invoice_group_id')->default(0)->after('id');
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'return_order_id')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->string('return_order_id', 255)->nullable()->after('payment_invoice_group_id');
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'wallet_adjustment')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->decimal('wallet_adjustment', 15, 2)->nullable()->after('return_order_id');
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'return_number')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->string('return_number', 255)->nullable()->after('wallet_adjustment');
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'sort_order')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->unsignedInteger('sort_order')->default(0)->after('return_number');
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'created_at')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->timestamp('created_at')->nullable();
            });
        }
        if (!$schema->hasColumn('payment_invoice_group_returns', 'updated_at')) {
            $schema->table('payment_invoice_group_returns', function ($table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function sourceDocumentDate(string $sourceType, int $sourceId, string $invoiceNo = ''): ?string
    {
        if ($sourceType === 'sales_order') {
            $value = DB::connection('sales')->table('sales_orders')->where('id', $sourceId)->value('created_at');
        } elseif ($sourceType === 'consignment_invoice') {
            $value = DB::connection('sales')->table('consignment_invoices')->where('id', $sourceId)->value('created_at');
        } elseif ($sourceType === 'online_report') {
            // An Online Report note carries the marketplace/order transaction date.
            // The user-facing Invoice Date must instead be the date the finalized
            // ONL Sales Order/invoice record was actually created.
            $value = $this->onlineFinalizedInvoiceCreatedAt($sourceId, $invoiceNo);
            if (!$value) {
                $value = DB::connection('sales')->table('online_reports')->where('id', $sourceId)->value('created_at');
            }
        } else {
            return null;
        }

        if (!$value) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }

    private function onlineFinalizedInvoiceCreatedAt(int $reportId, string $invoiceNo): mixed
    {
        $target = strtoupper(preg_replace('/\s+/', '', trim($invoiceNo)));
        if ($target === '') {
            return null;
        }

        $orders = DB::connection('sales')->table('sales_orders')
            ->where('order_number', 'LIKE', 'ONL-' . $reportId . '-%')
            ->whereIn('status', ['Confirmed', 'Closed'])
            ->orderByDesc('id')
            ->get(['invoice_numbers', 'created_at']);

        foreach ($orders as $order) {
            $values = json_decode((string) ($order->invoice_numbers ?? ''), true);
            if (!is_array($values)) {
                $values = preg_split('/[,\n\r]+/', (string) ($order->invoice_numbers ?? '')) ?: [];
            }
            foreach ($values as $value) {
                $candidate = strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
                if ($candidate !== '' && $candidate === $target) {
                    return $order->created_at;
                }
            }
        }

        return null;
    }

    public function processGroup(int $groupId, array $checkedInvoices, $processClosure, float $discountPercent = 0): array
    {
        return [
            'success' => false,
            'message' => 'Group invoices are paid automatically when they are added to a group. Process Selected is no longer required.',
            'payments_created' => 0,
            'group_status' => $this->currentGroupStatus($groupId),
        ];
    }

    private function validateGroupItems(array $items): array
    {
        $seen = [];
        foreach ($items as $item) {
            $sourceType = (string) ($item['source_type'] ?? '');
            $sourceId = (int) ($item['source_id'] ?? 0);
            $invoiceNo = trim((string) ($item['invoice_no'] ?? ''));
            if ($sourceType === '' || $sourceId <= 0 || $invoiceNo === '') {
                return ['success' => false, 'message' => 'Invalid invoice selected.'];
            }
            $key = $this->invoiceKey([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'invoice_no' => $invoiceNo,
            ]);
            if (isset($seen[$key])) {
                return ['success' => false, 'message' => 'Duplicate invoice selected.'];
            }
            $seen[$key] = true;
            if (!$this->sourceExists($sourceType, $sourceId)) {
                return ['success' => false, 'message' => 'Invoice ' . $invoiceNo . ' is no longer available.'];
            }
        }

        return ['success' => true];
    }

    private function autoPayGroupItems(int $groupId, array $groupItemIds, $processClosure): array
    {
        $groupItemIds = array_values(array_unique(array_filter(array_map('intval', $groupItemIds), fn ($id) => $id > 0)));
        if (!$groupItemIds) {
            return ['success' => true, 'payments_created' => 0, 'payment_ids' => [], 'payment_nos' => []];
        }

        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->whereIn('id', $groupItemIds)
            ->orderBy('id')
            ->get();

        $returnMap = $this->buildReturnMap();
        $returnIndex = $this->buildReturnDetailIndex();
        $settlementMap = $this->buildSettlementMap();
        $usedReturnIds = DB::connection('accounting')->table('process_payment_returns')
            ->pluck('sales_return_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $partitions = [];
        foreach ($items as $item) {
            // Do not skip just because this group item already points to a payment row.
            // A linked row can be partial. Settlement below decides whether anything
            // remains due and safely creates only the remaining payment when needed.
            $isOnline = $item->source_type === 'online_report';
            $invoiceNo = $isOnline ? (string) $item->invoice_no : '';
            $amount = round((float) $this->sourceDocumentAmount((string) $item->source_type, (int) $item->invoice_id, $invoiceNo), 2);
            $returned = round($this->lookupReturnedAmount((string) $item->invoice_no, $returnMap), 2);
            $paidKey = $item->source_type . ':' . $item->invoice_id . ($isOnline ? ':' . $item->invoice_no : '');
            $settlement = $settlementMap[$paidKey] ?? ['paid' => 0.0, 'adjustment' => 0.0];
            $paidToDate = round((float) ($settlement['paid'] ?? 0), 2);
            $recordedAdjustment = round((float) ($settlement['adjustment'] ?? 0), 2);
            $effectiveAdjustment = max($returned, $recordedAdjustment);
            $remainingReturnAdjustment = round(min(max($returned - $recordedAdjustment, 0), max($amount - $paidToDate, 0)), 2);
            $salesNoteAmount = $this->sourceSalesNoteAmount((string) $item->source_type, (int) $item->invoice_id);
            $autoSettledByReturn = $returned > 0 && (
                $returned >= $amount
                || ($salesNoteAmount > 0 && $returned >= $salesNoteAmount)
                || $paidToDate >= ($amount / 2)
            );
            $due = round($autoSettledByReturn ? 0 : max($amount - $paidToDate - $effectiveAdjustment, 0), 2);

            if ($due <= 0.00001 && $remainingReturnAdjustment <= 0.00001) {
                $existing = $this->latestPaymentInvoiceForItem($item);
                if ($existing) {
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('id', $item->id)
                        ->update(['process_payment_invoice_id' => (int) $existing->id, 'updated_at' => now()]);
                }
                continue;
            }

            $customer = $this->resolveCustomer((string) $item->source_type, (int) $item->invoice_id, $invoiceNo);
            if (!$customer) {
                return ['success' => false, 'message' => 'Invoice ' . $item->invoice_no . ' has no matching customer record.'];
            }

            $matchingReturns = [];
            if ($remainingReturnAdjustment > 0) {
                $remainingToLink = $remainingReturnAdjustment;
                foreach ($this->matchingReturnRows((string) $item->invoice_no, $returnIndex) as $ret) {
                    $returnId = (int) ($ret['id'] ?? 0);
                    if ($returnId <= 0 || isset($usedReturnIds[$returnId]) || $remainingToLink <= 0.00001) {
                        continue;
                    }
                    $returnAmount = round(min(max((float) ($ret['total_amount'] ?? 0), 0), $remainingToLink), 2);
                    if ($returnAmount <= 0) {
                        continue;
                    }
                    $matchingReturns[] = [
                        'sales_return_id' => $returnId,
                        'return_number' => (string) ($ret['return_number'] ?? ''),
                        'return_amount' => $returnAmount,
                        'source_type' => (string) $item->source_type,
                        'source_id' => (int) $item->invoice_id,
                        'invoice_no' => (string) $item->invoice_no,
                    ];
                    $usedReturnIds[$returnId] = true;
                    $remainingToLink = round($remainingToLink - $returnAmount, 2);
                }
            }

            $partitions[(int) $customer['id']][] = [
                'group_item_id' => (int) $item->id,
                'customer' => $customer,
                'invoice' => [
                    'source_type' => (string) $item->source_type,
                    'source_id' => (int) $item->invoice_id,
                    'invoice_no' => (string) $item->invoice_no,
                    'invoice_amount' => $amount,
                    'due_amount' => $due,
                    'adjustment' => $remainingReturnAdjustment,
                    'paid_amount' => $due,
                    'remarks' => (string) ($item->remarks ?? ''),
                ],
                'returns' => $matchingReturns,
            ];
        }

        $paymentIds = [];
        $paymentNos = [];
        foreach ($partitions as $customerId => $rows) {
            $customer = $rows[0]['customer'];
            $paymentNo = $this->nextPaymentNo();
            $invoicePayload = array_values(array_map(fn ($row) => $row['invoice'], $rows));
            $returnsById = [];
            foreach ($rows as $row) {
                foreach ($row['returns'] as $ret) {
                    $returnsById[(int) $ret['sales_return_id']] = $ret;
                }
            }

            $payload = [
                'customer_id' => (int) $customerId,
                'customer_name' => (string) $customer['name'],
                'payment_no' => $paymentNo,
                'payment_date' => now()->toDateString(),
                'payment_invoice_group_id' => $groupId,
                'online_percent' => 0,
                'online_payment' => 0,
                'retotal_amount' => round(array_sum(array_map(fn ($inv) => (float) $inv['invoice_amount'], $invoicePayload)), 2),
                'invoices' => $invoicePayload,
                'checks' => [],
                'returns' => array_values($returnsById),
            ];

            $subRequest = Request::create('/admin/payments/process', 'POST', $payload, [], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            if (app()->bound('session.store')) {
                $subRequest->setLaravelSession(app('session.store'));
            }

            $response = $processClosure($subRequest);
            $data = json_decode((string) $response->getContent(), true);
            if (!($data['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Failed to automatically process grouped invoices.',
                    'payments_created' => count($paymentIds),
                ];
            }

            $paymentId = (int) ($data['payment_id'] ?? 0);
            if ($paymentId <= 0) {
                return ['success' => false, 'message' => 'Automatic payment was created without a valid payment ID.'];
            }

            $inserted = DB::connection('accounting')->table('process_payment_invoices')
                ->where('process_payment_id', $paymentId)
                ->get();
            foreach ($rows as $row) {
                $targetKey = $this->invoiceKey($row['invoice']);
                $matched = $inserted->first(function ($inv) use ($targetKey) {
                    return $this->invoiceKey([
                        'source_type' => $inv->source_type,
                        'source_id' => $inv->source_id,
                        'invoice_no' => $inv->invoice_no,
                    ]) === $targetKey;
                });
                if ($matched) {
                    DB::connection('accounting')->table('payment_invoice_group_items')
                        ->where('id', $row['group_item_id'])
                        ->update(['process_payment_invoice_id' => (int) $matched->id, 'updated_at' => now()]);
                }
            }

            $paymentIds[] = $paymentId;
            $paymentNos[] = $paymentNo;
        }

        return [
            'success' => true,
            'payments_created' => count($paymentIds),
            'payment_ids' => $paymentIds,
            'payment_nos' => $paymentNos,
        ];
    }

    private function refreshGroupStatus(int $groupId): void
    {
        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->get();

        $fullyPaid = 0;
        $returnMap = $this->buildReturnMap();
        foreach ($items as $item) {
            $isOnline = $item->source_type === 'online_report';
            $amount = (float) $this->sourceDocumentAmount($item->source_type, (int) $item->invoice_id, $isOnline ? (string) $item->invoice_no : '');
            $settlementQuery = DB::connection('accounting')->table('process_payment_invoices')
                ->where('source_type', $item->source_type)
                ->where('source_id', $item->invoice_id);
            if ($isOnline) {
                $settlementQuery->where('invoice_no', (string) $item->invoice_no);
            }
            $settlement = $settlementQuery->selectRaw('COALESCE(SUM(paid_amount),0) as paid_total, COALESCE(SUM(adjustment),0) as adjustment_total')->first();
            $paid = (float) ($settlement->paid_total ?? 0);
            $recordedAdjustment = (float) ($settlement->adjustment_total ?? 0);
            $returned = $this->lookupReturnedAmount((string) $item->invoice_no, $returnMap);
            $settled = $paid + max($recordedAdjustment, $returned);
            if ($amount > 0 && $settled >= $amount) {
                $fullyPaid++;
            }
        }

        $status = 'active';
        if ($fullyPaid === $items->count() && $items->count() > 0) {
            $status = 'processed';
        } elseif ($fullyPaid > 0) {
            $status = 'partially_processed';
        }

        DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    private function currentGroupStatus(int $groupId): string
    {
        return (string) DB::connection('accounting')->table('payment_invoice_groups')
            ->where('id', $groupId)
            ->value('status');
    }

    /**
     * Resolve totals for only the groups visible on the current page.
     * Prefer already-posted process_payment_invoices amounts, then batch-load
     * source documents only for legacy/unlinked rows. Online JSON resolution is
     * the final fallback, not the default path.
     */
    private function groupTotalsForIds(array $groupIds): array
    {
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), fn ($id) => $id > 0)));
        if (!$groupIds) {
            return [];
        }

        $hasLinkedPaymentColumn = Schema::connection('accounting')
            ->hasColumn('payment_invoice_group_items', 'process_payment_invoice_id');

        $select = [
            'id',
            'payment_invoice_group_id',
            'source_type',
            'invoice_id',
            'invoice_no',
        ];
        if ($hasLinkedPaymentColumn) {
            $select[] = 'process_payment_invoice_id';
        }

        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->whereIn('payment_invoice_group_id', $groupIds)
            ->get($select);

        $totals = array_fill_keys($groupIds, 0.0);
        if ($items->isEmpty()) {
            return $totals;
        }

        // 1) Exact linked payment rows (fastest and authoritative for groups that
        // were automatically paid when the invoice was inserted).
        $linkedAmounts = collect();
        if ($hasLinkedPaymentColumn) {
            $linkedIds = $items->pluck('process_payment_invoice_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            if ($linkedIds->isNotEmpty()) {
                $linkedAmounts = DB::connection('accounting')->table('process_payment_invoices')
                    ->whereIn('id', $linkedIds)
                    ->pluck('invoice_amount', 'id');
            }
        }

        $unresolved = [];
        foreach ($items as $item) {
            $amount = null;
            $linkedId = $hasLinkedPaymentColumn ? (int) ($item->process_payment_invoice_id ?? 0) : 0;
            if ($linkedId > 0 && $linkedAmounts->has($linkedId)) {
                $amount = (float) $linkedAmounts->get($linkedId);
            }

            if ($amount === null) {
                $unresolved[] = $item;
                continue;
            }
            $totals[(int) $item->payment_invoice_group_id] += $amount;
        }

        if (!$unresolved) {
            return array_map(fn ($value) => round((float) $value, 2), $totals);
        }

        // 2) Legacy/unlinked group rows: batch-resolve from an existing payment
        // invoice using source identity. This avoids reopening each source row.
        $types = array_values(array_unique(array_map(fn ($item) => (string) $item->source_type, $unresolved)));
        $sourceIds = array_values(array_unique(array_map(fn ($item) => (int) $item->invoice_id, $unresolved)));
        $paymentCandidates = collect();
        if ($types && $sourceIds) {
            $paymentCandidates = DB::connection('accounting')->table('process_payment_invoices')
                ->whereIn('source_type', $types)
                ->whereIn('source_id', $sourceIds)
                ->orderByDesc('id')
                ->get(['id', 'source_type', 'source_id', 'invoice_no', 'invoice_amount']);
        }

        $paymentAmountMap = [];
        foreach ($paymentCandidates as $candidate) {
            $key = $this->invoiceKey([
                'source_type' => $candidate->source_type,
                'source_id' => $candidate->source_id,
                'invoice_no' => $candidate->invoice_no,
            ]);
            if (!array_key_exists($key, $paymentAmountMap)) {
                $paymentAmountMap[$key] = (float) $candidate->invoice_amount;
            }
        }

        $stillUnresolved = [];
        foreach ($unresolved as $item) {
            $key = $this->invoiceKey([
                'source_type' => $item->source_type,
                'source_id' => $item->invoice_id,
                'invoice_no' => $item->invoice_no,
            ]);
            if (array_key_exists($key, $paymentAmountMap)) {
                $totals[(int) $item->payment_invoice_group_id] += (float) $paymentAmountMap[$key];
            } else {
                $stillUnresolved[] = $item;
            }
        }

        if (!$stillUnresolved) {
            return array_map(fn ($value) => round((float) $value, 2), $totals);
        }

        // 3) Batch-load Local/Consignment sources only when no posted Collection
        // amount exists. Online Report JSON is resolved only for the remaining
        // exact invoices.
        $salesOrderIds = array_values(array_unique(array_map(
            fn ($item) => (int) $item->invoice_id,
            array_filter($stillUnresolved, fn ($item) => (string) $item->source_type === 'sales_order')
        )));
        $consignmentIds = array_values(array_unique(array_map(
            fn ($item) => (int) $item->invoice_id,
            array_filter($stillUnresolved, fn ($item) => (string) $item->source_type === 'consignment_invoice')
        )));

        $salesAmounts = $salesOrderIds
            ? DB::connection('sales')->table('sales_orders')->whereIn('id', $salesOrderIds)->pluck('total_amount', 'id')
            : collect();
        $consignmentAmounts = $consignmentIds
            ? DB::connection('sales')->table('consignment_invoices')->whereIn('id', $consignmentIds)->pluck('total_amount', 'id')
            : collect();

        foreach ($stillUnresolved as $item) {
            $sourceType = (string) $item->source_type;
            $sourceId = (int) $item->invoice_id;
            if ($sourceType === 'sales_order') {
                $amount = (float) ($salesAmounts->get($sourceId) ?? 0);
            } elseif ($sourceType === 'consignment_invoice') {
                $amount = (float) ($consignmentAmounts->get($sourceId) ?? 0);
            } elseif ($sourceType === 'online_report') {
                $amount = (float) $this->onlineInvoiceAmountService->resolve($sourceId, (string) $item->invoice_no);
            } else {
                $amount = 0.0;
            }
            $totals[(int) $item->payment_invoice_group_id] += $amount;
        }

        return array_map(fn ($value) => round((float) $value, 2), $totals);
    }

    private function groupTotalAmount(int $groupId): float
    {
        $items = DB::connection('accounting')->table('payment_invoice_group_items')
            ->where('payment_invoice_group_id', $groupId)
            ->get();

        $total = 0;
        foreach ($items as $item) {
            $total += (float) $this->sourceDocumentAmount($item->source_type, (int) $item->invoice_id, $item->source_type === 'online_report' ? (string) $item->invoice_no : '');
        }

        return round($total, 2);
    }

    private function ensurePaidGroupColumns(): void
    {
        $schema = Schema::connection('accounting');

        if ($schema->hasTable('payment_invoice_group_items') && !$schema->hasColumn('payment_invoice_group_items', 'process_payment_invoice_id')) {
            $schema->table('payment_invoice_group_items', function ($table) {
                $table->unsignedBigInteger('process_payment_invoice_id')->nullable()->after('remarks');
            });
        }

        if ($schema->hasTable('payment_invoice_groups')) {
            if (!$schema->hasColumn('payment_invoice_groups', 'online_percent')) {
                $schema->table('payment_invoice_groups', function ($table) {
                    $table->decimal('online_percent', 5, 2)->default(0)->after('discount_amount');
                });
            }
            if (!$schema->hasColumn('payment_invoice_groups', 'online_payment')) {
                $schema->table('payment_invoice_groups', function ($table) {
                    $table->decimal('online_payment', 15, 2)->default(0)->after('online_percent');
                });
            }
        }

        if ($schema->hasTable('process_payments')) {
            if (!$schema->hasColumn('process_payments', 'payment_invoice_group_id')) {
                $schema->table('process_payments', function ($table) {
                    $table->unsignedBigInteger('payment_invoice_group_id')->nullable()->after('payment_no');
                });
            }
            if (!$schema->hasColumn('process_payments', 'online_percent')) {
                $schema->table('process_payments', function ($table) {
                    $table->decimal('online_percent', 5, 2)->default(0)->after('total_paid');
                });
            }
            if (!$schema->hasColumn('process_payments', 'online_payment')) {
                $schema->table('process_payments', function ($table) {
                    $table->decimal('online_payment', 15, 2)->default(0)->after('online_percent');
                });
            }
            if (!$schema->hasColumn('process_payments', 'retotal_amount')) {
                $schema->table('process_payments', function ($table) {
                    $table->decimal('retotal_amount', 15, 2)->default(0)->after('online_payment');
                });
            }
        }
    }

    private function sourceSalesNoteAmount(string $sourceType, int $sourceId): float
    {
        if ($sourceType !== 'sales_order') {
            return 0;
        }

        $value = DB::connection('sales')->table('sales_orders as so')
            ->leftJoin('sales_notes as sn', 'sn.id', '=', 'so.sales_note_id')
            ->where('so.id', $sourceId)
            ->value('sn.net_total');

        return (float) ($value ?? 0);
    }

    private function latestPaymentInvoiceForItem(object $item): ?object
    {
        $query = DB::connection('accounting')->table('process_payment_invoices as ppi')
            ->join('process_payments as pp', 'pp.id', '=', 'ppi.process_payment_id')
            ->where('ppi.source_type', (string) $item->source_type)
            ->where('ppi.source_id', (int) $item->invoice_id);

        if ((string) $item->source_type === 'online_report') {
            $query->where('ppi.invoice_no', (string) $item->invoice_no);
        }

        return $query->select('ppi.*', 'pp.payment_no', 'pp.payment_date')
            ->orderByDesc('ppi.id')
            ->first();
    }

    private function buildReturnDetailIndex(): array
    {
        $rows = DB::connection('sales')->table('sales_returns as sr')
            ->leftJoin('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
            ->whereNotNull('sr.invoice_no')
            ->whereRaw("UPPER(TRIM(COALESCE(sr.status, ''))) NOT IN ('CANCELLED', 'VOID')")
            ->select(
                'sr.id',
                'sr.return_number',
                'sr.invoice_no',
                'sr.created_at',
                DB::raw('SUM(COALESCE(sri.return_amount, sri.subtotal, 0)) as total_amount')
            )
            ->groupBy('sr.id', 'sr.return_number', 'sr.invoice_no', 'sr.created_at')
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $record = [
                'id' => (int) $row->id,
                'return_number' => (string) $row->return_number,
                'invoice_no' => (string) $row->invoice_no,
                'return_date' => $row->created_at ? substr((string) $row->created_at, 0, 10) : '',
                'total_amount' => (float) $row->total_amount,
            ];
            foreach ($this->normalizedInvoiceCandidates((string) $row->invoice_no) as $key) {
                $index[$key][(int) $row->id] = $record;
            }
        }

        return $index;
    }

    private function matchingReturnRows(string $invoiceNo, array $index): array
    {
        $matches = [];
        foreach ($this->normalizedInvoiceCandidates($invoiceNo) as $key) {
            foreach (($index[$key] ?? []) as $id => $row) {
                $matches[(int) $id] = $row;
            }
        }
        return array_values($matches);
    }

    private function normalizedInvoiceCandidates(string $invoiceNo): array
    {
        $raw = trim($invoiceNo);
        if ($raw === '') {
            return [];
        }

        $normalize = static fn ($v) => strtoupper((string) preg_replace('/\\s+/', '', trim((string) $v)));
        $keys = [$normalize($raw)];
        foreach (preg_split('/[,\\s\\/]+/', $raw) as $token) {
            $token = $normalize($token);
            if ($token !== '') {
                $keys[] = $token;
            }
        }
        if (preg_match_all('/SN-\\d+/i', $raw, $m) && !empty($m[0])) {
            foreach ($m[0] as $token) {
                $keys[] = $normalize($token);
            }
        }

        return array_values(array_unique(array_filter($keys, fn ($key) => $key !== '')));
    }

    private function ensureGroupRemarksColumn(): void
    {
        if ($this->groupRemarksColumnReady) {
            return;
        }

        $schema = Schema::connection('accounting');
        if (!$schema->hasTable('payment_invoice_group_items')) {
            return;
        }

        if (!$schema->hasColumn('payment_invoice_group_items', 'remarks')) {
            try {
                $schema->table('payment_invoice_group_items', function ($table) {
                    $table->text('remarks')->nullable()->after('invoice_no');
                });
            } catch (Throwable $e) {
                // Another request/deployment may have added it at the same time.
                if (!$schema->hasColumn('payment_invoice_group_items', 'remarks')) {
                    throw $e;
                }
            }
        }

        $this->groupRemarksColumnReady = true;
    }

    private function ensureGroupDiscountColumns(): void
    {
        if ($this->groupDiscountColumnsReady) {
            return;
        }

        $schema = Schema::connection('accounting');
        if (!$schema->hasTable('payment_invoice_groups')) {
            return;
        }

        try {
            if (!$schema->hasColumn('payment_invoice_groups', 'discount_percent')) {
                $schema->table('payment_invoice_groups', function ($table) {
                    $table->decimal('discount_percent', 5, 2)->default(0)->after('status');
                });
            }
            if (!$schema->hasColumn('payment_invoice_groups', 'discount_amount')) {
                $schema->table('payment_invoice_groups', function ($table) {
                    $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
                });
            }
        } catch (Throwable $e) {
            if (!$schema->hasColumn('payment_invoice_groups', 'discount_percent') || !$schema->hasColumn('payment_invoice_groups', 'discount_amount')) {
                throw $e;
            }
        }

        $this->groupDiscountColumnsReady = true;
    }

    private function cleanRemarks(mixed $remarks): ?string
    {
        $value = trim((string) ($remarks ?? ''));
        return $value === '' ? null : substr($value, 0, 5000);
    }

    private function sourceDocumentAmount(string $sourceType, int $sourceId, string $invoiceNo = ''): float
    {
        if ($sourceType === 'sales_order') {
            return (float) DB::connection('sales')->table('sales_orders')->where('id', $sourceId)->value('total_amount') ?? 0;
        }
        if ($sourceType === 'consignment_invoice') {
            return (float) DB::connection('sales')->table('consignment_invoices')->where('id', $sourceId)->value('total_amount') ?? 0;
        }
        if ($sourceType === 'online_report') {
            return $this->onlineInvoiceAmountService->resolve($sourceId, $invoiceNo);
        }

        return 0;
    }

    private function sourceExists(string $sourceType, int $sourceId): bool
    {
        if ($sourceType === 'sales_order') {
            return DB::connection('sales')->table('sales_orders')->where('id', $sourceId)->exists();
        }
        if ($sourceType === 'consignment_invoice') {
            return DB::connection('sales')->table('consignment_invoices')->where('id', $sourceId)->exists();
        }
        if ($sourceType === 'online_report') {
            return DB::connection('sales')->table('online_reports')->where('id', $sourceId)->exists();
        }

        return false;
    }

    private function resolveCustomer(string $sourceType, int $sourceId, string $invoiceNo = ''): ?array
    {
        // W68_PAYMENTS_CUSTOMER_SOURCE_FALLBACK_20260909
        // Payments must not fail just because an authoritative Sales/Online
        // customer no longer has a matching Masterlist row. Resolve the source
        // identity first, then prefer Masterlist only when it exists.
        $customerId = 0;
        $customerName = '';

        if ($sourceType === 'sales_order') {
            $source = DB::connection('sales')->table('sales_orders')
                ->where('id', $sourceId)
                ->first(['customer_id', 'customer_name']);
            $customerId = (int) ($source->customer_id ?? 0);
            $customerName = trim((string) ($source->customer_name ?? ''));
        } elseif ($sourceType === 'consignment_invoice') {
            $source = DB::connection('sales')->table('consignment_invoices')
                ->where('id', $sourceId)
                ->first(['customer_id', 'customer_name']);
            $customerId = (int) ($source->customer_id ?? 0);
            $customerName = trim((string) ($source->customer_name ?? ''));
        } elseif ($sourceType === 'online_report') {
            $normalize = static fn ($value) => strtoupper((string) preg_replace('/\s+/', '', trim((string) $value)));
            $targetInvoice = $normalize($invoiceNo);

            // 1) Finalized ONL Sales Order is authoritative when available.
            $orders = DB::connection('sales')->table('sales_orders')
                ->where('order_number', 'LIKE', 'ONL-' . $sourceId . '-%')
                ->whereIn('status', ['Confirmed', 'Closed'])
                ->orderByDesc('id')
                ->get(['customer_id', 'customer_name', 'invoice_numbers']);

            $matchedOrder = $orders->first(function ($order) use ($normalize, $targetInvoice) {
                if ($targetInvoice === '') return true;
                $raw = trim((string) ($order->invoice_numbers ?? ''));
                $decoded = json_decode($raw, true);
                $values = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                foreach ((array) $values as $value) {
                    if ($normalize($value) === $targetInvoice) return true;
                }
                return false;
            });

            if ($matchedOrder) {
                $customerId = (int) ($matchedOrder->customer_id ?? 0);
                $customerName = trim((string) ($matchedOrder->customer_name ?? ''));
            }

            // 2) Online Report snapshot fallback. It can also fill a missing
            // name even when the finalized order already supplied the ID.
            if ($customerId <= 0 || $customerName === '') {
                $note = $this->onlineNoteByInvoice($sourceId, $invoiceNo);
                if ($note) {
                    if ($customerId <= 0) {
                        $customerId = (int) ($note['customer_id'] ?? 0);
                    }
                    if ($customerName === '') {
                        $customerName = trim((string) ($note['customer_name'] ?? ($note['customer'] ?? '')));
                    }
                }
            }

            // 3) Product Ledger is the last read-only fallback for damaged
            // historical Online Report snapshots. It is read-only and does not
            // alter any Ledger or Masterlist record.
            if ($customerId <= 0 || $customerName === '') {
                $ledgerRows = DB::connection('ledger')->table('product_ledgers')
                    ->where('source_type', 'online_report')
                    ->where('source_id', $sourceId)
                    ->whereNotNull('customer_id')
                    ->orderByDesc('id')
                    ->get(['customer_id', 'entity_name', 'reference_number']);

                $ledgerMatch = $ledgerRows->first(function ($row) use ($normalize, $targetInvoice) {
                    if ($targetInvoice === '') return true;
                    return $normalize($row->reference_number ?? '') === $targetInvoice;
                });

                if (!$ledgerMatch && $ledgerRows->pluck('customer_id')->filter()->unique()->count() === 1) {
                    $ledgerMatch = $ledgerRows->first();
                }

                if ($ledgerMatch) {
                    if ($customerId <= 0) {
                        $customerId = (int) ($ledgerMatch->customer_id ?? 0);
                    }
                    if ($customerName === '') {
                        $customerName = trim((string) ($ledgerMatch->entity_name ?? ''));
                    }
                }
            }
        }

        // If only a source name survived, resolve an exact Masterlist name.
        if ($customerId <= 0 && $customerName !== '') {
            $byName = DB::connection('masterlist')->table('customers')
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($customerName))])
                ->first(['id', 'name']);
            if ($byName) {
                return ['id' => (int) $byName->id, 'name' => (string) $byName->name];
            }
        }

        if ($customerId <= 0) {
            return null;
        }

        // Historical Shopee customer IDs 1029/1433 are one Payments read group.
        $masterIds = in_array($customerId, [1029, 1433], true)
            ? array_values(array_unique([$customerId, $customerId === 1029 ? 1433 : 1029]))
            : [$customerId];

        $masterCustomers = DB::connection('masterlist')->table('customers')
            ->whereIn('id', $masterIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $customer = $masterCustomers->get($customerId)
            ?? collect($masterIds)->map(fn ($id) => $masterCustomers->get($id))->filter()->first();

        if ($customer) {
            return ['id' => (int) $customer->id, 'name' => (string) $customer->name];
        }

        // If the exact source row had no name, recover the latest known
        // Sales-side name for that authoritative customer ID before failing.
        if ($customerName === '') {
            $customerName = trim((string) (
                DB::connection('sales')->table('sales_orders')
                    ->where('customer_id', $customerId)
                    ->whereNotNull('customer_name')
                    ->where('customer_name', '<>', '')
                    ->orderByDesc('id')
                    ->value('customer_name') ?? ''
            ));
        }
        if ($customerName === '') {
            $customerName = trim((string) (
                DB::connection('sales')->table('consignment_invoices')
                    ->where('customer_id', $customerId)
                    ->whereNotNull('customer_name')
                    ->where('customer_name', '<>', '')
                    ->orderByDesc('id')
                    ->value('customer_name') ?? ''
            ));
        }

        // Online-only/legacy buyer: keep the authoritative source ID and name.
        // No Masterlist row is created or altered.
        if ($customerName !== '') {
            return ['id' => $customerId, 'name' => $customerName];
        }

        return null;
    }

    private function buildSettlementMap(): array
    {
        $rows = DB::connection('accounting')->table('process_payment_invoices')
            ->select(
                'source_type',
                'source_id',
                'invoice_no',
                DB::raw('SUM(paid_amount) as paid_total'),
                DB::raw('SUM(adjustment) as adjustment_total')
            )
            ->groupBy('source_type', 'source_id', 'invoice_no')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $key = $row->source_type . ':' . $row->source_id . ($row->source_type === 'online_report' ? ':' . $row->invoice_no : '');
            $map[$key] = [
                'paid' => (float) $row->paid_total,
                'adjustment' => (float) $row->adjustment_total,
            ];
        }

        return $map;
    }

    private function invoiceKey(array $inv): string
    {
        $type = (string) ($inv['source_type'] ?? '');
        $id = (int) ($inv['source_id'] ?? ($inv['invoice_id'] ?? 0));
        $no = trim((string) ($inv['invoice_no'] ?? ''));

        return $type . ':' . $id . ($type === 'online_report' ? ':' . $no : '');
    }

    private function onlineNoteByInvoice(int $reportId, string $invoiceNo): ?array
    {
        if (trim($invoiceNo) === '') {
            return null;
        }

        $report = DB::connection('sales')->table('online_reports')
            ->where('id', $reportId)
            ->first(['invoice_numbers', 'notes_data']);
        if (!$report) {
            return null;
        }

        $invNumbers = json_decode((string) ($report->invoice_numbers ?? '[]'), true);
        $notesData = json_decode((string) ($report->notes_data ?? '[]'), true);
        $invNumbers = is_array($invNumbers) ? $invNumbers : [];
        $notesData = is_array($notesData) ? $notesData : [];
        $normalize = static fn ($value) => strtoupper((string) preg_replace('/\s+/', '', trim((string) $value)));
        $target = $normalize($invoiceNo);

        foreach ($invNumbers as $i => $no) {
            if ($normalize($no) === $target && isset($notesData[$i]) && is_array($notesData[$i])) {
                return $notesData[$i];
            }
        }

        foreach ($notesData as $note) {
            if (!is_array($note)) {
                continue;
            }
            if ($normalize($note['sales_number'] ?? '') === $target) {
                return $note;
            }
            $remarks = (string) ($note['remarks'] ?? '');
            if (preg_match('/Invoice:\s*([^|,\n]+)/i', $remarks, $m) && $normalize($m[1]) === $target) {
                return $note;
            }
        }

        return null;
    }

    private function buildReturnMap(): array
    {
        $rows = DB::connection('sales')->table('sales_returns as sr')
            ->join('sales_return_items as sri', 'sri.sales_return_id', '=', 'sr.id')
            ->select('sr.invoice_no', DB::raw('SUM(sri.return_amount) as return_total'))
            ->whereNotNull('sr.invoice_no')
            ->whereNotIn('sr.status', ['CANCELLED', 'VOID'])
            ->groupBy('sr.invoice_no')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $normalized = strtoupper((string) preg_replace('/\s+/', '', trim((string) $row->invoice_no)));
            if ($normalized !== '') {
                $map[$normalized] = (float) $row->return_total;
            }
        }

        return $map;
    }

    private function lookupReturnedAmount(string $invoiceNo, array $returnMap): float
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', trim($invoiceNo)));
        $candidates = array_merge([$normalized], preg_split('/[,\s\/]+/', $normalized));

        $total = 0;
        foreach ($candidates as $key) {
            if ($key !== '' && isset($returnMap[$key])) {
                $total += (float) $returnMap[$key];
            }
        }

        return $total;
    }

    private function nextPaymentNo(): string
    {
        $max = (int) DB::connection('accounting')->table('process_payments')
            ->max(DB::raw('CAST(SUBSTRING(payment_no, 5) AS UNSIGNED)'));

        return 'PAY-' . str_pad((string) ($max + 1), 7, '0', STR_PAD_LEFT);
    }

    private function parseNoteIds(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $ids = json_decode($raw, true);
        if (is_array($ids)) {
            return array_values(array_filter(array_map('intval', $ids)));
        }

        return array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw))));
    }

    private function actorName($user): string
    {
        if (is_array($user)) {
            return trim((string) ($user['name'] ?? ($user['username'] ?? ''))) ?: 'system';
        }

        return trim((string) ($user->name ?? ($user->username ?? ''))) ?: 'system';
    }
}
