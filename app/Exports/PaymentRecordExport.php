<?php

namespace App\Exports;

use App\Models\PaymentAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentRecordExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?string $status = null,
        public readonly ?int $studentId = null,
    ) {}

    public function collection(): Collection
    {
        $query = PaymentAttempt::with(['invoices.student', 'invoices']);

        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->studentId) {
            $query->whereHas('invoices', function ($q) {
                $q->where('student_id', $this->studentId);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return ['ID', 'Order ID', 'Status', 'Total', 'Tanggal Dibuat', 'Dibayar Pada', 'Invoices yang Dibayar'];
    }

    public function map($attempt): array
    {
        $invoiceIds = $attempt->invoices->pluck('id')->implode(', ');
        $studentNames = $attempt->invoices->pluck('student.name')->unique()->implode(', ');

        return [
            $attempt->id,
            $attempt->midtrans_order_id ?? '-',
            ucfirst($attempt->status),
            $attempt->invoices->sum('pivot.allocated_amount'),
            $attempt->created_at->format('d/m/Y H:i'),
            $attempt->paid_at?->format('d/m/Y H:i') ?? '-',
            $invoiceIds.($studentNames ? " ({$studentNames})" : ''),
        ];
    }
}
