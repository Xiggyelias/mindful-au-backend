<?php

namespace App\Models;

use App\Services\OpenRouterService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_model_id',
        'title',
        'model',
        'is_active',
        'last_message_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function updateLastMessageAt(): void
    {
        $this->last_message_at = now();
    }

    public function getModelName(): string
    {
        if ($this->relationLoaded('aiModel') && $this->aiModel?->name) {
            return $this->aiModel->name;
        }

        if (!empty($this->model)) {
            return (string) $this->model;
        }

        return OpenRouterService::configuredChatModel();
    }
}
