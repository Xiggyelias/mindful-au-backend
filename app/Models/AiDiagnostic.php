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
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }
}
