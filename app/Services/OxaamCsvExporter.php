<?php

namespace App\Services;

use App\Models\OxaamRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class OxaamCsvExporter
{
    /**
     * @param  list<OxaamRun>  $runs
     */
    public function write(array $runs, ?string $customPath = null, string $prefix = 'oxaam-scrape'): string
    {
        $path = $this->resolvePath($customPath, $prefix);
        $directory = dirname($path);

        File::ensureDirectoryExists($directory);

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not open the CSV output file for writing: '.$path);
        }

        fputcsv($handle, [
            'row_type',
            'last_run_id',
            'first_run_id',
            'status',
            'service',
            'account_email',
            'account_password',
            'code_url',
            'seen_count',
            'error_message',
            'last_scraped_at',
            'first_scraped_at',
            'session_email',
            'session_uses_after',
        ]);

        foreach ($this->aggregateSuccessfulRuns($runs) as $group) {
            fputcsv($handle, [
                'unique_success',
                $group['last_run']->id,
                $group['first_run']->id,
                'success',
                $group['last_run']->service_label ?? $group['last_run']->target_service,
                $group['last_run']->account_email,
                $group['last_run']->account_password,
                $group['last_run']->code_url,
                $group['seen_count'],
                null,
                $group['last_run']->scraped_at?->format('Y-m-d H:i:s'),
                $group['first_run']->scraped_at?->format('Y-m-d H:i:s'),
                $group['last_run']->session?->registration_email,
                $group['last_run']->session_uses_after,
            ]);
        }

        foreach ($runs as $run) {
            if ($run->status === 'success') {
                continue;
            }

            fputcsv($handle, [
                'failed_run',
                $run->id,
                $run->id,
                $run->status,
                $run->service_label ?? $run->target_service,
                $run->account_email,
                $run->account_password,
                $run->code_url,
                1,
                $run->error_message,
                $run->scraped_at?->format('Y-m-d H:i:s'),
                $run->scraped_at?->format('Y-m-d H:i:s'),
                $run->session?->registration_email,
                $run->session_uses_after,
            ]);
        }

        fclose($handle);

        return $path;
    }

    /**
     * @param  list<OxaamRun>  $runs
     * @return list<array{first_run: OxaamRun, last_run: OxaamRun, seen_count: int}>
     */
    protected function aggregateSuccessfulRuns(array $runs): array
    {
        $groups = [];

        foreach ($runs as $run) {
            if ($run->status !== 'success') {
                continue;
            }

            $key = implode('|', [
                $run->account_email,
                $run->account_password,
                $run->code_url,
            ]);

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'first_run' => $run,
                    'last_run' => $run,
                    'seen_count' => 0,
                ];
            }

            $groups[$key]['last_run'] = $run;
            $groups[$key]['seen_count']++;
        }

        return array_values($groups);
    }

    public function resolvePath(?string $customPath = null, string $prefix = 'oxaam-scrape'): string
    {
        if (is_string($customPath) && trim($customPath) !== '') {
            $customPath = trim($customPath);

            if ($this->isAbsolutePath($customPath)) {
                return $customPath;
            }

            return base_path($customPath);
        }

        return storage_path('app/exports/'.$prefix.'-'.now()->format('Ymd-His').'.csv');
    }

    protected function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, ['/', '\\'])
            || (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
