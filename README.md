Email Exposure & Mail Security Assessment App
Overview

This application evaluates an email address for security risk and exposure by analyzing:

Breach history (via external APIs)
Email domain security (SPF, DKIM, DMARC)

It generates a weighted risk score, displays results visually, stores submissions, and optionally emails a report to the user (CC’ing sales).

Key Features
Email input + validation
Breach/exposure lookup (XposedOrNot API)
SPF, DKIM, DMARC checks
Risk scoring engine (0–100)
Visual results (charts + breakdowns)
Report email delivery (with sales CC)
Database storage of submissions
User Flow
User enters email on landing page
System runs exposure + DNS checks
Results page shows:
Overall score (e.g., Excellent → High Risk)
6 category scores with explanations
Detailed insights + visuals
Option to receive report via email
Scoring Model

Overall score is based on 6 weighted categories:

Breach History (25%)
Data Sensitivity (20%)
SPF Health (15%)
DKIM Health (15%)
DMARC Enforcement (15%)
Domain Security Posture (10%)
Important Rules
Strong email authentication is required for high scores
Any breach significantly lowers the score
Password leaks heavily penalize results
Outputs
Overall risk score + rating
Category breakdowns with rationale
Visualizations (timeline, data exposure, comparisons)
Optional emailed report (with sales CC)
Data & Storage
Stores submissions, scores, and results
Logs processing status and provider responses
Designed for lead tracking and follow-up
Tech Notes
Server-side API for external integrations
DNS checks handled server-side
Configurable email delivery (via environment variables)
Lightweight, extensible architecture
Security & Privacy
Minimal data storage
Secure handling of API keys
Consent required for email/report delivery
Recommended: retention policy + data sanitization
Goal

Deliver a simple, sales-friendly security assessment tool that turns one email into a clear, actionable risk snapshot and a follow-up opportunity.