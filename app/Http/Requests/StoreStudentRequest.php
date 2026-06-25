<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'school_class' => ['required', 'string', 'max:255'],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'parent_email' => ['required', 'email', 'max:255'],
            'monthly_fee' => ['required', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive,graduated'],
        ];
    }
}
