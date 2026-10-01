<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
