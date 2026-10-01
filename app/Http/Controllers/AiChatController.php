<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiChatController extends Controller
{
    public function index(Request $request): RedirectResponse|View
    {
        $conversation = $request->user()
            ->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();

        if ($conversation === null) {
            return view('ai-chat.empty-state');
        }

        return redirect()->route(
            'ai-chat.conversations.show',
            $conversation,
        );
    }
}
