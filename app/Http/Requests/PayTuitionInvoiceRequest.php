<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayTuitionInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:qris,bank_transfer'],
            'bank' => ['required_if:payment_method,bank_transfer', 'string', 'in:bsi'],
        ];
    }
}
