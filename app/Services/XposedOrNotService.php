<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XposedOrNotService
{
    private string $apiKey;
    private string $baseUrl       = 'https://plus-api.xposedornot.com';
    private string $domainBaseUrl = 'https://plus-api-test.xposedornot.com';

    public function __construct()
    {
        $this->apiKey = config('services.xposedornot.key', '');
    }

    /**
     * GET /v3/check-email/{email}?detailed=true
     * Returns normalized breach data for an email address.
     */
    public function analyzeEmail(string $email): array
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get("{$this->baseUrl}/v3/check-email/{$email}", [
                'detailed' => 'true',
            ]);

            if ($response->status() === 404) {
                return $this->cleanRecord();
            }

            if ($response->failed()) {
                Log::warning('XposedOrNot email API error', [
                    'email'  => $this->maskEmail($email),
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return $this->unavailable('API returned HTTP ' . $response->status());
            }

            $data = $response->json();

            // Clean record — API returns success with empty breaches array
            if (($data['status'] ?? '') === 'success' && empty($data['breaches'])) {
                return $this->cleanRecord();
            }

            return $this->normalizeEmailResponse($data);
        } catch (\Throwable $e) {
            Log::error('XposedOrNot email request failed', [
                'email'   => $this->maskEmail($email),
                'message' => $e->getMessage(),
            ]);
            return $this->unavailable($e->getMessage());
        }
    }

    /**
     * GET /v2/partner/domain-summary?domain={domain}&details=true
     * Auto-onboards the domain if not yet approved, then returns normalized data.
     */
    public function analyzeDomain(string $domain): array
    {
        try {
            $response = $this->fetchDomainSummary($domain);

            // Domain not yet registered — try to onboard it, then retry for up to 15 seconds
            if ($response->status() === 403) {
                Log::info('XposedOrNot auto-onboarding domain', ['domain' => $domain]);
                $onboard = $this->onboardDomain($domain);

                // Quota exceeded — skip domain section entirely rather than blocking
                if (in_array($onboard['status'] ?? 0, [402, 403, 429]) ||
                    str_contains(strtolower(json_encode($onboard['body'] ?? '')), 'limit')) {
                    Log::warning('XposedOrNot domain quota reached, skipping domain analysis', ['domain' => $domain]);
                    return $this->quotaExceeded();
                }

                $response = $this->retryUntilReady($domain, retries: 5, intervalSeconds: 3);
            }

            if ($response === null || $response->failed()) {
                $status = $response?->status();
                Log::warning('XposedOrNot domain API error', ['domain' => $domain, 'status' => $status]);
                // Still processing after retries
                if ($status === 404) {
                    return $this->pendingDomain('Domain data is still being indexed. Check back in a few minutes.');
                }
                return ['available' => false, 'pending' => false, 'found' => false, 'top_breaches' => [], 'breach_count' => 0, 'total_exposed' => 0];
            }

            return $this->normalizeDomainResponse($response->json());
        } catch (\Throwable $e) {
            Log::error('XposedOrNot domain request failed', ['domain' => $domain, 'message' => $e->getMessage()]);
            return ['available' => false, 'pending' => false, 'found' => false, 'top_breaches' => [], 'breach_count' => 0, 'total_exposed' => 0];
        }
    }

    private function fetchDomainSummary(string $domain): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
        ])->timeout(15)->get("{$this->domainBaseUrl}/v2/partner/domain-summary", [
            'domain'  => $domain,
            'details' => 'true',
        ]);
    }

    /**
     * Retry fetching the domain summary after onboarding.
     * Waits $intervalSeconds between each attempt; returns the first successful response
     * or the last failed response if all retries are exhausted.
     */
    private function retryUntilReady(string $domain, int $retries, int $intervalSeconds): \Illuminate\Http\Client\Response
    {
        $last = null;
        for ($i = 0; $i < $retries; $i++) {
            sleep($intervalSeconds);
            $last = $this->fetchDomainSummary($domain);
            Log::info('XposedOrNot domain retry', [
                'domain'  => $domain,
                'attempt' => $i + 1,
                'status'  => $last->status(),
            ]);
            if ($last->successful()) {
                return $last;
            }
        }
        return $last;
    }

    private function pendingDomain(string $reason): array
    {
        return [
            'available'     => false,
            'pending'       => true,
            'found'         => false,
            'top_breaches'  => [],
            'breach_count'  => 0,
            'total_exposed' => 0,
            'reason'        => $reason,
        ];
    }

    private function quotaExceeded(): array
    {
        return [
            'available'      => false,
            'pending'        => false,
            'quota_exceeded' => true,
            'found'          => false,
            'top_breaches'   => [],
            'breach_count'   => 0,
            'total_exposed'  => 0,
        ];
    }

    /**
     * POST /v2/partner/domains
     * Onboard a domain for monitoring. Call this before analyzeDomain().
     */
    public function onboardDomain(string $domain): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key'    => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post("{$this->domainBaseUrl}/v2/partner/domains", [
                'domain' => $domain,
            ]);

            return [
                'success' => $response->successful(),
                'status'  => $response->status(),
                'body'    => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('XposedOrNot domain onboard failed', ['domain' => $domain, 'message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Normalisation helpers
    // -------------------------------------------------------------------------

    private function normalizeEmailResponse(array $data): array
    {
        $breaches           = [];
        $exposedAttributes  = [];

        foreach ($data['breaches'] ?? [] as $breach) {
            // xposed_data is a semicolon-separated string e.g. "Email addresses;Passwords;Names"
            $rawAttrs   = $breach['xposed_data'] ?? '';
            $attributes = array_map('trim', explode(';', $rawAttrs));
            $attributes = array_filter($attributes);

            $breaches[] = [
                'source_name'        => $breach['breach_id'] ?? 'Unknown',
                'breach_date'        => isset($breach['breached_date'])
                    ? substr($breach['breached_date'], 0, 10)
                    : null,
                'record_count'       => (int) ($breach['xposed_records'] ?? 0),
                'exposed_attributes' => array_values($attributes),
                'description'        => $breach['xposure_desc'] ?? '',
                'password_risk'      => $breach['password_risk'] ?? null,
                'logo'               => $breach['logo'] ?? null,
            ];

            $exposedAttributes = array_merge($exposedAttributes, $attributes);
        }

        return [
            'available'          => true,
            'found'              => count($breaches) > 0,
            'breach_count'       => count($breaches),
            'breaches'           => $breaches,
            'exposed_attributes' => array_values(array_unique(array_map('strtolower', $exposedAttributes))),
        ];
    }

    private function normalizeDomainResponse(array $data): array
    {
        $detailedInfo = $data['Detailed_Breach_Info'] ?? [];
        // Breach_Summary has ALL breaches with email counts; Top10_Breaches is the subset
        $breachCounts = !empty($data['Breach_Summary']) ? $data['Breach_Summary'] : ($data['Top10_Breaches'] ?? []);

        // Group emails by breach from the flat Breaches_Details list
        $emailsByBreach = [];
        foreach ($data['Breaches_Details'] ?? [] as $item) {
            $b = $item['breach'] ?? null;
            $e = $item['email'] ?? null;
            if ($b && $e) {
                $emailsByBreach[$b][] = $e;
            }
        }

        $leaderboard = [];
        foreach ($breachCounts as $breachName => $emailCount) {
            $info     = $detailedInfo[$breachName] ?? [];
            $rawAttrs = $info['xposed_data'] ?? '';
            $attrs    = array_values(array_filter(array_map('trim', explode(';', $rawAttrs))));
            $leaderboard[] = [
                'breach'      => $breachName,
                'email_count' => (int) $emailCount,
                'breach_date' => isset($info['breached_date']) ? substr($info['breached_date'], 0, 10) : null,
                'xposed_data' => $attrs,
                'emails'      => $emailsByBreach[$breachName] ?? [],
            ];
        }

        usort($leaderboard, fn($a, $b) => $b['email_count'] <=> $a['email_count']);

        // Domain_Summary holds total exposed email-breach records for the domain
        $domainKey    = array_key_first($data['Domain_Summary'] ?? []);
        $totalExposed = $domainKey ? (int) $data['Domain_Summary'][$domainKey] : (int) array_sum(array_column($leaderboard, 'email_count'));
        $uniqueBreaches = count($leaderboard);

        return [
            'available'      => true,
            'pending'        => false,
            'found'          => $uniqueBreaches > 0,
            'breach_count'   => $uniqueBreaches,
            'top_breaches'   => array_slice($leaderboard, 0, 10),
            'total_exposed'  => $totalExposed,
            'yearly_metrics' => $data['Yearly_Metrics'] ?? [],
        ];
    }

    private function cleanRecord(): array
    {
        return [
            'available'          => true,
            'found'              => false,
            'breach_count'       => 0,
            'breaches'           => [],
            'exposed_attributes' => [],
        ];
    }

    private function unavailable(string $reason): array
    {
        return [
            'available'          => false,
            'found'              => false,
            'breach_count'       => 0,
            'breaches'           => [],
            'exposed_attributes' => [],
            'error'              => $reason,
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 2) . '***@' . $domain;
    }
}
