<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI チャットメッセージの処理状態を表す Enum。
 *
 * - Pending: AI 応答待ち
 * - Completed: AI 応答完了
 * - Error: AI 応答失敗
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';
}
