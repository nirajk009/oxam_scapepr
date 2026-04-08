<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;

class OxaamDashboardParser
{
    public function parseServiceCredential(string $html, string $serviceKey = 'cgai'): array
    {
        $service = $this->serviceDefinition($serviceKey);
        $document = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);
        $section = $this->findServiceSection($xpath, $service);

        if (! $section instanceof DOMElement) {
            $serviceLabel = $service['label'];
            throw new RuntimeException("Could not find the {$serviceLabel} section on the dashboard.");
        }

        [$email, $password] = $this->extractCredentialPair($xpath, $section);
        $codeUrl = $this->extractCodeUrl($xpath, $section, $service);

        if (blank($email) || blank($password) || blank($codeUrl)) {
            $serviceLabel = $service['label'];
            throw new RuntimeException("The {$serviceLabel} section was found, but one or more credential fields were missing.");
        }

        return [
            'service_key' => $serviceKey,
            'service_label' => $service['label'],
            'service_title' => $this->firstText($xpath, './/h3', $section) ?: $service['label'],
            'page_title' => $this->firstText($xpath, '//title'),
            'dashboard_name' => $this->extractDashboardName($xpath),
            'account_email' => $email,
            'account_password' => $password,
            'code_url' => $codeUrl,
        ];
    }

    protected function findServiceSection(DOMXPath $xpath, array $service): ?DOMElement
    {
        $queries = [];

        foreach ($service['aliases'] as $alias) {
            $queries[] = sprintf(
                "//details[contains(@class, 'perk-accordion')][.//strong[contains(normalize-space(.), \"%s\")]]",
                addslashes($alias)
            );
            $queries[] = sprintf(
                "//details[contains(@class, 'perk-accordion')][contains(normalize-space(.), \"%s\")]",
                addslashes($alias)
            );
        }

        $queries[] = sprintf(
            "//details[contains(@class, 'perk-accordion')][.//a[contains(@href, \"%s\")]]",
            addslashes($service['official_hint'])
        );
        $queries[] = sprintf(
            "//div[contains(@class, 'perk-body')][.//a[contains(@href, \"%s\")]]",
            addslashes($service['official_hint'])
        );
        $queries[] = sprintf(
            "//*[self::details or self::div or self::section or self::article][count(.//button[contains(@class, 'copy-btn')]) >= 2][.//a[contains(@href, \"%s\")]]",
            addslashes($service['code_hint'])
        );

        foreach ($queries as $query) {
            $section = $xpath->query($query)->item(0);

            if ($section instanceof DOMElement) {
                return $section;
            }
        }

        return null;
    }

    protected function extractCredentialPair(DOMXPath $xpath, DOMElement $section): array
    {
        $email = null;
        $password = null;

        foreach ($xpath->query(".//div[contains(@class, 'cred-row')]", $section) as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $label = Str::lower($this->normalizeText($row->textContent));
            $copyValue = $this->firstAttribute($xpath, ".//button[contains(@class, 'copy-btn')]", 'data-copy', $row);

            if (blank($copyValue)) {
                continue;
            }

            if ($email === null && str_contains($label, 'email')) {
                $email = $copyValue;
            }

            if ($password === null && str_contains($label, 'password')) {
                $password = $copyValue;
            }
        }

        if (filled($email) && filled($password)) {
            return [$email, $password];
        }

        $copyValues = collect();

        foreach ($xpath->query(".//button[contains(@class, 'copy-btn')]", $section) as $button) {
            if (! $button instanceof DOMElement || ! $button->hasAttribute('data-copy')) {
                continue;
            }

            $copyValues->push($this->normalizeText($button->getAttribute('data-copy')));
        }

        $email ??= $copyValues->first(fn (?string $value) => filled($value) && filter_var($value, FILTER_VALIDATE_EMAIL));
        $password ??= $copyValues->first(
            fn (?string $value) => filled($value) && $value !== $email && ! filter_var($value, FILTER_VALIDATE_EMAIL)
        );

        return [$email, $password];
    }

    protected function extractCodeUrl(DOMXPath $xpath, DOMElement $section, array $service): ?string
    {
        $queries = [
            sprintf(".//a[contains(@href, \"%s\")]", addslashes($service['code_hint'])),
            ".//p[contains(@class, 'cred-block')]//a",
            ".//a[contains(@href, 'code')]",
        ];

        foreach ($queries as $query) {
            $url = $this->firstAttribute($xpath, $query, 'href', $section);

            if (filled($url)) {
                return $url;
            }
        }

        return null;
    }

    protected function extractDashboardName(DOMXPath $xpath): ?string
    {
        $title = $this->firstText($xpath, "//div[contains(@class, 'page-title')]");

        if (blank($title)) {
            return null;
        }

        return (string) Str::of($title)
            ->replace('Welcome,', '')
            ->replace('👋', '')
            ->squish();
    }

    protected function firstText(DOMXPath $xpath, string $query, ?DOMElement $context = null): ?string
    {
        $node = $xpath->query($query, $context)->item(0);

        if (! $node) {
            return null;
        }

        return $this->normalizeText($node->textContent);
    }

    protected function firstAttribute(
        DOMXPath $xpath,
        string $query,
        string $attribute,
        ?DOMElement $context = null
    ): ?string {
        $node = $xpath->query($query, $context)->item(0);

        if (! $node instanceof DOMElement || ! $node->hasAttribute($attribute)) {
            return null;
        }

        return $this->normalizeText($node->getAttribute($attribute));
    }

    protected function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $squished = preg_replace('/\s+/u', ' ', $decoded);

        return $squished === null ? null : trim($squished);
    }

    protected function serviceDefinition(string $serviceKey): array
    {
        return match (Str::lower($serviceKey)) {
            'cgai' => [
                'label' => 'CG-AI',
                'aliases' => ['CG-AI', 'CG AI', 'Free CG-AI'],
                'official_hint' => 'official.php?id=cgai',
                'code_hint' => 'cgcode',
            ],
            default => [
                'label' => Str::upper($serviceKey),
                'aliases' => [Str::upper($serviceKey)],
                'official_hint' => 'official.php?id='.Str::lower($serviceKey),
                'code_hint' => Str::lower($serviceKey).'code',
            ],
        };
    }
}
