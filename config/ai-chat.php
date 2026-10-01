<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', true),

    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 30),

    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 10),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta',
        ),
    ],
];
