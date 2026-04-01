<?php

namespace App\Services;

class ScoringService
{
    // Category weights (must sum to 1.0)
    private array $weights = [
        'breach_history'       => 0.25,
        'data_sensitivity'     => 0.20,
        'spf_health'           => 0.15,
        'dkim_health'          => 0.15,
        'dmarc_enforcement'    => 0.15,
        'domain_posture'       => 0.10,
    ];

    // Data sensitivity penalties
    private array $sensitivityPenalties = [
        'password'          => 45,
        'passwords'         => 45,
        'date of birth'     => 15,
        'dob'               => 15,
        'full name'         => 10,
        'name'              => 10,
        'first name'        => 5,
        'last name'         => 5,
        'phone'             => 10,
        'phone number'      => 10,
        'address'           => 10,
        'physical address'  => 10,
        'ip address'        => 5,
        'ip'                => 5,
        'employer'          => 5,
        'company'           => 5,
        'username'          => 5,
        'handle'            => 5,
        'email'             => 3,
        'social security'   => 45,
        'ssn'               => 45,
        'credit card'       => 40,
        'bank account'      => 40,
        'gender'            => 2,
        'age'               => 2,
        'location'          => 5,
        'geographic'        => 5,
    ];

    public function calculate(array $breachData, array $dnsData, array $domainData = []): array
    {
        $categories = [
            'breach_history'    => $this->scoreBreachHistory($breachData),
            'data_sensitivity'  => $this->scoreDataSensitivity($breachData),
            'spf_health'        => $this->scoreSpfHealth($dnsData['spf']),
            'dkim_health'       => $this->scoreDkimHealth($dnsData['dkim']),
            'dmarc_enforcement' => $this->scoreDmarcEnforcement($dnsData['dmarc']),
            'domain_posture'    => $this->scoreDomainPosture($dnsData, $domainData),
        ];

        $weighted = 0;
        foreach ($categories as $key => $cat) {
            $weighted += $cat['score'] * $this->weights[$key];
        }

        $overall = round($weighted);
        $overall = $this->applyGatingCaps($overall, $breachData, $dnsData);

        return [
            'overall'    => $overall,
            'grade'      => $this->grade($overall),
            'summary'    => $this->overallSummary($overall, $breachData, $dnsData),
            'categories' => $categories,
        ];
    }

    // -------------------------------------------------------------------------
    // Category scorers
    // -------------------------------------------------------------------------

    public function scoreBreachHistory(array $data): array
    {
        if (!$data['available']) {
            return $this->category(50, 'pending', 'Breach lookup unavailable — scored conservatively.');
        }

        $count = $data['breach_count'];
        if ($count === 0) {
            return $this->category(100, 'pass', 'No known breaches found for this email address.');
        }

        // Check recency
        $mostRecent = $this->mostRecentBreachYear($data['breaches']);
        $currentYear = (int) date('Y');
        $yearsAgo = $mostRecent ? ($currentYear - $mostRecent) : 99;

        if ($count === 1 && $yearsAgo > 3) {
            $score = rand(80, 92);
            $rationale = "One older incident ({$mostRecent}). Low recurrence risk.";
        } elseif ($count <= 2 && $yearsAgo > 2) {
            $score = rand(60, 79);
            $rationale = "{$count} incidents, most recent in {$mostRecent}. Moderate exposure history.";
        } elseif ($count <= 5) {
            $score = rand(35, 59);
            $rationale = "{$count} known incidents. Repeated exposure detected.";
        } else {
            $score = rand(5, 34);
            $rationale = "{$count} known incidents including recent activity. High breach history risk.";
        }

        if ($yearsAgo <= 1) {
            $score = min($score, 30);
            $rationale .= ' Recent breach within the last year is a strong risk signal.';
        }

        return $this->category($score, 'fail', $rationale);
    }

    public function scoreDataSensitivity(array $data): array
    {
        if (!$data['available']) {
            return $this->category(50, 'pending', 'Data sensitivity could not be evaluated.');
        }

        if (empty($data['exposed_attributes'])) {
            return $this->category(100, 'pass', 'No sensitive data types detected in known exposures.');
        }

        $score = 100;
        $applied = [];

        foreach ($data['exposed_attributes'] as $attr) {
            $lower = strtolower($attr);
            foreach ($this->sensitivityPenalties as $key => $penalty) {
                if (str_contains($lower, $key) && !in_array($key, $applied)) {
                    $score -= $penalty;
                    $applied[] = $key;
                    break;
                }
            }
        }

        $score = max(0, $score);
        $exposed = implode(', ', array_slice($data['exposed_attributes'], 0, 6));
        $rationale = "Exposed data types include: {$exposed}." . (count($data['exposed_attributes']) > 6 ? ' (and more)' : '');

        $status = $score >= 70 ? 'warn' : 'fail';

        return $this->category($score, $status, $rationale);
    }

    public function scoreSpfHealth(array $spf): array
    {
        if (!$spf['found']) {
            if ($spf['error'] && str_contains(strtolower($spf['error']), 'failed')) {
                return $this->category(0, 'unavailable', 'SPF lookup failed — DNS may be unreachable.');
            }
            return $this->category(0, 'fail', 'No SPF record found. The domain is unprotected against email spoofing.');
        }

        return match ($spf['quality']) {
            'strict'     => $this->category(100, 'pass', 'SPF record is present with strict -all enforcement.'),
            'softfail'   => $this->category(100, 'pass', 'SPF record is present with ~all softfail policy.'),
            'weak'       => $this->category(40, 'fail', 'SPF uses ?all (neutral). No practical enforcement — effectively disabled.'),
            'permissive' => $this->category(10, 'fail', 'SPF uses +all — allows any server to send as this domain. Critically misconfigured.'),
            default      => $this->category(60, 'warn', 'SPF record found but enforcement policy is unclear.'),
        };
    }

    public function scoreDkimHealth(array $dkim): array
    {
        if (!$dkim['found']) {
            return $this->category(0, 'fail', 'No DKIM selector detected. Message integrity cannot be verified.');
        }

        return match ($dkim['quality']) {
            'valid'   => $this->category(100, 'pass', "DKIM selector \"{$dkim['selector']}\" found with a valid key record."),
            'partial' => $this->category(60, 'warn', "DKIM record found on selector \"{$dkim['selector']}\" but the key may be revoked or empty."),
            default   => $this->category(40, 'warn', 'DKIM configuration is uncertain.'),
        };
    }

    public function scoreDmarcEnforcement(array $dmarc): array
    {
        if (!$dmarc['found']) {
            return $this->category(0, 'fail', 'No DMARC record found. The domain has no anti-impersonation policy in place.');
        }

        return match ($dmarc['policy']) {
            'reject'     => $this->category(100, 'pass', 'DMARC is configured with p=reject — the strongest anti-impersonation enforcement.'),
            'quarantine' => $this->category(85, 'warn', 'DMARC is configured with p=quarantine — good, but upgrading to p=reject is recommended.'),
            'none'       => $this->category(60, 'warn', 'DMARC exists but uses p=none (monitor only). No messages are blocked or quarantined.'),
            default      => $this->category(40, 'warn', 'DMARC record present but policy is unclear.'),
        };
    }

    public function scoreDomainPosture(array $dns, array $domainData = []): array
    {
        $spf   = $dns['spf'];
        $dkim  = $dns['dkim'];
        $dmarc = $dns['dmarc'];

        $allPresent = $spf['found'] && $dkim['found'] && $dmarc['found'];

        // Start with DNS authentication assessment
        if (!$allPresent) {
            $missing = array_filter([
                !$spf['found']   ? 'SPF'   : null,
                !$dkim['found']  ? 'DKIM'  : null,
                !$dmarc['found'] ? 'DMARC' : null,
            ]);
            $score = max(0, 40 - (count($missing) * 15));
            $list  = implode(', ', $missing);
            return $this->category($score, 'fail', "Missing authentication controls: {$list}. Overall posture is weak.");
        }

        $weaknesses = [];
        if (in_array($spf['quality'], ['weak', 'permissive', 'unknown'])) {
            $weaknesses[] = 'SPF policy is not enforced';
        }
        if ($dkim['quality'] !== 'valid') {
            $weaknesses[] = 'DKIM key may be invalid';
        }
        if (in_array($dmarc['policy'], ['none', null])) {
            $weaknesses[] = 'DMARC enforcement is minimal';
        }

        $score = empty($weaknesses) ? 100 : max(30, 80 - (count($weaknesses) * 15));

        // Factor in domain-level breach exposure
        if (!empty($domainData['available']) && !empty($domainData['found'])) {
            $domainBreachCount = $domainData['breach_count'] ?? 0;
            $totalExposed      = $domainData['total_exposed'] ?? 0;

            if ($totalExposed >= 20) {
                $score = min($score, 50);
            } elseif ($totalExposed >= 5) {
                $score = min($score, 70);
            } elseif ($domainBreachCount > 0) {
                $score = min($score, 85);
            }

            $domainNote = "{$totalExposed} account(s) across this domain exposed in {$domainBreachCount} breach(es).";
        } elseif (!empty($domainData['pending'])) {
            $domainNote = 'Domain-level breach data is being processed — check back on the next assessment.';
        } else {
            $domainNote = null;
        }

        $status = $score >= 80 ? 'pass' : ($score >= 50 ? 'warn' : 'fail');

        $rationale = empty($weaknesses)
            ? 'SPF, DKIM, and DMARC are all present and properly configured.'
            : 'Authentication controls present but weaknesses exist: ' . implode('; ', $weaknesses) . '.';

        if ($domainNote) {
            $rationale .= ' ' . $domainNote;
        }

        return $this->category($score, $status, $rationale);
    }

    // -------------------------------------------------------------------------
    // Gating caps (SOW requirement)
    // -------------------------------------------------------------------------

    private function applyGatingCaps(int $score, array $breachData, array $dnsData): int
    {
        $spfOk   = $dnsData['spf']['found']   && in_array($dnsData['spf']['quality'],   ['strict', 'softfail']);
        $dkimOk  = $dnsData['dkim']['found']  && $dnsData['dkim']['quality']  !== 'none';
        $dmarcOk = $dnsData['dmarc']['found'] && $dnsData['dmarc']['policy']  !== null;

        // Cap at 84 unless SPF+DKIM+DMARC all pass minimum validation
        if (!($spfOk && $dkimOk && $dmarcOk)) {
            $score = min($score, 84);
        }

        // Cap at 69 if any known breach exists
        if ($breachData['available'] && $breachData['found']) {
            $score = min($score, 69);
        }

        // Cap at 49 if password leak detected
        if ($this->hasPasswordLeak($breachData)) {
            $score = min($score, 49);
        }

        return $score;
    }

    private function hasPasswordLeak(array $data): bool
    {
        foreach ($data['exposed_attributes'] ?? [] as $attr) {
            $lower = strtolower($attr);
            if (str_contains($lower, 'password') || $lower === 'passwords') {
                return true;
            }
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function category(int $score, string $status, string $rationale): array
    {
        return [
            'score'    => max(0, min(100, $score)),
            'status'   => $status,  // pass | warn | fail | pending | unavailable
            'rationale' => $rationale,
        ];
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 55 => 'Fair',
            $score >= 35 => 'Elevated Risk',
            default      => 'High Risk',
        };
    }

    private function overallSummary(int $score, array $breach, array $dns): string
    {
        $parts = [];

        if ($breach['available'] && $breach['found']) {
            $parts[] = "{$breach['breach_count']} known breach(es) detected";
        } elseif ($breach['available']) {
            $parts[] = 'no known breaches';
        }

        $missing = array_filter([
            !$dns['spf']['found']   ? 'SPF'   : null,
            !$dns['dkim']['found']  ? 'DKIM'  : null,
            !$dns['dmarc']['found'] ? 'DMARC' : null,
        ]);

        if (!empty($missing)) {
            $parts[] = 'missing ' . implode(' & ', $missing);
        } else {
            $parts[] = 'mail authentication configured';
        }

        $context = empty($parts) ? '' : ' (' . implode(', ', $parts) . ')';
        return "Overall risk score: {$score}/100{$context}.";
    }

    private function mostRecentBreachYear(array $breaches): ?int
    {
        $years = [];
        foreach ($breaches as $b) {
            if (!empty($b['breach_date'])) {
                $year = (int) substr((string) $b['breach_date'], 0, 4);
                if ($year > 2000) {
                    $years[] = $year;
                }
            }
        }
        return empty($years) ? null : max($years);
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function statusForScore(int $score): string
    {
        return match(true) {
            $score >= 80 => 'pass',
            $score >= 60 => 'warn',
            $score >= 40 => 'warn',
            default      => 'fail',
        };
    }

    public function categoryLabels(): array
    {
        return [
            'breach_history'    => 'Breach History',
            'data_sensitivity'  => 'Data Sensitivity Exposed',
            'spf_health'        => 'SPF Health',
            'dkim_health'       => 'DKIM Health',
            'dmarc_enforcement' => 'DMARC Enforcement',
            'domain_posture'    => 'Domain Security Posture',
        ];
    }
}
