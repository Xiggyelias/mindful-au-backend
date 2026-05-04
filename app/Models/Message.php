<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (self $message): void {
            CounselingSession::query()->whereKey((int) $message->session_id)->update([
                'updated_at' => now(),
            ]);
        });
    }

    protected $fillable = [
        'session_id',
        'sender_id',
        'recipient_id',
        'content',
        'message_type',
        'file_url',
        'has_file',
        'is_encrypted',
        'seen_at',
    ];

    protected $casts = [
        'has_file' => 'boolean',
        'is_encrypted' => 'boolean',
        'seen_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function chatFile()
    {
        return $this->hasOne(ChatFile::class, 'message_id');
    }
}
