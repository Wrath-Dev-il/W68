<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PurchaseNote extends Model
{
    protected $connection = 'purchase';

    protected $fillable = [
        'purchase_note_number',
        'supplier_id',
        'date',
        'reference_number',
        'total_amount',
        'status',
        'is_force_closed',
        'force_closed_at',
        'force_closed_by',
        'force_close_reason',
        'force_close_reference',
        'remarks',
        'currency',
        'conversion_rate',
        'total_amount_converted',
    ];

    /**
     * Get the items for the purchase note.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseNoteItem::class);
    }

    /**
     * Get the supplier for the purchase note.
     * Note: This connects to the masterlist database.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * Next 7-digit P.O number based on highest existing number (not auto-increment id).
     */
    public static function nextNumber(): string
    {
        $max = static::query()
            ->where('purchase_note_number', '!=', 'PENDING')
            ->whereRaw("purchase_note_number REGEXP '^[0-9]+$'")
            ->max(DB::raw('CAST(purchase_note_number AS UNSIGNED)'));

        $next = ((int) $max) + 1;

        return str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }
}
