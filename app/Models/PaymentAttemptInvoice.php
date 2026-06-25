<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PaymentAttemptInvoice extends Pivot
{
    protected $table = 'payment_attempt_invoices';

    protected $fillable = [
        'payment_attempt_id',
        'tuition_invoice_id',
        'allocated_amount',
    ];

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function tuitionInvoice(): BelongsTo
    {
        return $this->belongsTo(TuitionInvoice::class);
    }
}
