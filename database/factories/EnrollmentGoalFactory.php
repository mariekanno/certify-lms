<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => now()->subDays(fake()->numberBetween(0, 10)),
        ]);
    }

    public function unachieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => null,
        ]);
    }
}
