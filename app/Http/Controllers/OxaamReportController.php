<?php

namespace App\Http\Controllers;

use App\Jobs\RunOxaamScrape;
use App\Models\OxaamCredential;
use App\Models\OxaamRun;
use App\Models\OxaamSession;
use App\Services\OxaamScraperService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Process;

class OxaamReportController extends Controller
{
    public function __construct(
        protected OxaamScraperService $scraper,
    ) {
    }

    public function index(): View
    {
        $latestRun = OxaamRun::query()
            ->with('session')
            ->orderByDesc('scraped_at')
            ->orderByDesc('id')
            ->first();

        $uniqueCredentials = OxaamCredential::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        $recentRuns = OxaamRun::query()
            ->with('session')
            ->orderByDesc('scraped_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $activeSession = OxaamSession::query()
            ->where('is_active', true)
            ->whereColumn('uses_count', '<', 'max_uses')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->first();

        return view('oxaam-report', [
            'latestRun' => $latestRun,
            'uniqueCredentials' => $uniqueCredentials,
            'recentRuns' => $recentRuns,
            'activeSession' => $activeSession,
            'webTrigger' => (string) config('services.oxaam.web_trigger', 'queue_worker'),
            'stats' => [
                'total_runs' => OxaamRun::count(),
                'successful_runs' => OxaamRun::where('status', 'success')->count(),
                'unique_credentials' => OxaamCredential::count(),
                'active_sessions' => OxaamSession::where('is_active', true)
                    ->whereColumn('uses_count', '<', 'max_uses')
                    ->count(),
            ],
        ]);
    }

    public function scrape(): RedirectResponse
    {
        if ((string) config('services.oxaam.web_trigger', 'queue_worker') === 'queue_worker') {
            RunOxaamScrape::dispatch();

            return redirect()
                ->route('report.index')
                ->with('status', 'Scrape queued. Keep `php artisan queue:work` running, then refresh in a few seconds.');
        }

        if ((string) config('services.oxaam.web_trigger', 'background_cli') === 'background_cli') {
            Process::path(base_path())->start([
                PHP_BINARY,
                'artisan',
                'oxaam:scrape',
                '--runs=1',
            ]);

            return redirect()
                ->route('report.index')
                ->with('status', 'Scrape started in the background. Refresh in a few seconds to see the new row.');
        }

        $run = $this->scraper->run();

        if ($run->status === 'success') {
            $remaining = $run->session?->uses_remaining;

            return redirect()
                ->route('report.index')
                ->with('status', sprintf(
                    'Scrape completed. Captured %s and saved it to the unique credential table. %s',
                    $run->account_email,
                    $remaining !== null ? "Session uses left: {$remaining}." : ''
                ));
        }

        return redirect()
            ->route('report.index')
            ->with('error', $run->error_message ?: 'The scrape failed before a credential row could be captured.');
    }
}
