<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounselorSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id',
        'counselor_schedule_id',
        'appointment_id',
        'slot_date',
        'day_of_week',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'slot_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'day_of_week' => 'integer',
    ];

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function schedule()
    {
        return $this->belongsTo(CounselorSchedule::class, 'counselor_schedule_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}
