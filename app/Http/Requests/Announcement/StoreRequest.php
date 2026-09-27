<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => [
                'required',
                'string',
                Rule::in([
                    AnnouncementTargetType::AllStudents->value,
                    AnnouncementTargetType::Certification->value,
                    AnnouncementTargetType::User->value,
                ]),
            ],
            'target_certification_id' => [
                'nullable',
                Rule::requiredIf(
                    $this->input('target_type') === AnnouncementTargetType::Certification->value
                ),
                Rule::prohibitedIf(
                    $this->input('target_type') !== AnnouncementTargetType::Certification->value
                ),
                'ulid',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'nullable',
                Rule::requiredIf(
                    $this->input('target_type') === AnnouncementTargetType::User->value
                ),
                Rule::prohibitedIf(
                    $this->input('target_type') !== AnnouncementTargetType::User->value
                ),
                'ulid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', UserRole::Student->value)
                        ->where('status', UserStatus::InProgress->value)
                        ->whereNull('deleted_at');
                }),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
