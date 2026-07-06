<?php

namespace App\Exports;

use App\Models\TuitionInvoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TuitionInvoiceExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        public readonly ?string $period = null,
        public readonly ?string $feeType = null,
        public readonly ?string $status = null,
        public readonly ?int $studentId = null,
    ) {}

    public function collection(): Collection
    {
        $query = TuitionInvoice::with('student');

        if ($this->period) {
            $query->where('period', $this->period);
        }

        if ($this->feeType) {
            $query->where('fee_type', $this->feeType);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->studentId) {
            $query->where('student_id', $this->studentId);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['ID', 'NIS Siswa', 'Nama Siswa', 'Periode', 'Jenis Biaya', 'Deskripsi', 'Jumlah', 'Jatuh Tempo', 'Status', 'Dibayar Pada', 'Sumber'];
    }

    public function map($invoice): array
    {
        return [
            $invoice->id,
            $invoice->student->nis ?? '-',
            $invoice->student->name ?? '-',
            $invoice->period,
            ucfirst($invoice->fee_type),
            $invoice->description ?? '-',
            $invoice->amount,
            $invoice->due_date->format('d/m/Y'),
            ucfirst($invoice->status),
            $invoice->paid_at?->format('d/m/Y H:i') ?? '-',
            ucfirst($invoice->generation_source),
        ];
    }
}
