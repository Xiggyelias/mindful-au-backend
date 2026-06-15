<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Escalation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'escalated_by',
        'escalated_to',
        'escalation_type',
        'severity',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    public function escalatedByUser()
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function escalatedToUser()
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }
}
