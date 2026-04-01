<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentRequest;
use App\Models\BreachEvent;
use App\Models\CategoryScore;
use App\Models\DnsResult;
use App\Models\Submission;
use App\Services\DnsCheckService;
use App\Services\ScoringService;
use App\Services\XposedOrNotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    public function __construct(
        private XposedOrNotService $xposedOrNot,
        private DnsCheckService    $dnsChecker,
        private ScoringService     $scorer,
    ) {}

    public function store(AssessmentRequest $request): JsonResponse
    {
        $email  = strtolower(trim($request->validated('email')));
        $domain = substr($email, strpos($email, '@') + 1);

        // Create the submission record immediately
        $submission = Submission::create([
            'email'              => $email,
            'domain'             => $domain,
            'requester_ip'       => $request->ip(),
            'consent_to_email'   => $request->boolean('consent_to_email'),
            'status'             => 'processing',
            'sales_rep_email'    => config('mail.sales_rep_email', env('SALES_REP_EMAIL')),
        ]);

        try {
            // Run checks (email + DNS in parallel intent; domain breach is best-effort)
            $breachData  = $this->xposedOrNot->analyzeEmail($email);
            $domainData  = $this->xposedOrNot->analyzeDomain($domain);
            $dnsData     = $this->dnsChecker->check($domain);
            $scoring     = $this->scorer->calculate($breachData, $dnsData, $domainData);

            // Persist results
            $this->persistCategoryScores($submission, $scoring['categories']);
            $this->persistBreachEvents($submission, $breachData['breaches'] ?? []);
            $this->persistDnsResults($submission, $dnsData, $this->dnsChecker->alignmentNotes(
                $dnsData['spf'], $dnsData['dkim'], $dnsData['dmarc']
            ));

            // Update submission
            $submission->update([
                'status'             => 'complete',
                'overall_score'      => $scoring['overall'],
                'summary'            => $scoring['summary'],
                'breach_count'       => $breachData['breach_count'],
                'domain_breach_json' => $domainData,
            ]);

            return response()->json([
                'id'                => $submission->id,
                'email'             => $email,
                'domain'            => $domain,
                'overall_score'     => $scoring['overall'],
                'grade'             => $scoring['grade'],
                'summary'           => $scoring['summary'],
                'categories'        => $this->formatCategories($scoring['categories']),
                'breach_data'       => $this->formatBreachData($breachData),
                'domain_breach_data' => $domainData,
                'dns_data'          => $this->formatDnsData($dnsData),
                'status'            => 'complete',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Assessment failed', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            $submission->update(['status' => 'failed']);

            return response()->json([
                'message' => 'Assessment could not be completed. Please try again.',
                'error'   => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Submission $submission): JsonResponse
    {
        $submission->load(['categoryScores', 'breachEvents', 'dnsResult']);

        return response()->json([
            'id'            => $submission->id,
            'email'         => $submission->email,
            'domain'        => $submission->domain,
            'overall_score' => $submission->overall_score,
            'grade'         => $this->scorer->calculate([], []  )['grade'] ?? 'N/A',
            'status'        => $submission->status,
            'created_at'    => $submission->created_at,
        ]);
    }

    // -------------------------------------------------------------------------
    // Persistence helpers
    // -------------------------------------------------------------------------

    private function persistCategoryScores(Submission $sub, array $categories): void
    {
        $labels = $this->scorer->categoryLabels();
        foreach ($categories as $key => $cat) {
            CategoryScore::create([
                'submission_id'    => $sub->id,
                'category_key'     => $key,
                'category_name'    => $labels[$key] ?? $key,
                'score'            => $cat['score'],
                'rationale'        => $cat['rationale'],
                'raw_metrics_json' => json_encode($cat),
            ]);
        }
    }

    private function persistBreachEvents(Submission $sub, array $breaches): void
    {
        foreach ($breaches as $breach) {
            BreachEvent::create([
                'submission_id'           => $sub->id,
                'source_name'             => $breach['source_name'] ?? 'Unknown',
                'breach_name'             => $breach['source_name'] ?? 'Unknown',
                'breach_date'             => $breach['breach_date'] ?? null,
                'exposed_attributes_json' => $breach['exposed_attributes'] ?? [],
                'severity_score'          => $this->computeBreachSeverity($breach['exposed_attributes'] ?? []),
            ]);
        }
    }

    private function persistDnsResults(Submission $sub, array $dns, string $alignmentNotes): void
    {
        DnsResult::create([
            'submission_id'   => $sub->id,
            'spf_result'      => $dns['spf']['found'] ? $dns['spf']['quality'] : 'missing',
            'spf_raw'         => $dns['spf']['raw'] ?? null,
            'dkim_result'     => $dns['dkim']['found'] ? $dns['dkim']['quality'] : 'missing',
            'dkim_raw'        => $dns['dkim']['raw'] ?? null,
            'dmarc_result'    => $dns['dmarc']['found'] ? $dns['dmarc']['policy'] : 'missing',
            'dmarc_raw'       => $dns['dmarc']['raw'] ?? null,
            'alignment_notes' => $alignmentNotes,
        ]);
    }

    // -------------------------------------------------------------------------
    // Response formatters
    // -------------------------------------------------------------------------

    private function formatCategories(array $categories): array
    {
        $labels = $this->scorer->categoryLabels();
        $out    = [];
        foreach ($categories as $key => $cat) {
            $out[$key] = array_merge($cat, ['name' => $labels[$key] ?? $key]);
        }
        return $out;
    }

    private function formatBreachData(array $data): array
    {
        return [
            'available'          => $data['available'],
            'found'              => $data['found'],
            'breach_count'       => $data['breach_count'],
            'breaches'           => $data['breaches'],
            'exposed_attributes' => $data['exposed_attributes'],
        ];
    }

    private function formatDnsData(array $dns): array
    {
        return [
            'spf'   => [
                'found'   => $dns['spf']['found'],
                'quality' => $dns['spf']['quality'],
                'raw'     => $dns['spf']['raw'],
                'error'   => $dns['spf']['error'],
            ],
            'dkim'  => [
                'found'    => $dns['dkim']['found'],
                'quality'  => $dns['dkim']['quality'],
                'selector' => $dns['dkim']['selector'],
                'raw'      => $dns['dkim']['raw'],
                'error'    => $dns['dkim']['error'],
            ],
            'dmarc' => [
                'found'  => $dns['dmarc']['found'],
                'policy' => $dns['dmarc']['policy'],
                'raw'    => $dns['dmarc']['raw'],
                'error'  => $dns['dmarc']['error'],
            ],
        ];
    }

    private function computeBreachSeverity(array $attributes): int
    {
        $score = 0;
        foreach ($attributes as $attr) {
            $lower = strtolower($attr);
            $score += match (true) {
                str_contains($lower, 'password') => 50,
                str_contains($lower, 'ssn')      => 50,
                str_contains($lower, 'credit')   => 40,
                str_contains($lower, 'dob')      => 20,
                str_contains($lower, 'phone')    => 10,
                str_contains($lower, 'address')  => 10,
                str_contains($lower, 'name')     => 5,
                default                          => 3,
            };
        }
        return min(100, $score);
    }
}
