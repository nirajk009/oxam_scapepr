<?php

namespace App\Services;

use App\Mail\OxaamBatchReportMail;
use App\Models\OxaamBatchReport;
use App\Models\OxaamRun;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OxaamBatchMonitor
{
    public function __construct(
        protected OxaamScraperService $scraper,
        protected OxaamCsvExporter $csvExporter,
    ) {
    }

    /**
     * @param  null|callable(int, int, OxaamRun): void  $onProgress
     */
    public function run(
        int $runs = 120,
        int $sleep = 0,
        string $mode = 'changed',
        string $profile = 'production',
        ?string $recipient = null,
        ?string $csvPath = null,
        ?string $subjectPrefix = null,
        ?callable $onProgress = null,
        string $serviceKey = 'cgai',
    ): OxaamBatchReport {
        $mode = Str::lower(trim($mode));

        if (! in_array($mode, ['changed', 'always'], true)) {
            throw new InvalidArgumentException('Mode must be either "changed" or "always".');
        }

        $runs = max($runs, 1);
        $sleep = max($sleep, 0);
        $startedAt = now();

        $report = OxaamBatchReport::create([
            'profile' => $profile,
            'notification_mode' => $mode,
            'target_service' => $serviceKey,
            'runs_requested' => $runs,
            'started_at' => $startedAt,
        ]);

        $collectedRuns = [];

        for ($index = 1; $index <= $runs; $index++) {
            $run = $this->scraper->run($serviceKey);
            $collectedRuns[] = $run->loadMissing('session');

            if ($onProgress !== null) {
                $onProgress($index, $runs, $run);
            }

            if ($sleep > 0 && $index < $runs) {
                usleep($sleep * 1000);
            }
        }

        $successfulRuns = collect($collectedRuns)->where('status', 'success')->values();
        $snapshotRows = $successfulRuns
            ->map(fn (OxaamRun $run) => [
                'account_email' => $run->account_email,
                'account_password' => $run->account_password,
                'code_url' => $run->code_url,
            ])
            ->unique(fn (array $row) => implode('|', $row))
            ->sortBy(fn (array $row) => implode('|', $row))
            ->values();
        $snapshotHash = $snapshotRows->isNotEmpty()
            ? hash('sha256', $snapshotRows->map(fn (array $row) => implode('|', $row))->implode("\n"))
            : null;

        $csvPath = $this->csvExporter->write(
            $collectedRuns,
            $csvPath,
            'oxaam-monitor-'.$profile
        );

        $previousReport = OxaamBatchReport::query()
            ->where('profile', $profile)
            ->where('notification_mode', 'changed')
            ->where('id', '!=', $report->id)
            ->orderByDesc('id')
            ->first();

        $previousHash = $previousReport?->snapshot_hash;
        $shouldNotify = $mode === 'always'
            || ($snapshotHash !== null && $snapshotHash !== $previousHash);

        $notificationReason = match (true) {
            $mode === 'always' => 'always_send',
            $snapshotHash === null => 'no_successful_snapshot',
            $previousHash === null => 'first_snapshot',
            $previousHash !== $snapshotHash => 'snapshot_changed',
            default => 'snapshot_unchanged',
        };

        $report->forceFill([
            'runs_completed' => count($collectedRuns),
            'successful_runs' => $successfulRuns->count(),
            'failed_runs' => count($collectedRuns) - $successfulRuns->count(),
            'snapshot_hash' => $snapshotHash,
            'csv_path' => $csvPath,
            'should_notify' => $shouldNotify && filled($recipient),
            'notification_reason' => filled($recipient) ? $notificationReason : 'missing_recipient',
            'meta' => [
                'previous_hash' => $previousHash,
                'snapshot_rows' => $snapshotRows->all(),
                'latest_success' => $successfulRuns->last()?->only([
                    'account_email',
                    'account_password',
                    'code_url',
                    'scraped_at',
                ]),
            ],
            'completed_at' => now(),
        ]);

        if ($shouldNotify && filled($recipient)) {
            $subject = $this->subjectFor($profile, $mode, $notificationReason, $subjectPrefix, $successfulRuns->last());

            Mail::to($recipient)->send(new OxaamBatchReportMail(
                $report,
                [
                    'previous_hash' => $previousHash,
                    'current_hash' => $snapshotHash,
                    'latest_success' => $successfulRuns->last()?->only([
                        'account_email',
                        'account_password',
                        'code_url',
                    ]),
                ],
                $subject,
            ));

            $report->email_sent_to = $recipient;
            $report->email_sent_at = now();
        }

        $report->save();

        return $report->fresh();
    }

    protected function subjectFor(
        string $profile,
        string $mode,
        string $reason,
        ?string $subjectPrefix,
        ?OxaamRun $latestSuccess,
    ): string {
        $parts = array_filter([
            filled($subjectPrefix) ? trim($subjectPrefix) : null,
            strtoupper($profile),
            $mode === 'always' ? 'Oxaam test scrape' : 'Oxaam update detected',
        ]);

        $subject = implode(' | ', $parts);

        if ($latestSuccess?->account_email) {
            $subject .= ' | '.$latestSuccess->account_email;
        } else {
            $subject .= ' | '.$reason;
        }

        return $subject;
    }
}
