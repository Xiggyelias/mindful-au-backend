<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'role',
        'approved',
        'is_active',
        'full_name',
        'id_number',
    ];

    protected $casts = [
        'approved' => 'boolean',
        'is_active' => 'boolean',
    ];
}
