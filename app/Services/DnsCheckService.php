<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DnsCheckService
{
    // Common DKIM selectors to probe
    private array $dkimSelectors = [
        'default', 'google', 'selector1', 'selector2',
        'k1', 'k2', 'dkim', 'mail', 'email', 's1', 's2',
        'smtp', 'mimecast', 'proofpoint', 'mandrill',
        'sendgrid', 'mailchimp', 'mg', 'zoho',
    ];

    private int $cacheTtl = 300; // 5 minutes

    public function check(string $domain): array
    {
        return Cache::remember("dns_check_{$domain}", $this->cacheTtl, function () use ($domain) {
            return [
                'spf'   => $this->checkSpf($domain),
                'dkim'  => $this->checkDkim($domain),
                'dmarc' => $this->checkDmarc($domain),
            ];
        });
    }

    // -------------------------------------------------------------------------
    // SPF
    // -------------------------------------------------------------------------

    public function checkSpf(string $domain): array
    {
        try {
            $records = @dns_get_record($domain, DNS_TXT);
            if ($records === false) {
                return $this->spfResult(false, null, 'DNS lookup failed');
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? $record['entries'][0] ?? '';
                if (str_starts_with(strtolower($txt), 'v=spf1')) {
                    $quality = $this->evaluateSpfQuality($txt);
                    return $this->spfResult(true, $txt, null, $quality);
                }
            }

            return $this->spfResult(false, null, 'No SPF record found');
        } catch (\Throwable $e) {
            Log::warning("SPF check failed for {$domain}: " . $e->getMessage());
            return $this->spfResult(false, null, $e->getMessage());
        }
    }

    private function evaluateSpfQuality(string $spf): string
    {
        $lower = strtolower($spf);
        // "+all" allows all — very permissive and bad
        if (str_contains($lower, '+all')) {
            return 'permissive';
        }
        // "?all" is neutral — not a real enforcement
        if (str_contains($lower, '?all')) {
            return 'weak';
        }
        // "~all" softfail is acceptable but not ideal
        if (str_contains($lower, '~all')) {
            return 'softfail';
        }
        // "-all" is the correct strict enforcement
        if (str_contains($lower, '-all')) {
            return 'strict';
        }
        return 'unknown';
    }

    private function spfResult(bool $found, ?string $raw, ?string $error, string $quality = 'none'): array
    {
        return [
            'found'   => $found,
            'raw'     => $raw,
            'quality' => $quality,
            'error'   => $error,
        ];
    }

    // -------------------------------------------------------------------------
    // DKIM
    // -------------------------------------------------------------------------

    public function checkDkim(string $domain): array
    {
        $found    = false;
        $selector = null;
        $raw      = null;

        foreach ($this->dkimSelectors as $sel) {
            $host = "{$sel}._domainkey.{$domain}";
            try {
                $records = @dns_get_record($host, DNS_TXT);
                if ($records === false || empty($records)) {
                    continue;
                }
                foreach ($records as $record) {
                    $txt = $record['txt'] ?? $record['entries'][0] ?? '';
                    if (str_contains(strtolower($txt), 'v=dkim1') || str_contains(strtolower($txt), 'p=')) {
                        $found    = true;
                        $selector = $sel;
                        $raw      = $txt;
                        break 2;
                    }
                }
            } catch (\Throwable $e) {
                // Continue probing other selectors
                continue;
            }
        }

        $quality = 'none';
        if ($found && $raw) {
            // Check if the key is present (p= field should not be empty)
            if (preg_match('/p=([^;]+)/i', $raw, $m) && strlen(trim($m[1])) > 10) {
                $quality = 'valid';
            } else {
                $quality = 'partial'; // Record found but key revoked or empty
            }
        }

        return [
            'found'    => $found,
            'selector' => $selector,
            'raw'      => $raw,
            'quality'  => $quality,
            'error'    => $found ? null : 'No DKIM selector detected across common selector names',
        ];
    }

    // -------------------------------------------------------------------------
    // DMARC
    // -------------------------------------------------------------------------

    public function checkDmarc(string $domain): array
    {
        $host = "_dmarc.{$domain}";
        try {
            $records = @dns_get_record($host, DNS_TXT);
            if ($records === false || empty($records)) {
                return $this->dmarcResult(false, null, null, 'No DMARC record found');
            }

            foreach ($records as $record) {
                $txt = $record['txt'] ?? $record['entries'][0] ?? '';
                if (str_starts_with(strtolower($txt), 'v=dmarc1')) {
                    $policy = $this->extractDmarcPolicy($txt);
                    return $this->dmarcResult(true, $txt, $policy, null);
                }
            }

            return $this->dmarcResult(false, null, null, 'No DMARC record found');
        } catch (\Throwable $e) {
            Log::warning("DMARC check failed for {$domain}: " . $e->getMessage());
            return $this->dmarcResult(false, null, null, $e->getMessage());
        }
    }

    private function extractDmarcPolicy(string $dmarc): string
    {
        if (preg_match('/p=(none|quarantine|reject)/i', $dmarc, $m)) {
            return strtolower($m[1]);
        }
        return 'none';
    }

    private function dmarcResult(bool $found, ?string $raw, ?string $policy, ?string $error): array
    {
        return [
            'found'  => $found,
            'raw'    => $raw,
            'policy' => $policy,
            'error'  => $error,
        ];
    }

    // -------------------------------------------------------------------------
    // Alignment summary
    // -------------------------------------------------------------------------

    public function alignmentNotes(array $spf, array $dkim, array $dmarc): string
    {
        $notes = [];

        if (!$spf['found']) {
            $notes[] = 'SPF record missing';
        } elseif ($spf['quality'] === 'permissive') {
            $notes[] = 'SPF uses +all (dangerously permissive)';
        } elseif ($spf['quality'] === 'weak') {
            $notes[] = 'SPF uses ?all (neutral, no enforcement)';
        }

        if (!$dkim['found']) {
            $notes[] = 'DKIM not detected';
        } elseif ($dkim['quality'] === 'partial') {
            $notes[] = 'DKIM key appears revoked';
        }

        if (!$dmarc['found']) {
            $notes[] = 'DMARC record missing';
        } elseif ($dmarc['policy'] === 'none') {
            $notes[] = 'DMARC policy is p=none (monitor only, no enforcement)';
        } elseif ($dmarc['policy'] === 'quarantine') {
            $notes[] = 'DMARC policy is p=quarantine (moderate enforcement)';
        }

        return empty($notes) ? 'All authentication controls appear aligned' : implode('; ', $notes);
    }
}
