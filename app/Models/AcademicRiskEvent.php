<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicRiskEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_run_id',
        'external_event_id',
        'student_identifier',
        'registration_number',
        'faculty',
        'year_of_study',
        'enrolment_status',
        'risk_type',
        'risk_score',
        'status',
        'linked_user_id',
        'received_at',
        'processed_at',
        'payload',
    ];

    protected $casts = [
        'risk_score' => 'decimal:2',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'payload' => 'array',
    ];

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }
}
