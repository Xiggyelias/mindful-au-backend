<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tip_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tip()
    {
        return $this->belongsTo(Tip::class);
    }
}
