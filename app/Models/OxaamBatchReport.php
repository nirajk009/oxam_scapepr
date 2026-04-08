<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OxaamBatchReport extends Model
{
    protected $fillable = [
        'profile',
        'notification_mode',
        'target_service',
        'runs_requested',
        'runs_completed',
        'successful_runs',
        'failed_runs',
        'snapshot_hash',
        'csv_path',
        'should_notify',
        'notification_reason',
        'email_sent_to',
        'email_sent_at',
        'meta',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'should_notify' => 'boolean',
            'meta' => 'array',
            'email_sent_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
