<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditTrailPurge
{
    /**
     * Resolve the monthly 10:00 PM Asia/Manila purge timestamp for the month
     * containing the given reference time.
     */
    public static function targetFor(?Carbon $reference = null): Carbon
    {
        $reference = $reference
            ? $reference->copy()->timezone('Asia/Manila')
            : Carbon::now('Asia/Manila');

        return $reference->copy()->endOfMonth()->setTime(22, 0, 0);
    }

    /**
     * Purge Audit Trail rows up to the latest passed month-end 10:00 PM cutoff.
     *
     * If the current month's cutoff has not arrived yet, any unpurged logs from
     * prior months (on or before the previous month-end 10:00 PM cutoff) are
     * automatically caught up and deleted without touching current-cycle logs.
     */
    public static function purgeIfDue(?Carbon $now = null): array
    {
        $nowManila = $now
            ? $now->copy()->timezone('Asia/Manila')
            : Carbon::now('Asia/Manila');

        $currentTarget = self::targetFor($nowManila);
        $previousTarget = self::targetFor($nowManila->copy()->startOfMonth()->subMonthNoOverflow());
        $nextTarget = self::targetFor($nowManila->copy()->startOfMonth()->addMonthNoOverflow());

        $isCurrentDue = $nowManila->greaterThanOrEqualTo($currentTarget);
        $dueCutoff = $isCurrentDue ? $currentTarget : $previousTarget;
        $displayNextDue = $isCurrentDue ? $nextTarget : $currentTarget;
        $cacheKey = 'audit_trail:auto_delete:' . $dueCutoff->format('Y-m-d-His') . '-asia-manila';

        if (!Schema::connection('ledger')->hasTable('audit_trails')) {
            return [
                'due_at' => $displayNextDue->toIso8601String(),
                'server_now' => $nowManila->toIso8601String(),
                'purged' => false,
                'already_purged' => false,
                'deleted_count' => 0,
            ];
        }

        try {
            if (Cache::has($cacheKey)) {
                $cachedValue = Cache::get($cacheKey);
                return [
                    'due_at' => $displayNextDue->toIso8601String(),
                    'server_now' => $nowManila->toIso8601String(),
                    'purged' => false,
                    'already_purged' => $isCurrentDue,
                    'deleted_count' => (int) ($cachedValue['deleted_count'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            // Continue even if cache is unavailable.
        }

        $cutoffString = $dueCutoff->toDateTimeString();

        $deletedCount = (int) DB::connection('ledger')
            ->table('audit_trails')
            ->where(function ($query) use ($cutoffString) {
                $query->where('created_at', '<=', $cutoffString)
                    ->orWhereNull('created_at');
            })
            ->delete();

        try {
            Cache::put($cacheKey, [
                'deleted_count' => $deletedCount,
                'cutoff_at' => $cutoffString,
                'purged_at' => $nowManila->toDateTimeString(),
            ], $nowManila->copy()->addDays(40));
        } catch (\Throwable $e) {
            // The delete already happened, so cache failures can be ignored here.
        }

        if ($deletedCount > 0 || $isCurrentDue) {
            Log::info('Audit trail auto-delete executed.', [
                'cutoff_at' => $cutoffString,
                'catch_up' => !$isCurrentDue,
                'deleted_count' => $deletedCount,
            ]);
        }

        return [
            'due_at' => $displayNextDue->toIso8601String(),
            'server_now' => $nowManila->toIso8601String(),
            'purged' => $isCurrentDue ? true : ($deletedCount > 0),
            'already_purged' => false,
            'deleted_count' => $deletedCount,
        ];
    }
}
