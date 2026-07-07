<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_order_id' => $this->provider_order_id,
            'payment_url' => $this->payment_url,
            'usage_limit' => $this->usage_limit,
            'expiry_at' => $this->expiry_at,
            'status' => $this->status,
            'discount_amount' => $this->discount_amount,
            'gross_amount' => $this->getGrossAmount(),
            'payment_method' => $this->payment_method,
            'fee_amount' => $this->fee_amount,
            'fee_percentage' => $this->fee_percentage,
            'total_amount' => $this->getTotalAmount(),
            'qr_code_url' => $this->provider_response['qr_code_url'] ?? null,
            'va_number' => $this->provider_response['va_number'] ?? null,
            'bank' => $this->provider_response['bank'] ?? null,
            'invoices' => TuitionInvoiceResource::collection($this->whenLoaded('invoices')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
