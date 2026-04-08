<p>Oxaam monitor report for profile <strong>{{ $report->profile }}</strong>.</p>

<p>
    Mode: {{ $report->notification_mode }}<br>
    Reason: {{ $report->notification_reason ?? 'n/a' }}<br>
    Runs requested: {{ $report->runs_requested }}<br>
    Runs completed: {{ $report->runs_completed }}<br>
    Successful runs: {{ $report->successful_runs }}<br>
    Failed runs: {{ $report->failed_runs }}<br>
    Started: {{ $report->started_at?->format('Y-m-d H:i:s') ?? 'n/a' }}<br>
    Completed: {{ $report->completed_at?->format('Y-m-d H:i:s') ?? 'n/a' }}
</p>

@if (! empty($summary['latest_success']))
    <p>
        Latest success:<br>
        Email: {{ $summary['latest_success']['account_email'] }}<br>
        Password: {{ $summary['latest_success']['account_password'] }}<br>
        Code URL: {{ $summary['latest_success']['code_url'] }}
    </p>
@endif

@if (! empty($summary['previous_hash']) || ! empty($summary['current_hash']))
    <p>
        Previous snapshot hash: {{ $summary['previous_hash'] ?? 'none' }}<br>
        Current snapshot hash: {{ $summary['current_hash'] ?? 'none' }}
    </p>
@endif

<p>The attached CSV contains the captured run rows for this batch.</p>
