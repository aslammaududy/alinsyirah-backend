<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\TuitionInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TuitionInvoice>
 */
class TuitionInvoiceFactory extends Factory
{
    protected $model = TuitionInvoice::class;

    public function definition(): array
    {
        $student = Student::factory();

        return [
            'student_id' => $student,
            'period' => fake()->year().'-'.str_pad((string) fake()->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'fee_type' => fake()->randomElement(['enrollment', 'spp', 'other']),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(100000, 500000),
            'due_date' => fake()->date(),
            'status' => 'draft',
            'generation_source' => 'manual',
            'paid_at' => null,
        ];
    }

    public function spp(): static
    {
        return $this->state(fn (array $attrs) => [
            'fee_type' => 'spp',
        ]);
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'pending_payment',
        ]);
    }
}
