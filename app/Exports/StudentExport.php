<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        public readonly ?string $schoolClass = null,
        public readonly ?string $status = null,
    ) {}

    public function collection(): Collection
    {
        $query = Student::query();

        if ($this->schoolClass) {
            $query->where('school_class', $this->schoolClass);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['NIS', 'Nama', 'Kelas', 'Nama Orang Tua', 'No. HP', 'Email', 'Biaya/Bulan', 'Status'];
    }

    public function map($student): array
    {
        return [
            $student->nis,
            $student->name,
            $student->school_class,
            $student->parent_name,
            $student->parent_phone,
            $student->parent_email,
            $student->monthly_fee,
            ucfirst($student->status),
        ];
    }
}
