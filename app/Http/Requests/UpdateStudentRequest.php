<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nis' => ['sometimes', 'string', 'max:20'],
            'name' => ['sometimes', 'string', 'max:255'],
            'school_class' => ['sometimes', 'string', 'max:255'],
            'parent_name' => ['sometimes', 'string', 'max:255'],
            'parent_phone' => ['sometimes', 'string', 'max:20'],
            'parent_email' => ['sometimes', 'email', 'max:255'],
            'monthly_fee' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive,graduated'],
        ];
    }
}
