<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Appointment extends Model
{
    use HasFactory;

    private static ?bool $hasCallTypeColumnCache = null;

    /**
     * Anonymous online bookings must never be stored as video — counselors only receive audio calls.
     * In-person (notes start with "physical") is exempt.
     *
     * `notes` is normalised alongside `call_type` because the two describe the same fact and the
     * UI reads the format out of `notes` (that is where "Video / online" vs "Audio / online"
     * comes from). Coercing only `call_type` left anonymous rows saying `notes = 'Online'`, so
     * the counselor's queue advertised a video session for a call stored as audio.
     */
    protected static function booted(): void
    {
        static::saving(function (Appointment $appointment): void {
            if (! $appointment->is_anonymous) {
                return;
            }
            $notes = strtolower(trim((string) ($appointment->notes ?? '')));
            if (str_starts_with($notes, 'physical')) {
                return;
            }
            if (self::supportsCallTypeColumn()) {
                $appointment->call_type = 'audio';
            }
            if (! str_starts_with($notes, 'online audio')) {
                $appointment->notes = 'Online audio';
            }
        });
    }

    private static function supportsCallTypeColumn(): bool
    {
        if (self::$hasCallTypeColumnCache !== null) {
            return self::$hasCallTypeColumnCache;
        }
        try {
            self::$hasCallTypeColumnCache = Schema::hasColumn('appointments', 'call_type');
        } catch (\Throwable) {
            self::$hasCallTypeColumnCache = false;
        }

        return self::$hasCallTypeColumnCache;
    }

    protected $fillable = [
        'student_id',
        'counselor_id',
        'counselor_slot_id',
        'is_anonymous',
        'is_emergency',
        'anonymous_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'cancelled_at',
        'reminder_sent',
        'notes',
        'call_type',
        'cancellation_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_anonymous' => 'boolean',
        'is_emergency' => 'boolean',
        'reminder_sent' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function counselorSlot()
    {
        return $this->belongsTo(CounselorSlot::class, 'counselor_slot_id');
    }
}
