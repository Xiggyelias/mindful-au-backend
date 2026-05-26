<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'provider',
        'description',
        'is_active',
        'max_tokens',
        'cost_per_input_token',
        'cost_per_output_token',
        'capabilities',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capabilities' => 'array',
        'max_tokens' => 'integer',
        'cost_per_input_token' => 'decimal:8',
        'cost_per_output_token' => 'decimal:8',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'ai_model_id');
    }

    public static function findOrCreateByName(string $name, array $attributes = []): self
    {
        $name = trim($name);

        $defaults = array_merge([
            'display_name' => $attributes['display_name'] ?? Str::headline(str_replace(['/', '-', ':'], ' ', $name)),
            'provider' => $attributes['provider'] ?? self::guessProvider($name),
            'is_active' => true,
        ], $attributes);

        return static::query()->firstOrCreate(
            ['name' => $name],
            $defaults
        );
    }

    private static function guessProvider(string $name): string
    {
        $lower = Str::lower($name);

        if (Str::contains($lower, 'anthropic') || Str::contains($lower, 'claude')) {
            return 'anthropic';
        }
        if (Str::contains($lower, ['meta-llama', 'llama'])) {
            return 'meta';
        }
        if (Str::contains($lower, 'deepseek')) {
            return 'deepseek';
        }
        if (Str::contains($lower, ['liquid', 'lfm'])) {
            return 'liquid';
        }
        if (Str::contains($lower, 'nvidia')) {
            return 'nvidia';
        }
        if (Str::contains($lower, 'qwen')) {
            return 'qwen';
        }

        return 'openrouter';
    }
}
