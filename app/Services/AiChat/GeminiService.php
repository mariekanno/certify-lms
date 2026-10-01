<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GeminiService
{
    /**
     * @param array<int, array{role: string, parts: array<int, array{text: string}>}> $contents
     *
     * @return array{
     *     content: string,
     *     input_tokens: int|null,
     *     output_tokens: int|null,
     *     model: string,
     *     response_time_ms: int,
     * }
     *
     * @throws RequestException
     * @throws RuntimeException
     */
    public function generate(
        array $contents,
        ?string $systemInstruction = null,
    ): array {
        $apiKey = (string) config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model', 'gemini-3.1-flash-lite');
        $baseUrl = rtrim(
            (string) config(
                'ai-chat.gemini.base_url',
                'https://generativelanguage.googleapis.com/v1beta',
            ),
            '/',
        );

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $payload = [
            'contents' => $contents,
        ];

        if ($systemInstruction !== null && $systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    [
                        'text' => $systemInstruction,
                    ],
                ],
            ];
        }

        $startedAt = microtime(true);

        $response = Http::acceptJson()
            ->timeout(60)
            ->post(
                "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}",
                $payload,
            );

        $responseTimeMs = (int) round(
            (microtime(true) - $startedAt) * 1000,
        );

        $response->throw();

        $content = collect(
            $response->json('candidates.0.content.parts', []),
        )
            ->pluck('text')
            ->filter()
            ->implode("\n");

        if ($content === '') {
            throw new RuntimeException(
                'Gemini API returned no response content.',
            );
        }

        return [
            'content' => $content,
            'input_tokens' => $response->json(
                'usageMetadata.promptTokenCount',
            ),
            'output_tokens' => $response->json(
                'usageMetadata.candidatesTokenCount',
            ),
            'model' => $model,
            'response_time_ms' => $responseTimeMs,
        ];
    }
}
