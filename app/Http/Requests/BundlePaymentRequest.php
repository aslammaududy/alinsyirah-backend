<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BundlePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tuition_invoice_ids' => ['required', 'array', 'min:1'],
            'tuition_invoice_ids.*' => ['required', 'integer', 'exists:tuition_invoices,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.id' => ['required', 'integer', 'exists:tuition_invoices,id'],
            'allocations.*.allocated_amount' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'usage_limit' => ['sometimes', 'integer', 'min:1'],
            'expiry' => ['sometimes', 'array'],
            'expiry.duration' => ['required_with:expiry', 'integer', 'min:1'],
            'expiry.unit' => ['required_with:expiry', 'in:days,hours,minutes'],
            'customer_details' => ['sometimes', 'array'],
            'enabled_payments' => ['sometimes', 'array'],
            'enabled_payments.*' => ['string'],
            'callbacks' => ['sometimes', 'array'],
            'payment_method' => ['required', 'in:qris,bank_transfer'],
            'bank' => ['required_if:payment_method,bank_transfer', 'string', 'in:bsi'],
        ];
    }
}
