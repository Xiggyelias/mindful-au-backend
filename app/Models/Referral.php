<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'intake_submission_id',
        'student_id',
        'referred_by',
        'direction',
        'target_service',
        'destination_details',
        'consent_granted',
        'shared_fields',
        'status',
        'referred_at',
        'closed_at',
        'outcome_notes',
    ];

    protected $casts = [
        'consent_granted' => 'boolean',
        'shared_fields' => 'array',
        'referred_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    public function intakeSubmission(): BelongsTo
    {
        return $this->belongsTo(IntakeSubmission::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class);
    }
}
