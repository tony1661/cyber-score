# Email Exposure Assessment — Setup Guide

## Prerequisites

| Requirement | Minimum version |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 18+ |
| npm | 9+ |
| MySQL | 8.0+ (or use SQLite for dev) |

---

## 1. Scaffold Laravel skeleton files

This repo contains only the custom application files. The Laravel framework skeleton
(`artisan`, `bootstrap/`, `public/`, `storage/`, core `config/`, etc.) must be copied in
from a fresh Laravel 11 project **without overwriting the custom files**:

```bash
# 1a. Scaffold a fresh Laravel project in /tmp
composer create-project laravel/laravel /tmp/laravel-fresh --prefer-dist --quiet

# 1b. Copy only the missing skeleton files (does NOT overwrite our custom files)
cp    /tmp/laravel-fresh/artisan        .
cp    /tmp/laravel-fresh/phpunit.xml    .
cp -r /tmp/laravel-fresh/bootstrap     .
cp -r /tmp/laravel-fresh/public        .
cp -r /tmp/laravel-fresh/storage       .
cp -r /tmp/laravel-fresh/tests         .
cp -r /tmp/laravel-fresh/app/Providers app/
cp -rn /tmp/laravel-fresh/app/Http/Middleware app/Http/

# Core config files not included in this repo
for f in app auth cache database filesystems logging queue session; do
  cp /tmp/laravel-fresh/config/${f}.php config/
done

# Database factories and seeders
cp -r /tmp/laravel-fresh/database/factories database/
cp -r /tmp/laravel-fresh/database/seeders   database/

# 1c. Install PHP dependencies
composer install
```

Verify it worked:
```bash
php artisan --version
# Laravel Framework 11.x.x
```

---

## 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` and fill in:

```dotenv
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cyber_score
DB_USERNAME=root
DB_PASSWORD=your_password

# XposedOrNot paid API — https://plus.xposedornot.com
XPOSEDORNOT_API_KEY=your-api-key-here

# SMTP / Mailgun (or any Laravel-supported driver)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=assessment@yourdomain.com
MAIL_FROM_NAME="Email Exposure Assessment"

# Sales rep CC — this address is copied on every emailed report
SALES_REP_EMAIL=tony@meteortel.com
```

### SQLite (quick dev setup — no MySQL needed)

```dotenv
DB_CONNECTION=sqlite
# leave DB_DATABASE blank — SQLite file is created automatically
```

```bash
touch database/database.sqlite
```

---

## 3. Database

```bash
php artisan migrate
```

This creates five tables: `submissions`, `category_scores`, `breach_events`,
`dns_results`, and `email_deliveries`.

---

## 4. Front-end assets

```bash
npm install
npm run build       # production build
# or
npm run dev         # hot-reload dev server (Vite)
```

---

## 5. Run the application

```bash
# Development (two terminals)
php artisan serve       # terminal 1 — Laravel on http://localhost:8000
npm run dev             # terminal 2 — Vite hot reload

# Or with Laravel Sail (Docker)
./vendor/bin/sail up
./vendor/bin/sail npm run dev
```

Open **http://localhost:8000** in your browser.

---

## 6. Testing email delivery locally

During development, switch to the `log` mail driver so emails are written to
`storage/logs/laravel.log` instead of being sent:

```dotenv
MAIL_MAILER=log
```

Or use **Mailpit** (bundled with Laravel Sail) for a local SMTP inbox:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

Mailpit UI: http://localhost:8025

---

## 7. XposedOrNot API key

Sign up for a paid plan at https://plus.xposedornot.com, then set `XPOSEDORNOT_API_KEY`
in `.env`.

The app handles API errors gracefully — if the key is missing or the API is down,
breach checks will show as "unavailable" and category scores will be marked conservatively.

---

## Application structure

```
app/
  Http/Controllers/
    AssessmentController.php   — POST /api/assessments, GET /api/assessments/{id}
    ReportEmailController.php  — POST /api/assessments/{id}/email-report
  Services/
    XposedOrNotService.php     — Breach/exposure API calls
    DnsCheckService.php        — SPF / DKIM / DMARC lookups
    ScoringService.php         — Weighted scoring + gating caps
    ReportEmailService.php     — Sends HTML email report
  Models/
    Submission.php             — Top-level assessment record
    CategoryScore.php          — Per-category scores + rationale
    BreachEvent.php            — Individual breach incidents
    DnsResult.php              — DNS check results
    EmailDelivery.php          — Email delivery tracking

resources/js/
  views/
    LandingPage.vue            — Email intake form
    ResultsPage.vue            — Full results + charts + email CTA
  components/
    CategoryCard.vue           — Score card widget
    AuthChip.vue               — SPF/DKIM/DMARC status chip
    DetailSection.vue          — Expandable detail accordion
    charts/
      BreachTimeline.vue       — Bar chart: breaches by year
      DataComposition.vue      — Doughnut: exposed data types
      CategoryComparison.vue   — Horizontal bar: all 6 category scores
```

---

## Scoring model summary

| Category | Weight | Cap triggers |
|---|---|---|
| Breach History | 25% | |
| Data Sensitivity | 20% | Password leak → overall ≤ 49 |
| SPF Health | 15% | |
| DKIM Health | 15% | Missing auth → overall ≤ 84 |
| DMARC Enforcement | 15% | Any breach → overall ≤ 69 |
| Domain Security Posture | 10% | |

Grade bands: **90–100** Excellent · **75–89** Good · **55–74** Fair · **35–54** Elevated Risk · **0–34** High Risk

---

## Production checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] Run `npm run build`
- [ ] Configure a queue worker for future async report processing (`php artisan queue:work`)
- [ ] Set up HTTPS (Let's Encrypt / Forge / Vapor)
- [ ] Configure a real SMTP provider (Mailgun, Postmark, SES)
- [ ] Review data retention policy and add a scheduled cleanup command
- [ ] Restrict `requester_ip` storage per your jurisdiction's privacy laws
