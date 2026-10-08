<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => [
                'required',
                'string',
                'max:2000', // 暫定値。PM確認後に確定
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'メモ本文',
        ];
    }
}
