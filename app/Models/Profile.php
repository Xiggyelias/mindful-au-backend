<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'id_number',
        'avatar_url',
        'anonymous_mode',
        'peer_available',
    ];

    protected $casts = [
        'anonymous_mode' => 'boolean',
        'peer_available' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
