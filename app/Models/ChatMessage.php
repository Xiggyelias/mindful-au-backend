<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function metadataEntries(): HasMany
    {
        return $this->hasMany(MessageMetadata::class, 'message_id');
    }

    public function addMetadata(string $key, mixed $value, string $type = 'string'): MessageMetadata
    {
        return $this->metadataEntries()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
                'type' => $type,
            ]
        );
    }
}
