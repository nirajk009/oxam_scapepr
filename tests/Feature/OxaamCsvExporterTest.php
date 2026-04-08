<?php

namespace Tests\Feature;

use App\Models\OxaamRun;
use App\Models\OxaamSession;
use App\Services\OxaamCsvExporter;
use Tests\TestCase;

class OxaamCsvExporterTest extends TestCase
{
    public function test_it_aggregates_identical_successes_and_keeps_failure_rows(): void
    {
        $session = new OxaamSession([
            'registration_name' => 'CSV Tester',
            'registration_email' => 'csv@tester.com',
            'registration_phone' => '9876543210',
            'registration_password' => 'Oxaam123456#',
            'cookie_name' => 'PHPSESSID',
            'cookie_value' => 'cookie123',
            'cookies' => [['Name' => 'PHPSESSID', 'Value' => 'cookie123', 'Domain' => 'www.oxaam.com', 'Path' => '/']],
            'uses_count' => 0,
            'max_uses' => 300,
            'is_active' => true,
            'last_registered_at' => now()->subHour(),
            'last_validated_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        $successOne = new OxaamRun([
            'id' => 101,
            'target_service' => 'cgai',
            'status' => 'success',
            'http_status' => 200,
            'duration_ms' => 500,
            'session_uses_after' => 1,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'dashboard_name' => 'CSV Tester',
            'account_email' => 'same@mapoba.com',
            'account_password' => 'Oxaam444444#',
            'code_url' => 'https://www.oxaam.com/cgcode44.php',
            'scraped_at' => now()->subSeconds(30),
        ]);
        $successOne->setRelation('session', $session);

        $successTwo = new OxaamRun([
            'id' => 102,
            'target_service' => 'cgai',
            'status' => 'success',
            'http_status' => 200,
            'duration_ms' => 500,
            'session_uses_after' => 2,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'dashboard_name' => 'CSV Tester',
            'account_email' => 'same@mapoba.com',
            'account_password' => 'Oxaam444444#',
            'code_url' => 'https://www.oxaam.com/cgcode44.php',
            'scraped_at' => now(),
        ]);
        $successTwo->setRelation('session', $session);

        $failed = new OxaamRun([
            'id' => 103,
            'target_service' => 'cgai',
            'status' => 'failed',
            'http_status' => 500,
            'duration_ms' => 500,
            'session_uses_after' => 2,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'error_message' => 'Some failure',
            'scraped_at' => now(),
        ]);
        $failed->setRelation('session', $session);

        $path = app(OxaamCsvExporter::class)->write(
            [$successOne, $successTwo, $failed],
            'storage/app/exports/csv-exporter-test.csv',
            'csv-exporter-test',
        );

        $this->assertFileExists($path);

        $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));

        $this->assertSame([
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
        ], $rows[0]);

        $this->assertSame('unique_success', $rows[1][0]);
        $this->assertSame('2', $rows[1][8]);
        $this->assertSame('same@mapoba.com', $rows[1][5]);

        $this->assertSame('failed_run', $rows[2][0]);
        $this->assertSame('Some failure', $rows[2][9]);
    }
}
