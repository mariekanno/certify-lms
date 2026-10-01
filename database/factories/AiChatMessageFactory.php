<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->sentence(),
            'error_detail' => null,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
        ];
    }

    public function user(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Pending,
            'content' => '',
        ]);
    }

    public function error(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => 'Gemini API error',
        ]);
    }
}
