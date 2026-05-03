<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiDiagnostic extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'session_id',
        'stress_level',
        'anxiety_level',
        'depression_level',
        'mood',
        'risk_level',
        'insights',
        'recommendations',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function session()
    {
        // For physical appointments, we use a virtual check
        if (is_string($this->session_id) && str_starts_with($this->session_id, 'apt_')) {
            return $this->belongsTo(Appointment::class, 'session_id');
        }
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    /**
     * Get the associated session or appointment object.
     */
    public function getContextAttribute()
    {
        if (is_string($this->session_id) && str_starts_with($this->session_id, 'apt_')) {
            return Appointment::find((int) substr($this->session_id, 4));
        }
        return CounselingSession::find($this->session_id);
    }
}
