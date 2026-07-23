<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CounselingCall extends Model
{
    protected $table = 'calls';

    /** Ringing, not yet answered. CALLING from the caller's side, RINGING from the callee's. */
    public const STATUS_PENDING = 'pending';

    /** Callee answered. CONNECTED for both sides. */
    public const STATUS_ACCEPTED = 'accepted';

    /** Callee explicitly rejected. */
    public const STATUS_DECLINED = 'declined';

    /** Caller hung up before the callee answered. */
    public const STATUS_CANCELLED = 'cancelled';

    /** Nobody answered before expires_at. */
    public const STATUS_MISSED = 'missed';

    /** Statuses that make a user "busy" for the purposes of starting/receiving another call. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED];

    /** Student placed the call (counselor sees incoming). */
    public const CALLER_STUDENT = 'student';

    /** Counselor placed the call (student sees incoming). */
    public const CALLER_COUNSELOR = 'counselor';

    protected $fillable = [
        'appointment_id',
        'student_id',
        'counselor_id',
        'status',
        'call_type',
        'caller_role',
        'expires_at',
        'connected_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    /** A pending call is only "ringing" until its TTL lapses; after that it's stale (should be swept to missed). */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isPast();
    }

    /** Rows that currently make either participant unavailable for a new call. */
    public function scopeActiveForUser(Builder $query, int $userId): Builder
    {
        return $query
            ->where(function (Builder $q) use ($userId) {
                $q->where('student_id', $userId)->orWhere('counselor_id', $userId);
            })
            ->where(function (Builder $q) {
                $q->where('status', self::STATUS_ACCEPTED)
                    ->orWhere(function (Builder $pending) {
                        $pending->where('status', self::STATUS_PENDING)
                            ->where(function (Builder $notExpired) {
                                $notExpired->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    });
            });
    }
}
