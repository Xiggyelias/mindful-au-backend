<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmergencyRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'counselor_id',
        'assigned_to',
        'counselor_slot_id',
        'requested_at',
        'is_after_hours',
        'priority',
        'status',
        'location',
        'reason',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_after_hours' => 'boolean',
        'priority' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function slot()
    {
        return $this->belongsTo(CounselorSlot::class, 'counselor_slot_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
