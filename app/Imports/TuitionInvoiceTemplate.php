<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TuitionInvoiceTemplate implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return collect([
            [
                'student_nis' => '1234567890',
                'period' => '2026-07',
                'fee_type' => 'spp',
                'description' => 'SPP Bulanan Juli 2026',
                'amount' => 500000,
                'due_date' => '2026-07-10',
                'status' => 'draft',
                'generation_source' => 'manual',
            ],
        ]);
    }

    public function headings(): array
    {
        return ['student_nis', 'period', 'fee_type', 'description', 'amount', 'due_date', 'status', 'generation_source'];
    }
}
