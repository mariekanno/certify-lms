<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MeetingPack::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MeetingPackStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
            'page' => 'ページ番号',
        ];
    }

    /**
     * @return array{
     *     keyword: string|null,
     *     status: string|null
     * }
     */
    public function filters(): array
    {
        return [
            'keyword' => $this->validated('keyword'),
            'status' => $this->validated('status'),
        ];
    }
}
