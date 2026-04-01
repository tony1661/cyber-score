<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'             => ['required', 'email:rfc,dns'],
            'consent_to_email'  => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'            => 'An email address is required.',
            'email.email'               => 'Please enter a valid email address.',
            'consent_to_email.required' => 'Please indicate whether you consent to email communication.',
        ];
    }
}
