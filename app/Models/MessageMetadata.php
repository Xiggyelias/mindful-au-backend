<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageMetadata extends Model
{
    use HasFactory;

    protected $table = 'message_metadata';

    protected $fillable = [
        'message_id',
        'key',
        'value',
        'type',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
