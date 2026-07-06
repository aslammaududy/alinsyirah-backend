<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\StudentImport;
use App\Imports\StudentTemplate;
use App\Imports\TuitionInvoiceImport;
use App\Imports\TuitionInvoiceTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'type' => ['required', 'in:students,tuition-invoices'],
        ]);

        $file = $request->file('file');
        $type = $request->input('type');
        $token = Str::uuid()->toString();

        // Parse the file in preview mode (no DB writes)
        $import = match ($type) {
            'students' => new StudentImport(preview: true),
            'tuition-invoices' => new TuitionInvoiceImport(preview: true),
        };

        try {
            $collection = Excel::toCollection([], $file, null, \Maatwebsite\Excel\Excel::XLSX)->first();

            // Store preview data in cache for 30 minutes
            Cache::put("import_{$token}", [
                'file_path' => $file->storeAs('imports', $token.'.xlsx'),
                'type' => $type,
                'rows' => $collection->toArray(),
            ], now()->addMinutes(30));

            return response()->json([
                'token' => $token,
                'rows' => $collection->toArray(),
                'summary' => [
                    'total' => $collection->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to parse import file.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $token = $request->input('token');
        $cached = Cache::get("import_{$token}");

        if (! $cached) {
            return response()->json([
                'message' => 'Import session expired or invalid token.',
            ], 422);
        }

        $type = $cached['type'];
        $filePath = storage_path('app/'.$cached['file_path']);

        if (! file_exists($filePath)) {
            return response()->json([
                'message' => 'Import file no longer available.',
            ], 422);
        }

        try {
            $import = match ($type) {
                'students' => new StudentImport(preview: false),
                'tuition-invoices' => new TuitionInvoiceImport(preview: false),
            };

            Excel::import($import, $filePath);

            // Cleanup
            Cache::forget("import_{$token}");
            @unlink($filePath);

            return response()->json([
                'message' => 'Import completed successfully.',
                'created' => count($cached['rows']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Import failed.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function studentTemplate()
    {
        return Excel::download(new StudentTemplate, 'template-students.xlsx');
    }

    public function invoiceTemplate()
    {
        return Excel::download(new TuitionInvoiceTemplate, 'template-tuition-invoices.xlsx');
    }
}
