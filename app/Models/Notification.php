<?php

namespace App\Models;

use App\Services\NotificationEmailService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'meta',
        'type',
        'read',
    ];

    protected $casts = [
        'read' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (Notification $notification): void {
            app(NotificationEmailService::class)->sendForNotification($notification);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
