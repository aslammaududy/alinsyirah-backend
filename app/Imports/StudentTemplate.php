<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentTemplate implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return collect([
            [
                'nis' => '1234567890',
                'name' => 'Ahmad Fauzi',
                'school_class' => '1-A',
                'parent_name' => 'Budi Fauzi',
                'parent_phone' => '081234567890',
                'parent_email' => 'budi@example.com',
                'monthly_fee' => 500000,
                'status' => 'active',
            ],
        ]);
    }

    public function headings(): array
    {
        return ['nis', 'name', 'school_class', 'parent_name', 'parent_phone', 'parent_email', 'monthly_fee', 'status'];
    }
}
