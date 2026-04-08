<?php

namespace Tests\Feature;

use App\Models\OxaamCredential;
use App\Models\OxaamRun;
use App\Models\OxaamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OxaamReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_page_renders_latest_results_and_unique_rows(): void
    {
        $session = OxaamSession::create([
            'registration_name' => 'Scraper Tester',
            'registration_email' => 'tester@example.com',
            'registration_phone' => '9876543210',
            'registration_password' => 'Oxaam123456#',
            'cookie_name' => 'PHPSESSID',
            'cookie_value' => 'abc123',
            'cookies' => [['Name' => 'PHPSESSID', 'Value' => 'abc123', 'Domain' => 'www.oxaam.com', 'Path' => '/']],
            'uses_count' => 8,
            'max_uses' => 300,
            'is_active' => true,
            'last_registered_at' => now()->subHour(),
            'last_validated_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        $run = OxaamRun::create([
            'oxaam_session_id' => $session->id,
            'target_service' => 'cgai',
            'status' => 'success',
            'http_status' => 200,
            'duration_ms' => 1400,
            'session_uses_after' => 9,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'dashboard_name' => 'Scraper Tester',
            'account_email' => 'oxaamcgai24@mapoba.com',
            'account_password' => 'Oxaam524179#',
            'code_url' => 'https://www.oxaam.com/cgcode63.php',
            'scraped_at' => now(),
        ]);

        OxaamCredential::create([
            'first_seen_run_id' => $run->id,
            'last_seen_run_id' => $run->id,
            'last_session_id' => $session->id,
            'target_service' => 'cgai',
            'service_label' => 'CG-AI',
            'account_email' => 'oxaamcgai24@mapoba.com',
            'account_password' => 'Oxaam524179#',
            'code_url' => 'https://www.oxaam.com/cgcode63.php',
            'seen_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSeeText('Oxaam CG-AI tracker');
        $response->assertSee('oxaamcgai24@mapoba.com');
        $response->assertSee('Oxaam524179#');
        $response->assertSee('https://www.oxaam.com/cgcode63.php');
    }
}
