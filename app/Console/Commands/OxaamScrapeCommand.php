<?php

namespace App\Console\Commands;

use App\Models\OxaamRun;
use App\Services\OxaamScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OxaamScrapeCommand extends Command
{
    protected $signature = 'oxaam:scrape
        {--runs=1 : Number of scraper passes to execute}
        {--sleep=0 : Milliseconds to wait between passes}
        {--no-csv : Skip CSV export and only write runs to the database}
        {--csv= : CSV output path. Defaults to storage/app/exports/oxaam-scrape-YYYYmmdd-HHmmss.csv}';

    protected $description = 'Register/login to Oxaam, scrape the CG-AI credential block, and store a report.';

    public function handle(OxaamScraperService $scraper): int
    {
        $runs = max((int) $this->option('runs'), 1);
        $sleep = max((int) $this->option('sleep'), 0);
        $hadFailure = false;
        $collectedRuns = [];

        for ($index = 1; $index <= $runs; $index++) {
            $run = $scraper->run();
            $collectedRuns[] = $run;

            if ($run->status === 'success') {
                $remaining = $run->session?->uses_remaining;

                $this->info(sprintf(
                    '[%d/%d] %s | %s | %s | remaining session uses: %s',
                    $index,
                    $runs,
                    $run->account_email,
                    $run->account_password,
                    $run->code_url,
                    $remaining ?? 'n/a'
                ));
            } else {
                $hadFailure = true;
                $this->error(sprintf('[%d/%d] %s', $index, $runs, $run->error_message ?: 'Unknown scrape failure.'));
            }

            if ($sleep > 0 && $index < $runs) {
                usleep($sleep * 1000);
            }
        }

        if (! $this->option('no-csv')) {
            $csvPath = $this->writeCsv($collectedRuns);

            $this->newLine();
            $this->info('CSV saved to: '.$csvPath);
            $this->line('Open it directly: '.$csvPath);
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<OxaamRun>  $runs
     */
    protected function writeCsv(array $runs): string
    {
        $path = $this->resolveCsvPath();
        $directory = dirname($path);

        File::ensureDirectoryExists($directory);

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Could not open the CSV output file for writing: '.$path);
        }

        fputcsv($handle, [
            'run_id',
            'status',
            'service',
            'account_email',
            'account_password',
            'code_url',
            'error_message',
            'scraped_at',
            'session_email',
            'session_uses_after',
        ]);

        $seenCredentials = [];

        foreach ($runs as $run) {
            if ($run->status === 'success') {
                $key = implode('|', [
                    $run->account_email,
                    $run->account_password,
                    $run->code_url,
                ]);

                if (isset($seenCredentials[$key])) {
                    continue;
                }

                $seenCredentials[$key] = true;
            }

            fputcsv($handle, [
                $run->id,
                $run->status,
                $run->service_label ?? $run->target_service,
                $run->account_email,
                $run->account_password,
                $run->code_url,
                $run->error_message,
                $run->scraped_at?->format('Y-m-d H:i:s'),
                $run->session?->registration_email,
                $run->session_uses_after,
            ]);
        }

        fclose($handle);

        return $path;
    }

    protected function resolveCsvPath(): string
    {
        $customPath = $this->option('csv');

        if (is_string($customPath) && trim($customPath) !== '') {
            $customPath = trim($customPath);

            if ($this->isAbsolutePath($customPath)) {
                return $customPath;
            }

            return base_path($customPath);
        }

        return storage_path('app/exports/oxaam-scrape-'.now()->format('Ymd-His').'.csv');
    }

    protected function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\'])
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
