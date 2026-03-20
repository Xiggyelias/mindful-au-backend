<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTwoFactorMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'method',
        'secret_encrypted',
        'recovery_codes_encrypted',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_encrypted',
        'recovery_codes_encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
