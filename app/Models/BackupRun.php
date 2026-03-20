<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'file_path',
        'file_size_bytes',
        'checksum_sha256',
        'is_encrypted',
        'verification_status',
        'error_message',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];
}
