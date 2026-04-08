<?php

namespace Tests\Unit;

use App\Services\OxaamDashboardParser;
use Tests\TestCase;

class OxaamDashboardParserTest extends TestCase
{
    public function test_it_extracts_the_cgai_credential_block_from_the_saved_dashboard_fixture(): void
    {
        $parser = app(OxaamDashboardParser::class);
        $html = file_get_contents(base_path('Page_to_scrap/main.html'));

        $parsed = $parser->parseServiceCredential($html, 'cgai');

        $this->assertSame('CG-AI', $parsed['service_label']);
        $this->assertSame('Dashboard | Oxaam', $parsed['page_title']);
        $this->assertNotEmpty($parsed['dashboard_name']);
        $this->assertMatchesRegularExpression('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $parsed['account_email']);
        $this->assertStringStartsWith('Oxaam', $parsed['account_password']);
        $this->assertMatchesRegularExpression('#^https://www\.oxaam\.com/cgcode\d+\.php$#', $parsed['code_url']);
    }

    public function test_it_can_fall_back_to_service_link_and_copy_buttons_when_the_exact_heading_markup_changes(): void
    {
        $parser = app(OxaamDashboardParser::class);

        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <title>Dashboard | Oxaam</title>
</head>
<body>
    <div class="page-title">Welcome, Demo User</div>
    <section class="perk-card">
        <div class="perk-body">
            <a href="https://www.oxaam.com/official.php?id=cgai">Official Website</a>
            <div class="cred-block">
                <button class="copy-btn" data-copy="fallback@mapoba.com">Copy email</button>
                <button class="copy-btn" data-copy="Oxaam123456#">Copy password</button>
            </div>
            <a href="https://www.oxaam.com/cgcode99.php">https://www.oxaam.com/cgcode99.php</a>
        </div>
    </section>
</body>
</html>
HTML;

        $parsed = $parser->parseServiceCredential($html, 'cgai');

        $this->assertSame('CG-AI', $parsed['service_label']);
        $this->assertSame('fallback@mapoba.com', $parsed['account_email']);
        $this->assertSame('Oxaam123456#', $parsed['account_password']);
        $this->assertSame('https://www.oxaam.com/cgcode99.php', $parsed['code_url']);
    }
}
