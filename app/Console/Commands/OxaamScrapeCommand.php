<?php

namespace App\Console\Commands;

use App\Services\OxaamCsvExporter;
use App\Services\OxaamScraperService;
use Illuminate\Console\Command;

class OxaamScrapeCommand extends Command
{
    protected $signature = 'oxaam:scrape
        {--runs=1 : Number of scraper passes to execute}
        {--sleep=0 : Milliseconds to wait between passes}
        {--no-csv : Skip CSV export and only write runs to the database}
        {--csv= : CSV output path. Defaults to storage/app/exports/oxaam-scrape-YYYYmmdd-HHmmss.csv}';

    protected $description = 'Register/login to Oxaam, scrape the CG-AI credential block, and store a report.';

    public function handle(OxaamScraperService $scraper, OxaamCsvExporter $csvExporter): int
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
            $csvPath = $csvExporter->write(
                $collectedRuns,
                is_string($this->option('csv')) ? $this->option('csv') : null,
            );

            $this->newLine();
            $this->info('CSV saved to: '.$csvPath);
            $this->line('Open it directly: '.$csvPath);
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }
}
