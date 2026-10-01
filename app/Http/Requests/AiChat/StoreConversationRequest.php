<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => [
                'required',
                'string',
                Rule::in(['full-screen', 'widget']),
            ],
            'section_id' => [
                'nullable',
                'ulid',
                'exists:sections,id',
            ],
            'message' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
