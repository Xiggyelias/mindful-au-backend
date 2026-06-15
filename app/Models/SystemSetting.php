<?php

namespace App\Models;

use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (): void {
            SystemSettings::forgetCache();
        });

        static::saved(function (): void {
            SystemSettings::forgetCache();
        });

        static::deleting(function (): void {
            SystemSettings::forgetCache();
        });

        static::deleted(function (): void {
            SystemSettings::forgetCache();
        });
    }

    protected $fillable = [
        'key',
        'value',
        'category',
    ];

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = json_encode($value);
    }

    public function getValueAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
