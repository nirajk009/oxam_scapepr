<?php

namespace App\Jobs;

use App\Services\OxaamScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunOxaamScrape implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(OxaamScraperService $scraper): void
    {
        $scraper->run();
    }
}
