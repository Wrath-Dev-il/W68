<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayableChequeVoucherPayment extends Model
{
    protected $connection = 'accounting';
    protected $table = 'payable_cheque_voucher_payments';

    protected $fillable = [
        'payable_cheque_voucher_id',
        'payment_method',
        'account_no',
        'bank_name',
        'check_no',
        'check_date',
        'payment_date',
        'reference_no',
        'credit_amount',
    ];

    protected $casts = [
        'check_date' => 'date',
        'payment_date' => 'date',
        'credit_amount' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(PayableChequeVoucher::class, 'payable_cheque_voucher_id');
    }
}
