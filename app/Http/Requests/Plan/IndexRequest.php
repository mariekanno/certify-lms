<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Plan::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PlanStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
            'page' => 'ページ',
        ];
    }

    public function filters(): array
    {
        return [
            'keyword' => $this->validated('keyword'),
            'status' => $this->validated('status'),
        ];
    }
}
