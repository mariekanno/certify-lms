<?php

declare(strict_types=1);

namespace Tests\Feature\ExternalApi;

use App\Services\AiChat\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('external-api')]
class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.gemini.api_key' => 'test-key',
            'ai-chat.gemini.model' => 'gemini-test-model',
            'ai-chat.gemini.base_url' => 'https://gemini.example.test/v1beta',
        ]);

        Http::preventStrayRequests();
    }

    public function test_generate_returns_content_without_real_http_request(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'テスト回答です。'],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 12,
                    'candidatesTokenCount' => 8,
                ],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            [
                'role' => 'user',
                'parts' => [
                    ['text' => 'テスト質問です。'],
                ],
            ],
        ]);

        $this->assertSame('テスト回答です。', $result['content']);
        $this->assertSame(12, $result['input_tokens']);
        $this->assertSame(8, $result['output_tokens']);
        $this->assertSame('gemini-test-model', $result['model']);
    }

    public function test_generate_throws_when_response_content_is_empty(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini API returned no response content.',
        );

        app(GeminiService::class)->generate([
            [
                'role' => 'user',
                'parts' => [
                    ['text' => '空応答テスト'],
                ],
            ],
        ]);
    }

    public function test_generate_throws_request_exception_on_api_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Service unavailable',
                ],
            ], 503),
        ]);

        $this->expectException(RequestException::class);

        app(GeminiService::class)->generate([
            [
                'role' => 'user',
                'parts' => [
                    ['text' => '通信エラーテスト'],
                ],
            ],
        ]);
    }

    public function test_generate_sends_expected_prompt_structure(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => 'PHPについて教えてください。'],
                ],
            ],
        ];

        app(GeminiService::class)->generate(
            $contents,
            '資格学習を支援してください。',
        );

        Http::assertSent(function (Request $request) use ($contents): bool {
            return $request->url()
                === 'https://gemini.example.test/v1beta/models/gemini-test-model:generateContent?key=test-key'
                && $request['contents'] === $contents
                && $request['systemInstruction'] === [
                    'parts' => [
                        [
                            'text' => '資格学習を支援してください。',
                        ],
                    ],
                ];
        });
    }

    public function test_generate_retries_after_temporary_error_and_succeeds(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'Service unavailable',
                    ],
                ], 503)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => '再試行後の回答です。'],
                                ],
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $result = app(GeminiService::class)->generate([
            [
                'role' => 'user',
                'parts' => [
                    ['text' => '再試行テスト'],
                ],
            ],
        ]);

        $this->assertSame(
            '再試行後の回答です。',
            $result['content'],
        );

        Http::assertSentCount(2);
    }
}
