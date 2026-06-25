<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-monthly-invoices')]
#[Description('Generate monthly SPP invoices for active students for the current period')]
class GenerateMonthlyInvoices extends Command
{
    public function handle(): void
    {
        $now = now();
        $period = $now->format('Y-m');
        $year = $now->format('Y');
        $month = $now->format('m');
        $dueDay = min((int) $now->format('d'), 28);

        $students = Student::where('status', 'active')->get();

        foreach ($students as $student) {
            TuitionInvoice::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'period' => $period,
                    'fee_type' => 'spp',
                ],
                [
                    'description' => "SPP {$student->name} - {$period}",
                    'amount' => $student->monthly_fee,
                    'due_date' => sprintf('%s-%s-%02d', $year, $month, $dueDay),
                    'status' => 'draft',
                    'generation_source' => 'scheduled',
                ]
            );
        }

        $count = $students->count();

        $this->info("Generated/verified {$count} SPP invoices for period {$period}.");
    }
}
