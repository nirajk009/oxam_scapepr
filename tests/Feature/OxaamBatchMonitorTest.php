<?php

namespace Tests\Feature;

use App\Mail\OxaamBatchReportMail;
use App\Models\OxaamBatchReport;
use App\Models\OxaamRun;
use App\Models\OxaamSession;
use App\Services\OxaamBatchMonitor;
use App\Services\OxaamScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class OxaamBatchMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_mode_sends_mail_for_the_first_successful_snapshot(): void
    {
        Mail::fake();

        $run = $this->makeSuccessfulRun(
            email: 'first@mapoba.com',
            password: 'Oxaam111111#',
            codeUrl: 'https://www.oxaam.com/cgcode11.php',
        );

        $mock = Mockery::mock(OxaamScraperService::class);
        $mock->shouldReceive('run')->once()->andReturn($run);
        $this->app->instance(OxaamScraperService::class, $mock);

        $report = app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        $this->assertTrue($report->should_notify);
        $this->assertSame('first_snapshot', $report->notification_reason);
        $this->assertNotNull($report->email_sent_at);
        $this->assertFileExists($report->csv_path);

        Mail::assertSent(OxaamBatchReportMail::class, function (OxaamBatchReportMail $mail) use ($report) {
            return $mail->hasTo('notify@example.com')
                && $mail->report->is($report);
        });
    }

    public function test_changed_mode_skips_mail_when_the_snapshot_is_identical_to_the_previous_batch(): void
    {
        $firstRun = $this->makeSuccessfulRun(
            email: 'same@mapoba.com',
            password: 'Oxaam222222#',
            codeUrl: 'https://www.oxaam.com/cgcode22.php',
        );

        $firstMock = Mockery::mock(OxaamScraperService::class);
        $firstMock->shouldReceive('run')->once()->andReturn($firstRun);
        $this->app->instance(OxaamScraperService::class, $firstMock);

        Mail::fake();

        app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        Mail::assertSentCount(1);

        $secondRun = $this->makeSuccessfulRun(
            email: 'same@mapoba.com',
            password: 'Oxaam222222#',
            codeUrl: 'https://www.oxaam.com/cgcode22.php',
        );

        $secondMock = Mockery::mock(OxaamScraperService::class);
        $secondMock->shouldReceive('run')->once()->andReturn($secondRun);
        $this->app->instance(OxaamScraperService::class, $secondMock);

        Mail::fake();

        $report = app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        $this->assertFalse($report->should_notify);
        $this->assertSame('snapshot_unchanged', $report->notification_reason);
        $this->assertNull($report->email_sent_at);
        $this->assertSame(2, OxaamBatchReport::count());

        Mail::assertNothingSent();
    }

    public function test_always_mode_sends_mail_even_when_the_snapshot_matches_an_earlier_batch(): void
    {
        Mail::fake();

        $run = $this->makeSuccessfulRun(
            email: 'always@mapoba.com',
            password: 'Oxaam333333#',
            codeUrl: 'https://www.oxaam.com/cgcode33.php',
        );

        $mock = Mockery::mock(OxaamScraperService::class);
        $mock->shouldReceive('run')->once()->andReturn($run);
        $this->app->instance(OxaamScraperService::class, $mock);

        $report = app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'always',
            profile: 'test',
            recipient: 'notify@example.com',
            subjectPrefix: 'TEST',
        );

        $this->assertTrue($report->should_notify);
        $this->assertSame('always_send', $report->notification_reason);

        Mail::assertSent(OxaamBatchReportMail::class, function (OxaamBatchReportMail $mail) {
            return $mail->hasTo('notify@example.com')
                && str_contains($mail->subjectLine, 'TEST');
        });
    }

    public function test_changed_mode_compares_against_the_last_sent_snapshot_not_the_last_unsent_batch(): void
    {
        Mail::fake();

        $firstSuccess = $this->makeSuccessfulRun(
            email: 'baseline@mapoba.com',
            password: 'Oxaam999111#',
            codeUrl: 'https://www.oxaam.com/cgcode71.php',
        );

        $firstMock = Mockery::mock(OxaamScraperService::class);
        $firstMock->shouldReceive('run')->once()->andReturn($firstSuccess);
        $this->app->instance(OxaamScraperService::class, $firstMock);

        app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        Mail::assertSentCount(1);

        $failedRun = $this->makeFailedRun('Temporary failure');

        $failedMock = Mockery::mock(OxaamScraperService::class);
        $failedMock->shouldReceive('run')->once()->andReturn($failedRun);
        $this->app->instance(OxaamScraperService::class, $failedMock);

        Mail::fake();

        $failedReport = app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        $this->assertFalse($failedReport->should_notify);
        $this->assertSame('no_successful_snapshot', $failedReport->notification_reason);
        Mail::assertNothingSent();

        $sameAsBaseline = $this->makeSuccessfulRun(
            email: 'baseline@mapoba.com',
            password: 'Oxaam999111#',
            codeUrl: 'https://www.oxaam.com/cgcode71.php',
        );

        $sameMock = Mockery::mock(OxaamScraperService::class);
        $sameMock->shouldReceive('run')->once()->andReturn($sameAsBaseline);
        $this->app->instance(OxaamScraperService::class, $sameMock);

        Mail::fake();

        $report = app(OxaamBatchMonitor::class)->run(
            runs: 1,
            mode: 'changed',
            profile: 'production',
            recipient: 'notify@example.com',
        );

        $this->assertFalse($report->should_notify);
        $this->assertSame('snapshot_unchanged', $report->notification_reason);
        Mail::assertNothingSent();
    }

    protected function makeSuccessfulRun(string $email, string $password, string $codeUrl): OxaamRun
    {
        $session = OxaamSession::create([
            'registration_name' => 'Batch Monitor Tester',
            'registration_email' => 'session'.uniqid().'@example.com',
            'registration_phone' => '9876543210',
            'registration_password' => 'Oxaam654321#',
            'cookie_name' => 'PHPSESSID',
            'cookie_value' => uniqid('sess_', true),
            'cookies' => [['Name' => 'PHPSESSID', 'Value' => uniqid('sess_', true), 'Domain' => 'www.oxaam.com', 'Path' => '/']],
            'uses_count' => 0,
            'max_uses' => 300,
            'is_active' => true,
            'last_registered_at' => now()->subHour(),
            'last_validated_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        return OxaamRun::create([
            'oxaam_session_id' => $session->id,
            'target_service' => 'cgai',
            'status' => 'success',
            'http_status' => 200,
            'duration_ms' => 500,
            'session_uses_after' => 1,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'dashboard_name' => 'Batch Monitor Tester',
            'account_email' => $email,
            'account_password' => $password,
            'code_url' => $codeUrl,
            'scraped_at' => now(),
        ])->load('session');
    }

    protected function makeFailedRun(string $message): OxaamRun
    {
        $session = OxaamSession::create([
            'registration_name' => 'Batch Monitor Tester',
            'registration_email' => 'failed'.uniqid().'@example.com',
            'registration_phone' => '9876543210',
            'registration_password' => 'Oxaam654321#',
            'cookie_name' => 'PHPSESSID',
            'cookie_value' => uniqid('sess_', true),
            'cookies' => [['Name' => 'PHPSESSID', 'Value' => uniqid('sess_', true), 'Domain' => 'www.oxaam.com', 'Path' => '/']],
            'uses_count' => 0,
            'max_uses' => 300,
            'is_active' => true,
            'last_registered_at' => now()->subHour(),
            'last_validated_at' => now()->subMinute(),
            'last_used_at' => now()->subMinute(),
        ]);

        return OxaamRun::create([
            'oxaam_session_id' => $session->id,
            'target_service' => 'cgai',
            'status' => 'failed',
            'http_status' => 500,
            'duration_ms' => 500,
            'session_uses_after' => 0,
            'service_label' => 'CG-AI',
            'page_title' => 'Dashboard | Oxaam',
            'error_message' => $message,
            'scraped_at' => now(),
        ])->load('session');
    }
}
