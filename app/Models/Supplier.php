<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $connection = 'masterlist';

    protected $appends = [
        'lifecycle',
    ];

    protected $fillable = [
        'record_type',
        'supplier_code',
        'name',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'tin',
        'telefax',
        'payment_terms',
        'status',
        'customer_type',
        'pricing_remarks',
        'bank_code',
        'bank_account_number',
        'bank_name',
        'bank_contact_number',
        'bank_contact_person',
        'bank_address',
        'start_date',
        'end_date',
        'Supplier_Image',
    ];

    /**
     * Get the purchase orders for the supplier.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    /**
     * Get the ledger entries for the supplier.
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedger::class, 'supplier_id');
    }

    public static function calculateLifecycle($startDate, $endDate): string
    {
        if (!$startDate || !$endDate) {
            return 'N/A';
        }

        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
        } catch (\Throwable $e) {
            return 'N/A';
        }

        if ($end->lessThan($start)) {
            return 'Invalid date range';
        }

        $diffDays = (int) $start->diffInDays($end);
        $years = intdiv($diffDays, 365);
        $months = intdiv($diffDays % 365, 30);
        $days = $diffDays % 30;

        $parts = [];
        if ($years > 0) {
            $parts[] = "{$years}y";
        }
        if ($months > 0) {
            $parts[] = "{$months}m";
        }
        $parts[] = "{$days}d";

        return implode(' ', $parts);
    }

    public function getLifecycleAttribute(): string
    {
        return self::calculateLifecycle($this->start_date, $this->end_date);
    }
}
