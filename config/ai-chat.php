<?php

declare(strict_types=1);

return [
    'enabled' => env('AI_CHAT_ENABLED', true),

    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 50),

    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 20),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        'base_url' => env(
            'GEMINI_BASE_URL',
            'https://generativelanguage.googleapis.com/v1beta',
        ),
    ],
];
