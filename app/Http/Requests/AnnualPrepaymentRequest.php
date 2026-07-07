<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnualPrepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:students,id'],
            'year' => ['required', 'string', 'regex:/^\d{4}$/'],
            'discount_amount' => ['sometimes', 'integer', 'min:0'],
            'usage_limit' => ['sometimes', 'integer', 'min:1'],
            'expiry' => ['sometimes', 'array'],
            'expiry.duration' => ['required_with:expiry', 'integer', 'min:1'],
            'expiry.unit' => ['required_with:expiry', 'in:days,hours,minutes'],
            'enabled_payments' => ['sometimes', 'array'],
            'enabled_payments.*' => ['string'],
            'callbacks' => ['sometimes', 'array'],
            'payment_method' => ['required', 'in:qris,bank_transfer'],
            'bank' => ['required_if:payment_method,bank_transfer', 'string', 'in:bsi'],
        ];
    }
}
