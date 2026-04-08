<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OxaamCredential extends Model
{
    protected $fillable = [
        'first_seen_run_id',
        'last_seen_run_id',
        'last_session_id',
        'target_service',
        'service_label',
        'account_email',
        'account_password',
        'code_url',
        'seen_count',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function firstSeenRun(): BelongsTo
    {
        return $this->belongsTo(OxaamRun::class, 'first_seen_run_id');
    }

    public function lastSeenRun(): BelongsTo
    {
        return $this->belongsTo(OxaamRun::class, 'last_seen_run_id');
    }

    public function lastSession(): BelongsTo
    {
        return $this->belongsTo(OxaamSession::class, 'last_session_id');
    }
}
