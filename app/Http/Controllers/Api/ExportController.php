<?php

namespace App\Http\Controllers\Api;

use App\Exports\PaymentRecordExport;
use App\Exports\StudentExport;
use App\Exports\TuitionInvoiceExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function export(Request $request, string $type)
    {
        $export = match ($type) {
            'students' => new StudentExport(
                schoolClass: $request->query('school_class'),
                status: $request->query('status'),
            ),
            'tuition-invoices' => new TuitionInvoiceExport(
                period: $request->query('period'),
                feeType: $request->query('fee_type'),
                status: $request->query('status'),
                studentId: $request->query('student_id') ? (int) $request->query('student_id') : null,
            ),
            'payments' => new PaymentRecordExport(
                dateFrom: $request->query('date_from'),
                dateTo: $request->query('date_to'),
                status: $request->query('status'),
                studentId: $request->query('student_id') ? (int) $request->query('student_id') : null,
            ),
            default => abort(422, 'Invalid export type. Allowed: students, tuition-invoices, payments'),
        };

        $filename = "{$type}-export-".now()->format('Y-m-d').'.xlsx';

        return Excel::download($export, $filename);
    }
}
