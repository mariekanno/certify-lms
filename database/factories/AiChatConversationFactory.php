<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatConversation>
 */
class AiChatConversationFactory extends Factory
{
    protected $model = AiChatConversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'enrollment_id' => null,
            'section_id' => null,
            'title' => fake()->sentence(4),
            'auto_title_enabled' => true,
            'last_message_at' => now(),
        ];
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'user_id' => $enrollment->user_id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    public function autoTitleDisabled(): static
    {
        return $this->state(fn () => [
            'auto_title_enabled' => false,
        ]);
    }
}
