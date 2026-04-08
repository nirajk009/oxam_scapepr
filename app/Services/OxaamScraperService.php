<?php

namespace App\Services;

use App\Models\OxaamCredential;
use App\Models\OxaamRun;
use App\Models\OxaamSession;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OxaamScraperService
{
    public function __construct(
        protected OxaamDashboardParser $parser,
    ) {
    }

    public function run(?string $serviceKey = null): OxaamRun
    {
        $serviceKey ??= config('services.oxaam.service_key', 'cgai');

        $startedAt = now();
        $started = microtime(true);
        $selectedSession = $this->selectReusableSession();
        $session = $selectedSession;
        $response = null;
        $debugHtmlPath = null;

        try {
            [$session, $response, $jar] = $this->resolveDashboardResponse($selectedSession);

            try {
                $parsed = $this->parser->parseServiceCredential($response->body(), $serviceKey);
            } catch (Throwable $exception) {
                $debugHtmlPath = $this->storeDebugHtml($response->body(), $serviceKey, $session, 'parser-miss');

                if (
                    $selectedSession instanceof OxaamSession
                    && $session instanceof OxaamSession
                    && $selectedSession->is($session)
                ) {
                    $session->markInvalid($this->appendDebugPath(
                        'Stored session was missing the requested service block.',
                        $debugHtmlPath
                    ));

                    [$session, $response, $jar] = $this->registerFreshSession();

                    try {
                        $parsed = $this->parser->parseServiceCredential($response->body(), $serviceKey);
                    } catch (Throwable $freshException) {
                        $debugHtmlPath = $this->storeDebugHtml($response->body(), $serviceKey, $session, 'fresh-parser-miss');
                        $session->markInvalid($this->appendDebugPath($freshException->getMessage(), $debugHtmlPath));

                        throw new RuntimeException(
                            'The saved session was invalidated because its dashboard no longer exposed the requested service block. '
                            .$this->appendDebugPath($freshException->getMessage(), $debugHtmlPath),
                            previous: $freshException
                        );
                    }
                } else {
                    throw new RuntimeException(
                        $this->appendDebugPath($exception->getMessage(), $debugHtmlPath),
                        previous: $exception
                    );
                }
            }

            $session->forceFill([
                'cookie_value' => $this->cookieValue($jar),
                'cookies' => $this->serializeCookies($jar),
                'uses_count' => $session->uses_count + 1,
                'last_validated_at' => now(),
                'last_used_at' => now(),
                'last_error' => null,
                'is_active' => true,
            ])->save();

            $run = OxaamRun::create([
                'oxaam_session_id' => $session->id,
                'target_service' => $serviceKey,
                'status' => 'success',
                'http_status' => $response->status(),
                'duration_ms' => $this->durationInMs($started),
                'session_uses_after' => $session->uses_count,
                'service_label' => $parsed['service_label'],
                'page_title' => $parsed['page_title'],
                'dashboard_name' => $parsed['dashboard_name'],
                'account_email' => $parsed['account_email'],
                'account_password' => $parsed['account_password'],
                'code_url' => $parsed['code_url'],
                'report' => [
                    'service_title' => $parsed['service_title'],
                    'scraper_account' => [
                        'name' => $session->registration_name,
                        'email' => $session->registration_email,
                        'phone' => $session->registration_phone,
                    ],
                    'debug_html_path' => $debugHtmlPath,
                ],
                'scraped_at' => $startedAt,
            ]);

            $this->syncCredentialLedger($run, $session);

            return $run->load('session');
        } catch (Throwable $exception) {
            if ($debugHtmlPath === null && $response?->body()) {
                $debugHtmlPath = $this->storeDebugHtml($response->body(), $serviceKey, $session, 'failure');
            }

            $errorMessage = $this->appendDebugPath($exception->getMessage(), $debugHtmlPath);

            if ($session instanceof OxaamSession) {
                $session->forceFill([
                    'last_error' => $errorMessage,
                ])->save();
            }

            return OxaamRun::create([
                'oxaam_session_id' => $session?->id,
                'target_service' => $serviceKey,
                'status' => 'failed',
                'http_status' => $response?->status(),
                'duration_ms' => $this->durationInMs($started),
                'session_uses_after' => $session?->uses_count,
                'service_label' => Str::upper($serviceKey),
                'page_title' => $response ? $this->extractTitle($response->body()) : null,
                'error_message' => $errorMessage,
                'report' => $debugHtmlPath ? ['debug_html_path' => $debugHtmlPath] : null,
                'scraped_at' => $startedAt,
            ])->load('session');
        }
    }

    protected function resolveDashboardResponse(?OxaamSession $session): array
    {
        if ($session instanceof OxaamSession) {
            [$response, $jar] = $this->fetchDashboard($session);

            if ($this->looksLikeDashboard($response->body())) {
                return [$session, $response, $jar];
            }

            [$loginResponse, $loginJar] = $this->loginWithStoredAccount($session);

            if ($this->looksLikeDashboard($loginResponse->body())) {
                return [$session, $loginResponse, $loginJar];
            }

            $session->markInvalid('Stored session and login retry both failed.');
        }

        return $this->registerFreshSession();
    }

    protected function selectReusableSession(): ?OxaamSession
    {
        return OxaamSession::query()
            ->where('is_active', true)
            ->whereColumn('uses_count', '<', 'max_uses')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function registerFreshSession(): array
    {
        $identity = $this->makeIdentity();
        $jar = new CookieJar();

        [$response, $jar] = $this->sendRequest(
            $jar,
            'POST',
            $this->homepageUrl(),
            [
                'name' => $identity['name'],
                'email' => $identity['email'],
                'phone' => $identity['phone'],
                'password' => $identity['password'],
                'country' => 'India',
            ],
            true,
            [
                'Referer' => $this->homepageUrl(),
                'Origin' => $this->baseUrl(),
            ]
        );

        if (! $this->looksLikeDashboard($response->body())) {
            [$response, $jar] = $this->sendRequest(
                $jar,
                'POST',
                $this->loginUrl(),
                [
                    'email' => $identity['email'],
                    'password' => $identity['password'],
                ],
                true,
                [
                    'Referer' => $this->loginUrl(),
                    'Origin' => $this->baseUrl(),
                ]
            );
        }

        if (! $this->looksLikeDashboard($response->body())) {
            throw new RuntimeException(
                'A fresh Oxaam account was created, but the dashboard never loaded. '
                .$this->failureHint($response->body())
            );
        }

        $session = OxaamSession::create([
            'registration_name' => $identity['name'],
            'registration_email' => $identity['email'],
            'registration_phone' => $identity['phone'],
            'registration_password' => $identity['password'],
            'cookie_name' => 'PHPSESSID',
            'cookie_value' => $this->cookieValue($jar),
            'cookies' => $this->serializeCookies($jar),
            'max_uses' => config('services.oxaam.max_session_uses', 300),
            'last_registered_at' => now(),
            'last_validated_at' => now(),
            'last_error' => null,
            'is_active' => true,
        ]);

        return [$session, $response, $jar];
    }

    protected function fetchDashboard(OxaamSession $session): array
    {
        $jar = $this->cookieJarForSession($session);

        return $this->sendRequest($jar, 'GET', $this->dashboardUrl(), [], false, [
            'Referer' => $this->homepageUrl(),
        ]);
    }

    protected function loginWithStoredAccount(OxaamSession $session): array
    {
        $jar = $this->cookieJarForSession($session);

        return $this->sendRequest($jar, 'POST', $this->loginUrl(), [
            'email' => $session->registration_email,
            'password' => $session->registration_password,
        ], true, [
            'Referer' => $this->loginUrl(),
            'Origin' => $this->baseUrl(),
        ]);
    }

    protected function looksLikeDashboard(string $html): bool
    {
        $normalized = Str::lower($html);

        if (str_contains($normalized, '<title>login | oxaam</title>')) {
            return false;
        }

        return str_contains($normalized, '<title>dashboard | oxaam</title>')
            || (
                str_contains($normalized, 'click here to activate')
                && str_contains($normalized, 'perk-accordion')
            );
    }

    protected function syncCredentialLedger(OxaamRun $run, OxaamSession $session): void
    {
        $credential = OxaamCredential::query()->firstOrNew([
            'target_service' => $run->target_service,
            'account_email' => $run->account_email,
            'account_password' => $run->account_password,
            'code_url' => $run->code_url,
        ]);

        if (! $credential->exists) {
            $credential->fill([
                'service_label' => $run->service_label,
                'first_seen_run_id' => $run->id,
                'last_seen_run_id' => $run->id,
                'last_session_id' => $session->id,
                'seen_count' => 1,
                'first_seen_at' => $run->scraped_at,
                'last_seen_at' => $run->scraped_at,
            ]);
        } else {
            $credential->fill([
                'service_label' => $run->service_label,
                'last_seen_run_id' => $run->id,
                'last_session_id' => $session->id,
                'seen_count' => $credential->seen_count + 1,
                'last_seen_at' => $run->scraped_at,
            ]);
        }

        $credential->save();
    }

    protected function cookieJarForSession(OxaamSession $session): CookieJar
    {
        $cookies = $session->cookies;

        if (blank($cookies) && filled($session->cookie_value)) {
            $cookies = [[
                'Name' => $session->cookie_name ?: 'PHPSESSID',
                'Value' => $session->cookie_value,
                'Domain' => parse_url($this->baseUrl(), PHP_URL_HOST),
                'Path' => '/',
                'Secure' => str_starts_with($this->baseUrl(), 'https://'),
            ]];
        }

        return new CookieJar(false, $cookies ?: []);
    }

    protected function sendRequest(
        CookieJar $jar,
        string $method,
        string $url,
        array $payload = [],
        bool $asForm = false,
        array $headers = []
    ): array {
        if ($this->useCurlBinaryTransport()) {
            return $this->sendRequestWithCurlBinary($jar, $method, $url, $payload, $headers);
        }

        if ($this->useNativeCurlTransport()) {
            return $this->sendRequestWithNativeCurl($jar, $method, $url, $payload, $headers);
        }

        $request = $this->client($jar)->withHeaders($headers);

        if ($asForm) {
            $request = $request->asForm();
        }

        $response = match (Str::upper($method)) {
            'POST' => $request->post($url, $payload),
            default => $request->get($url, $payload),
        };

        return [$response, $jar];
    }

    protected function sendRequestWithCurlBinary(
        CookieJar $jar,
        string $method,
        string $url,
        array $payload = [],
        array $headers = []
    ): array {
        $cookieFile = tempnam(sys_get_temp_dir(), 'oxaam_cookie_');
        $bodyFile = tempnam(sys_get_temp_dir(), 'oxaam_body_');
        $timeout = (int) config('services.oxaam.timeout', 20);

        if (! $cookieFile || ! $bodyFile) {
            throw new RuntimeException('Could not create temporary files for curl.');
        }

        $this->writeCookieJarToCurlFile($jar, $cookieFile);

        $result = $this->runCurlBinaryAttempts($method, $url, $cookieFile, $bodyFile, $payload, $headers, $timeout);

        $body = file_get_contents($bodyFile) ?: '';
        $status = (int) trim($result->output());
        $nextJar = $this->cookieJarFromCurlFile($cookieFile);

        @unlink($cookieFile);
        @unlink($bodyFile);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'curl.exe failed while contacting Oxaam.');
        }

        return [new Response(new PsrResponse($status, [], $body)), $nextJar];
    }

    protected function sendRequestWithNativeCurl(
        CookieJar $jar,
        string $method,
        string $url,
        array $payload = [],
        array $headers = []
    ): array {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('The PHP cURL extension is not available on this host.');
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'oxaam_cookie_');
        $timeout = (int) config('services.oxaam.timeout', 20);

        if (! $cookieFile) {
            throw new RuntimeException('Could not create a temporary cookie file for native cURL.');
        }

        $this->writeCookieJarToCurlFile($jar, $cookieFile);

        [$status, $body, $errorOutput] = $this->runNativeCurlAttempts(
            $method,
            $url,
            $cookieFile,
            $payload,
            $headers,
            $timeout,
        );

        $nextJar = $this->cookieJarFromCurlFile($cookieFile);

        @unlink($cookieFile);

        if ($errorOutput !== null) {
            throw new RuntimeException($errorOutput);
        }

        return [new Response(new PsrResponse($status, [], $body)), $nextJar];
    }

    protected function runCurlBinaryAttempts(
        string $method,
        string $url,
        string $cookieFile,
        string $bodyFile,
        array $payload,
        array $headers,
        int $timeout,
    ) {
        $attempts = [
            $this->buildCurlCommand($method, $url, $cookieFile, $bodyFile, $payload, $headers),
            $this->buildCurlCommand($method, $url, $cookieFile, $bodyFile, $payload, $headers),
        ];

        foreach ($this->resolveEntriesForCurl($url) as $resolveEntry) {
            $attempts[] = $this->buildCurlCommand(
                $method,
                $url,
                $cookieFile,
                $bodyFile,
                $payload,
                $headers,
                $resolveEntry,
            );
        }

        $lastResult = null;
        $errorMessages = [];

        foreach ($attempts as $index => $command) {
            if ($index > 0) {
                usleep(250000);
            }

            $lastResult = Process::timeout($timeout)->run($command);

            if ($lastResult->successful()) {
                return $lastResult;
            }

            $error = trim($lastResult->errorOutput());

            if ($error !== '') {
                $errorMessages[] = $error;
            }
        }

        if ($lastResult && ! empty($errorMessages)) {
            $message = collect($errorMessages)->unique()->implode(' | ');

            throw new RuntimeException($message);
        }

        return $lastResult;
    }

    protected function runNativeCurlAttempts(
        string $method,
        string $url,
        string $cookieFile,
        array $payload,
        array $headers,
        int $timeout,
    ): array {
        $resolveEntries = array_merge([null, null], $this->resolveEntriesForCurl($url));
        $errorMessages = [];

        foreach ($resolveEntries as $index => $resolveEntry) {
            if ($index > 0) {
                usleep(250000);
            }

            [$status, $body, $error] = $this->performNativeCurlRequest(
                $method,
                $url,
                $cookieFile,
                $payload,
                $headers,
                $timeout,
                $resolveEntry,
            );

            if ($error === null) {
                return [$status, $body, null];
            }

            $errorMessages[] = $error;
        }

        $message = collect($errorMessages)->filter()->unique()->implode(' | ');

        return [0, '', $message !== '' ? $message : 'Native PHP cURL failed while contacting Oxaam.'];
    }

    protected function performNativeCurlRequest(
        string $method,
        string $url,
        string $cookieFile,
        array $payload,
        array $headers,
        int $timeout,
        ?string $resolveEntry = null,
    ): array {
        $handle = curl_init();

        if ($handle === false) {
            return [0, '', 'Could not initialize native PHP cURL.'];
        }

        $headerLines = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
        ];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $verifySsl = filter_var(config('services.oxaam.verify_ssl', false), FILTER_VALIDATE_BOOL);
        $postFields = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 10),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_ENCODING => '',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => (string) config('services.oxaam.user_agent'),
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ];

        if (Str::upper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $postFields;
        }

        if ($resolveEntry !== null) {
            $options[CURLOPT_RESOLVE] = [$resolveEntry];
        }

        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $errorNumber = curl_errno($handle);
        $errorMessage = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        if ($errorNumber !== 0) {
            return [0, '', sprintf('cURL error %d: %s', $errorNumber, $errorMessage)];
        }

        return [$status, is_string($body) ? $body : '', null];
    }

    protected function client(CookieJar $jar): PendingRequest
    {
        $timeout = (int) config('services.oxaam.timeout', 20);

        return Http::timeout($timeout)
            ->connectTimeout($timeout)
            ->retry(1, 250, throw: false)
            ->withHeaders([
                'User-Agent' => config('services.oxaam.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
            ])
            ->withOptions([
                'cookies' => $jar,
                'allow_redirects' => true,
                'verify' => filter_var(config('services.oxaam.verify_ssl', false), FILTER_VALIDATE_BOOL),
                'curl' => [
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
    }

    protected function buildCurlCommand(
        string $method,
        string $url,
        string $cookieFile,
        string $bodyFile,
        array $form = [],
        array $headers = [],
        ?string $resolveEntry = null,
    ): array {
        $command = [
            (string) config('services.oxaam.curl_binary', 'curl.exe'),
            '-sS',
            '-L',
            '--ipv4',
            '--connect-timeout',
            '10',
            '--retry',
            '2',
            '--retry-delay',
            '1',
            '--retry-all-errors',
            '--retry-connrefused',
            '-A',
            (string) config('services.oxaam.user_agent'),
            '-H',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            '-c',
            $cookieFile,
            '-b',
            $cookieFile,
            '-o',
            $bodyFile,
            '-w',
            '%{http_code}',
        ];

        if (! filter_var(config('services.oxaam.verify_ssl', false), FILTER_VALIDATE_BOOL)) {
            $command[] = '-k';
        }

        if ($resolveEntry !== null) {
            $command[] = '--resolve';
            $command[] = $resolveEntry;
        }

        foreach ($headers as $name => $value) {
            $command[] = '-H';
            $command[] = $name.': '.$value;
        }

        if (Str::upper($method) === 'POST') {
            $command[] = '-X';
            $command[] = 'POST';

            foreach ($form as $key => $value) {
                $command[] = '--data-urlencode';
                $command[] = $key.'='.$value;
            }
        }

        $command[] = $url;

        return $command;
    }

    protected function writeCookieJarToCurlFile(CookieJar $jar, string $path): void
    {
        $lines = [
            '# Netscape HTTP Cookie File',
            '# https://curl.se/docs/http-cookies.html',
            '# This file was generated by the Oxaam scraper.',
            '',
        ];

        foreach ($jar->toArray() as $cookie) {
            $lines[] = implode("\t", [
                $cookie['Domain'] ?? parse_url($this->baseUrl(), PHP_URL_HOST),
                'FALSE',
                $cookie['Path'] ?? '/',
                ! empty($cookie['Secure']) ? 'TRUE' : 'FALSE',
                isset($cookie['Expires']) && $cookie['Expires'] !== null ? (string) $cookie['Expires'] : '0',
                $cookie['Name'] ?? '',
                $cookie['Value'] ?? '',
            ]);
        }

        file_put_contents($path, implode(PHP_EOL, $lines));
    }

    protected function cookieJarFromCurlFile(string $path): CookieJar
    {
        $cookies = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 7) {
                continue;
            }

            [$domain, $flag, $cookiePath, $secure, $expires, $name, $value] = $parts;

            $cookies[] = [
                'Name' => $name,
                'Value' => $value,
                'Domain' => $domain,
                'Path' => $cookiePath,
                'Secure' => strtoupper($secure) === 'TRUE',
                'Expires' => is_numeric($expires) ? (int) $expires : null,
            ];
        }

        return new CookieJar(false, $cookies);
    }

    protected function resolveEntriesForCurl(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        if (! is_string($host) || $host === '') {
            return [];
        }

        $records = dns_get_record($host, DNS_A);
        $ips = collect($records)->pluck('ip')->filter()->values()->all();

        if ($ips === []) {
            $fallback = gethostbynamel($host);
            $ips = is_array($fallback) ? array_values(array_filter($fallback)) : [];
        }

        return collect($ips)
            ->filter(fn ($ip) => is_string($ip) && $ip !== '')
            ->map(fn ($ip) => sprintf('%s:%d:%s', $host, $port, $ip))
            ->values()
            ->all();
    }

    protected function serializeCookies(CookieJar $jar): array
    {
        return collect($jar->toArray())
            ->map(fn (array $cookie) => Arr::only(
                $cookie,
                ['Name', 'Value', 'Domain', 'Path', 'Max-Age', 'Expires', 'Secure', 'Discard', 'HttpOnly']
            ))
            ->values()
            ->all();
    }

    protected function cookieValue(CookieJar $jar): ?string
    {
        foreach ($jar->toArray() as $cookie) {
            if (($cookie['Name'] ?? null) === 'PHPSESSID') {
                return $cookie['Value'] ?? null;
            }
        }

        return null;
    }

    protected function makeIdentity(): array
    {
        $firstNames = ['Aarav', 'Ivy', 'Rohan', 'Maya', 'Neel', 'Tara', 'Kabir', 'Sana'];
        $lastNames = ['Patel', 'Shaw', 'Das', 'Reed', 'Kapoor', 'Nair', 'Stone', 'Iyer'];

        $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
        $stamp = now()->format('YmdHis').random_int(100, 999);

        return [
            'name' => $name,
            'email' => 'oxaamscraper'.$stamp.'@mailinator.com',
            'phone' => '9'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'password' => 'Oxaam'.random_int(100000, 999999).'#',
        ];
    }

    protected function failureHint(string $html): string
    {
        if ($this->looksLikeCloudflareChallenge($html)) {
            return 'Oxaam returned a Cloudflare challenge page to the Laravel/PHP HTTP client on this host, so the dashboard never became reachable.';
        }

        $snippet = Str::of(strip_tags($html))->squish()->limit(180);

        return filled($snippet) ? 'Last response snippet: '.$snippet : 'No useful HTML came back from Oxaam.';
    }

    protected function looksLikeCloudflareChallenge(string $html): bool
    {
        $normalized = Str::lower($html);

        return str_contains($normalized, 'just a moment')
            && (
                str_contains($normalized, 'cf-browser-verification')
                || str_contains($normalized, 'challenge-platform')
                || str_contains($normalized, 'cloudflare')
            );
    }

    protected function extractTitle(string $html): ?string
    {
        if (! preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    protected function durationInMs(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    protected function useCurlBinaryTransport(): bool
    {
        return (string) config('services.oxaam.transport', 'curl_binary') === 'curl_binary';
    }

    protected function useNativeCurlTransport(): bool
    {
        return (string) config('services.oxaam.transport') === 'native_curl';
    }

    protected function storeDebugHtml(?string $html, string $serviceKey, ?OxaamSession $session, string $reason): ?string
    {
        if (blank($html)) {
            return null;
        }

        $directory = storage_path('app/debug/oxaam');
        File::ensureDirectoryExists($directory);

        $fileName = sprintf(
            '%s-%s-session-%s-%s.html',
            now()->format('Ymd-His'),
            Str::slug($reason),
            $session?->id ?? 'none',
            Str::slug($serviceKey)
        );
        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        file_put_contents($path, $html);

        return $path;
    }

    protected function appendDebugPath(string $message, ?string $path): string
    {
        if (blank($path) || str_contains($message, $path)) {
            return $message;
        }

        return $message.' Debug HTML saved to: '.$path;
    }

    protected function baseUrl(): string
    {
        return rtrim((string) config('services.oxaam.base_url'), '/');
    }

    protected function homepageUrl(): string
    {
        return $this->baseUrl().'/'.ltrim((string) config('services.oxaam.homepage_path', '/'), '/');
    }

    protected function dashboardUrl(): string
    {
        return $this->baseUrl().'/'.ltrim((string) config('services.oxaam.dashboard_path', '/dashboard.php'), '/');
    }

    protected function loginUrl(): string
    {
        return $this->baseUrl().'/'.ltrim((string) config('services.oxaam.login_path', '/login.php'), '/');
    }
}
