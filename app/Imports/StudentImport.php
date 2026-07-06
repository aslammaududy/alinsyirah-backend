<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentImport implements ToCollection, WithHeadingRow, WithValidation
{
    public function __construct(
        public readonly bool $preview = true,
    ) {}

    public function collection(Collection $rows): void
    {
        if ($this->preview) {
            return; // Preview mode: don't write to DB
        }

        foreach ($rows as $row) {
            Student::updateOrCreate(
                ['nis' => $row['nis']],
                [
                    'name' => $row['name'],
                    'school_class' => $row['school_class'],
                    'parent_name' => $row['parent_name'],
                    'parent_phone' => $row['parent_phone'],
                    'parent_email' => $row['parent_email'],
                    'monthly_fee' => $row['monthly_fee'],
                    'status' => $row['status'] ?? 'active',
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'school_class' => ['required', 'string', 'max:50'],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:20'],
            'parent_email' => ['required', 'email', 'max:255'],
            'monthly_fee' => ['required', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,graduated'],
        ];
    }
}
