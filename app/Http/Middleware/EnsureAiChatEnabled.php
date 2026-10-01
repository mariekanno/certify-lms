<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI チャット機能が無効化されている場合にアクセスを拒否する Middleware。
 */
final class EnsureAiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai-chat.enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
