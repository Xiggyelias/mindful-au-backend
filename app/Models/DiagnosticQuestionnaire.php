<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticQuestionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'questions',
        'status',
        'version',
    ];

    protected $casts = [
        'questions' => 'array',
    ];
}
