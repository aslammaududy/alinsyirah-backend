<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnualPrepaymentRequest;
use App\Http\Resources\PaymentAttemptResource;
use App\Models\Student;
use App\Models\TuitionInvoice;
use App\Services\BundlePaymentService;
use RuntimeException;

class AnnualPrepaymentController extends Controller
{
    public function __construct(
        private readonly BundlePaymentService $bundleService,
    ) {}

    /**
     * Generate 12 monthly SPP invoices for a student for the given year and bundle them into a single Payment Link.
     */
    public function store(AnnualPrepaymentRequest $request): PaymentAttemptResource
    {
        $data = $request->validated();

        $student = Student::findOrFail($data['student_id']);
        $year = $data['year'];

        $invoices = [];

        for ($month = 1; $month <= 12; $month++) {
            $period = sprintf('%s-%02d', $year, $month);

            $invoice = TuitionInvoice::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'period' => $period,
                    'fee_type' => 'spp',
                ],
                [
                    'description' => "SPP {$student->name} - {$period}",
                    'amount' => $student->monthly_fee,
                    'due_date' => sprintf('%s-%02d-10', $year, $month),
                    'status' => 'draft',
                    'generation_source' => 'annual_prepayment',
                    'created_by' => $request->user()?->id,
                ]
            );

            if ($invoice->wasRecentlyCreated || $invoice->status === 'draft') {
                $invoices[] = $invoice;
            }
        }

        if (empty($invoices)) {
            abort(422, 'All 12 invoices already exist in a non-draft state for this student and year.');
        }

        $allocations = array_map(
            fn (TuitionInvoice $inv) => ['id' => $inv->id, 'allocated_amount' => $inv->amount],
            $invoices
        );

        try {
            $attempt = $this->bundleService->bundle(
                invoices: collect($invoices),
                allocations: $allocations,
                discountAmount: $data['discount_amount'] ?? 0,
                usageLimit: $data['usage_limit'] ?? null,
                expiry: $data['expiry'] ?? null,
                customerDetails: [
                    'first_name' => $student->parent_name,
                    'email' => $student->parent_email,
                    'phone' => $student->parent_phone,
                ],
                enabledPayments: $data['enabled_payments'] ?? null,
                callbacks: $data['callbacks'] ?? null,
                createdBy: $request->user()?->id,
                paymentMethod: $data['payment_method'],
                bank: $data['bank'] ?? null,
            );

            return PaymentAttemptResource::make($attempt);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
