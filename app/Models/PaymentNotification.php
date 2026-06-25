<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotification extends Model
{
    protected $fillable = [
        'provider_order_id',
        'transaction_status',
        'signature_valid',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'raw_payload' => 'array',
        ];
    }
}
