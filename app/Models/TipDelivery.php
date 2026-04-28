<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tip_id',
        'delivered_on',
        'audience',
        'mood',
        'personalized',
        'notification_id',
    ];

    protected $casts = [
        'delivered_on' => 'date',
        'personalized' => 'boolean',
        'notification_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tip()
    {
        return $this->belongsTo(Tip::class);
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
