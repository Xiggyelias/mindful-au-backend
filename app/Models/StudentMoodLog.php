<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMoodLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'mood',
        'logged_on',
    ];

    protected $casts = [
        'logged_on' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
