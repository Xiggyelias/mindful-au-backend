<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'role_id',
        'approved',
    ];

    protected $casts = [
        'approved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // Helper methods for backward compatibility
    public function getRoleNameAttribute(): string
    {
        return $this->role?->name ?? $this->attributes['role'] ?? 'unknown';
    }

    public function getRoleDisplayNameAttribute(): string
    {
        return $this->role?->display_name ?? ucfirst($this->getRoleNameAttribute());
    }

    public function getRequiresApprovalAttribute(): bool
    {
        return $this->role?->requires_approval ?? false;
    }

    public function getRoleLevelAttribute(): int
    {
        return $this->role?->level ?? 0;
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('approved', false);
    }

    public function scopeByRoleName($query, string $roleName)
    {
        return $query->whereHas('role', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        })->orWhere('role', $roleName);
    }
}
