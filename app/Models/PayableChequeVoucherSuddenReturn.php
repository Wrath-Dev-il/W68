<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayableChequeVoucherSuddenReturn extends Model
{
    protected $connection = 'accounting';
    protected $table = 'payable_cheque_voucher_sudden_returns';

    protected $fillable = [
        'payable_cheque_voucher_id',
        'payable_cheque_voucher_invoice_id',
        'purchase_order_id',
        'return_number',
        'return_date',
        'return_amount',
        'remarks',
    ];

    protected $casts = [
        'return_date' => 'date',
        'return_amount' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(PayableChequeVoucher::class, 'payable_cheque_voucher_id');
    }

    public function invoice()
    {
        return $this->belongsTo(PayableChequeVoucherInvoice::class, 'payable_cheque_voucher_invoice_id');
    }
}
