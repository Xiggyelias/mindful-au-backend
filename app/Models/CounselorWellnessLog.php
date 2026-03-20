<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CounselorWellnessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_id',
        'mood_score',
        'stress_level',
        'burnout_index',
        'recommendations',
        'notes',
        'check_in_answers',
        'check_in_version',
    ];

    protected $casts = [
        'check_in_answers' => 'array',
    ];

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
