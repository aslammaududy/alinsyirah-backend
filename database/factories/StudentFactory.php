<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('##########'),
            'name' => fake()->name(),
            'school_class' => fake()->randomElement(['X-A', 'X-B', 'XI-A', 'XI-B', 'XII-A', 'XII-B']),
            'parent_name' => fake()->name('male'),
            'parent_phone' => fake()->phoneNumber(),
            'parent_email' => fake()->safeEmail(),
            'monthly_fee' => fake()->numberBetween(100000, 500000),
            'status' => 'active',
        ];
    }

    public function withPhoto(): static
    {
        return $this->state(fn () => [
            'photo_url' => 'students/'.fake()->numberBetween(1, 1000).'/'.fake()->uuid().'.jpg',
        ]);
    }
}
