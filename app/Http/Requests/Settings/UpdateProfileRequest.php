<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:50',
            ],
            'bio' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'meeting_url' => [
                'nullable',
                'string',
                'url',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談URL',
        ];
    }
}
