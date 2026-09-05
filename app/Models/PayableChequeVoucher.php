<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayableChequeVoucher extends Model
{
    protected $connection = 'accounting';
    protected $table = 'payable_cheque_vouchers';

    protected $fillable = [
        'voucher_no',
        'supplier_id',
        'supplier_name',
        'voucher_date',
        'reference_no',
        'particulars',
        'payment_method',
        'total_paid',
        'status',
        'is_draft',
        'draft_state',
        'show_check_summary',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'total_paid' => 'decimal:2',
        'is_draft' => 'boolean',
        'show_check_summary' => 'boolean',
    ];

    public function invoices()
    {
        return $this->hasMany(PayableChequeVoucherInvoice::class);
    }

    public function payments()
    {
        return $this->hasMany(PayableChequeVoucherPayment::class);
    }

    public function suddenReturns()
    {
        return $this->hasMany(PayableChequeVoucherSuddenReturn::class);
    }

    public function getDraftStateAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }

    public function setDraftStateAttribute($value)
    {
        $this->attributes['draft_state'] = $value ? json_encode($value) : null;
    }
}
