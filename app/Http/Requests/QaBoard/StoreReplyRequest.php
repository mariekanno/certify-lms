<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }
}
