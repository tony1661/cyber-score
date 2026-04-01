<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\ReportEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportEmailController extends Controller
{
    public function __construct(private ReportEmailService $mailer) {}

    public function send(Request $request, Submission $submission): JsonResponse
    {
        // Optionally verify submission is complete
        if ($submission->status !== 'complete') {
            return response()->json(['message' => 'Assessment is not yet complete.'], 422);
        }

        // Load relationships for the email template
        $submission->load(['categoryScores', 'breachEvents', 'dnsResult']);

        $result = $this->mailer->send($submission);

        if ($result['success']) {
            return response()->json(['message' => 'Report sent successfully.']);
        }

        return response()->json(['message' => 'Failed to send report. Please try again.'], 500);
    }
}
