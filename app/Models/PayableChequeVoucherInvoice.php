<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayableChequeVoucherInvoice extends Model
{
    protected $connection = 'accounting';
    protected $table = 'payable_cheque_voucher_invoices';

    protected $fillable = [
        'payable_cheque_voucher_id',
        'purchase_order_id',
        'purchase_no',
        'invoice_no',
        'invoice_amount',
        'amount_due',
        'amount_paid',
        'remarks',
        'payment_status',
    ];

    protected $casts = [
        'invoice_amount' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(PayableChequeVoucher::class, 'payable_cheque_voucher_id');
    }
}
