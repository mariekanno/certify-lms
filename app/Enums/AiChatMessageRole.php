<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI チャットメッセージの送信者種別を表す Enum。
 *
 * - User: 受講生が送信したメッセージ
 * - Assistant: Gemini が生成した応答
 */
enum AiChatMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
