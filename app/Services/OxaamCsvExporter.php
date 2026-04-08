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
