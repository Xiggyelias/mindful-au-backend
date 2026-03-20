<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagnostic extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'responses',
        'total_score',
        'risk_level',
        'category_scores',
        'ai_recommendations',
        'insights',
        'is_anonymous',
        'anonymous_id',
    ];

    protected $casts = [
        'responses' => 'array',
        'category_scores' => 'array',
        'ai_recommendations' => 'array',
        'is_anonymous' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
