<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatConversation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'enrollment_id',
        'section_id',
        'title',
        'auto_title_enabled',
        'last_message_at',
    ];

    protected $casts = [
        'auto_title_enabled' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    /**
     * 会話の所有者。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 会話に紐づく受講情報。
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * 会話に紐づく教材 Section。
     *
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * 会話内のメッセージ一覧。
     *
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id')
            ->orderBy('created_at');
    }
}
