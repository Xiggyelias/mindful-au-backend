<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'run_type',
        'status',
        'received_count',
        'processed_count',
        'failed_count',
        'error_summary',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function academicRiskEvents(): HasMany
    {
        return $this->hasMany(AcademicRiskEvent::class);
    }
}
