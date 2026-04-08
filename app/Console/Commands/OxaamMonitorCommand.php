<?php

namespace App\Console\Commands;

use App\Services\OxaamBatchMonitor;
use Illuminate\Console\Command;

class OxaamMonitorCommand extends Command
{
    protected $signature = 'oxaam:monitor
        {--runs=120 : Number of scraper passes to execute}
        {--sleep=0 : Milliseconds to wait between passes}
        {--mode=changed : Notification mode: changed or always}
        {--profile=production : Profile label for snapshot comparison}
        {--to= : Recipient email address}
        {--csv= : CSV output path. Defaults to storage/app/exports/oxaam-monitor-<profile>-YYYYmmdd-HHmmss.csv}
        {--subject-prefix= : Optional subject prefix, useful for test mails}';

    protected $description = 'Run a monitored Oxaam scrape batch, compare with the last batch, and optionally email the CSV report.';

    public function handle(OxaamBatchMonitor $monitor): int
    {
        $runs = max((int) $this->option('runs'), 1);
        $sleep = max((int) $this->option('sleep'), 0);
        $mode = (string) $this->option('mode');
        $profile = (string) $this->option('profile');
        $recipient = $this->option('to');
        $csvPath = $this->option('csv');
        $subjectPrefix = $this->option('subject-prefix');

        $report = $monitor->run(
            runs: $runs,
            sleep: $sleep,
            mode: $mode,
            profile: $profile,
            recipient: is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null,
            csvPath: is_string($csvPath) && trim($csvPath) !== '' ? trim($csvPath) : null,
            subjectPrefix: is_string($subjectPrefix) && trim($subjectPrefix) !== '' ? trim($subjectPrefix) : null,
            onProgress: function (int $index, int $total, $run): void {
                if ($run->status === 'success') {
                    $this->info(sprintf(
                        '[%d/%d] %s | %s | %s',
                        $index,
                        $total,
                        $run->account_email,
                        $run->account_password,
                        $run->code_url
                    ));

                    return;
                }

                $this->error(sprintf(
                    '[%d/%d] %s',
                    $index,
                    $total,
                    $run->error_message ?: 'Unknown scrape failure.'
                ));
            },
        );

        $this->newLine();
        $this->info('Batch CSV: '.$report->csv_path);
        $this->line('Snapshot hash: '.($report->snapshot_hash ?? 'none'));
        $this->line('Notification reason: '.($report->notification_reason ?? 'n/a'));

        if ($report->email_sent_at) {
            $this->info('Mail sent to '.$report->email_sent_to.' at '.$report->email_sent_at->format('Y-m-d H:i:s'));
        } else {
            $this->line('Mail not sent.');
        }

        return $report->failed_runs > 0 ? self::FAILURE : self::SUCCESS;
    }
}
