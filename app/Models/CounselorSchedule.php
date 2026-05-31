<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounselorSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id',
        'day_of_week',
        'is_working_day',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'slot_duration_minutes',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
        'day_of_week' => 'integer',
        'slot_duration_minutes' => 'integer',
    ];

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function slots()
    {
        return $this->hasMany(CounselorSlot::class, 'counselor_schedule_id');
    }
}
