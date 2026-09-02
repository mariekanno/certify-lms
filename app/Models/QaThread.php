<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    public function scopePublishedCertification(Builder $query): Builder
    {
        return $query->whereHas('certification', function (Builder $q): void {
            $q->where('status', CertificationStatus::Published->value);
        });
    }

    public function scopeStatusFilter(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('title', 'LIKE', '%'.$keyword.'%')
                ->orWhere('body', 'LIKE', '%'.$keyword.'%');
        });
    }

    public function scopeAssignedToCoach(Builder $query, User $coach): Builder
    {
        $certificationIds = $coach->coachingCertificationIds();

        if ($certificationIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('certification_id', $certificationIds);
    }
}
