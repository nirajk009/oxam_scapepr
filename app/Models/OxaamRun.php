<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OxaamRun extends Model
{
    protected $fillable = [
        'oxaam_session_id',
        'target_service',
        'status',
        'http_status',
        'duration_ms',
        'session_uses_after',
        'service_label',
        'page_title',
        'dashboard_name',
        'account_email',
        'account_password',
        'code_url',
        'report',
        'error_message',
        'scraped_at',
    ];

    protected function casts(): array
    {
        return [
            'report' => 'array',
            'scraped_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(OxaamSession::class, 'oxaam_session_id');
    }
}
