<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TuitionInvoiceImport implements ToCollection, WithHeadingRow, WithValidation
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
            $student = Student::where('nis', $row['student_nis'])->first();

            if (! $student) {
                continue; // Skip if student not found (validated upstream)
            }

            TuitionInvoice::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'period' => $row['period'],
                    'fee_type' => $row['fee_type'],
                ],
                [
                    'description' => $row['description'] ?? null,
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date'],
                    'status' => $row['status'] ?? 'draft',
                    'generation_source' => $row['generation_source'] ?? 'manual',
                    'created_by' => auth()->id(),
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'student_nis' => ['required', 'string', 'exists:students,nis'],
            'period' => ['required', 'string', 'max:7', 'regex:/^\d{4}-\d{2}$/'],
            'fee_type' => ['required', 'in:enrollment,spp,other'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', 'in:draft,pending_payment'],
            'generation_source' => ['nullable', 'in:manual,scheduled'],
        ];
    }
}
