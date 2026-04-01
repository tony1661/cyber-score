<?php

namespace App\Services;

use App\Models\EmailDelivery;
use App\Models\Submission;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportEmailService
{
    public function send(Submission $submission): array
    {
        $salesRepEmail = config('mail.sales_rep_email', env('SALES_REP_EMAIL', 'tony@meteortel.com'));

        try {
            $submission->loadMissing(['breachEvents', 'categoryScores', 'dnsResult']);

        Mail::send('email.report', ['submission' => $submission], function ($message) use ($submission, $salesRepEmail) {
                $message->to($submission->email)
                    ->cc($salesRepEmail)
                    ->subject('Your Email Exposure Assessment Report')
                    ->replyTo($salesRepEmail, 'Security Assessment Team');
            });

            $delivery = EmailDelivery::create([
                'submission_id'     => $submission->id,
                'sent_to'           => $submission->email,
                'cc_to'             => $salesRepEmail,
                'sent_at'           => now(),
                'delivery_status'   => 'sent',
                'provider_message_id' => null,
            ]);

            Log::info('Assessment report sent', ['submission_id' => $submission->id]);

            return ['success' => true, 'delivery_id' => $delivery->id];
        } catch (\Throwable $e) {
            Log::error('Failed to send assessment report', [
                'submission_id' => $submission->id,
                'error'         => $e->getMessage(),
            ]);

            EmailDelivery::create([
                'submission_id'   => $submission->id,
                'sent_to'         => $submission->email,
                'cc_to'           => $salesRepEmail,
                'sent_at'         => now(),
                'delivery_status' => 'failed',
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
