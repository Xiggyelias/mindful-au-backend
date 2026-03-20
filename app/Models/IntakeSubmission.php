<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntakeSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'submitter_type',
        'is_anonymous',
        'anonymous_id',
        'presenting_concerns',
        'risk_answers',
        'consent_acknowledged',
        'risk_level',
        'urgency_score',
        'status',
        'assigned_to',
        'summary',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'consent_acknowledged' => 'boolean',
        'presenting_concerns' => 'array',
        'risk_answers' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function riskAlerts(): HasMany
    {
        return $this->hasMany(RiskAlert::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
