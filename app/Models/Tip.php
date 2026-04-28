<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tip extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'category',
        'audience',
        'mood_tags',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'mood_tags' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function deliveries()
    {
        return $this->hasMany(TipDelivery::class);
    }

    public function favorites()
    {
        return $this->hasMany(TipFavorite::class);
    }
}
