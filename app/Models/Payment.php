<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'meeting_pack_id',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'amount',
        'quantity',
        'currency',
        'status',
        'completed_at',
        'failed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'quantity' => 'integer',
        'status' => PaymentStatus::class,
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MeetingPack, $this>
     */
    public function meetingPack(): BelongsTo
    {
        return $this->belongsTo(MeetingPack::class);
    }

    /**
     * @return HasOne<MeetingQuotaTransaction, $this>
     */
    public function quotaTransaction(): HasOne
    {
        return $this->hasOne(
            MeetingQuotaTransaction::class,
            'related_payment_id',
        );
    }
}
