<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OxaamSession extends Model
{
    protected $fillable = [
        'registration_name',
        'registration_email',
        'registration_phone',
        'registration_password',
        'cookie_name',
        'cookie_value',
        'cookies',
        'uses_count',
        'max_uses',
        'is_active',
        'last_registered_at',
        'last_validated_at',
        'last_used_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'cookies' => 'array',
            'is_active' => 'boolean',
            'last_registered_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(OxaamRun::class);
    }

    public function getUsesRemainingAttribute(): int
    {
        return max($this->max_uses - $this->uses_count, 0);
    }

    public function markInvalid(?string $message = null): void
    {
        $this->forceFill([
            'is_active' => false,
            'last_error' => $message,
        ])->save();
    }
}
