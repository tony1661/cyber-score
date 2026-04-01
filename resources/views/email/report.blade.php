<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your Email Exposure Assessment Report</title>
</head>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;margin:0;padding:20px;color:#1e293b;">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);">

  {{-- Header --}}
  <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:36px 32px;text-align:center;">
    <p style="color:#94a3b8;font-size:14px;margin:0;">Email Exposure Assessment Report</p>
    <p style="color:#64748b;font-size:13px;margin:8px 0 0;">{{ $submission->email }} &nbsp;|&nbsp; {{ $submission->created_at->format('F j, Y') }}</p>
  </div>

  @php
    $score = $submission->overall_score;

    $scoreColor = match(true) {
      $score >= 90 => '#10b981',
      $score >= 75 => '#3b82f6',
      $score >= 55 => '#f59e0b',
      $score >= 35 => '#ef4444',
      default      => '#dc2626',
    };

    $grade = match(true) {
      $score >= 90 => 'Excellent',
      $score >= 75 => 'Good',
      $score >= 55 => 'Fair',
      $score >= 35 => 'Elevated Risk',
      default      => 'High Risk',
    };

    function catScoreColor(int $s): string {
      return match(true) {
        $s >= 80 => '#10b981',
        $s >= 60 => '#3b82f6',
        $s >= 40 => '#f59e0b',
        default  => '#ef4444',
      };
    }

    function chipStyle(string $status): string {
      return match($status) {
        'pass' => 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;',
        'warn' => 'background:#fef3c7;color:#92400e;border:1px solid #fcd34d;',
        default => 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;',
      };
    }

    // Breach pills
    $allAttrs = [];
    foreach ($submission->breachEvents as $be) {
      $a = $be->exposed_attributes_json;
      if (is_string($a)) { $a = json_decode($a, true) ?? []; }
      $allAttrs = array_merge($allAttrs, array_map('strtolower', (array) $a));
    }
    $hasPassword  = collect($allAttrs)->contains(fn($a) => str_contains($a, 'password'));
    $hasSensitive = !$hasPassword && collect($allAttrs)->contains(
      fn($a) => collect(['date','dob','phone','address','social','ssn','credit','bank'])->contains(fn($k) => str_contains($a, $k))
    );
    $breachCount = $submission->breachEvents->count();

    // DNS chips
    $spfOk = false; $dkimOk = false; $dkimPartial = false; $dmarcStatus = 'fail';
    $spfDetail = 'missing'; $dkimDetail = 'not detected'; $dmarcDetail = 'missing';
    if ($submission->dnsResult) {
      $spfResult   = $submission->dnsResult->spf_result;
      $spfOk       = in_array($spfResult, ['strict', 'softfail']);
      $spfDetail   = $spfOk ? $spfResult : 'missing';

      $dkimResult  = $submission->dnsResult->dkim_result;
      $dkimOk      = $dkimResult === 'valid';
      $dkimPartial = $dkimResult === 'partial';
      $dkimDetail  = $dkimOk ? 'detected' : ($dkimPartial ? 'partial' : 'not detected');

      $dmarcPolicy = $submission->dnsResult->dmarc_result;
      $dmarcStatus = match($dmarcPolicy) { 'reject' => 'pass', 'quarantine', 'none' => 'warn', default => 'fail' };
      $dmarcDetail = $dmarcPolicy && $dmarcPolicy !== 'missing' ? 'p=' . $dmarcPolicy : 'missing';
    }

    // Unique exposed attributes across all breaches
    $uniqueAttrs = array_values(array_unique($allAttrs));

    // ── Gauge SVG pre-computation ──────────────────────────────────────────
    // Semicircle: M 10,120 A 100,100 0 0,1 210,120  (cx=110, cy=120, r=100)
    $arcLen     = M_PI * 100; // ≈ 314.16
    $filledArc  = round(($score / 100) * $arcLen, 2);

    // Tick marks at 0, 25, 50, 75, 100
    // angle formula: a = π - (pct/100)*π  (0%→left, 100%→right, 50%→top)
    $ticks = [];
    foreach ([0, 25, 50, 75, 100] as $pct) {
      $a = M_PI - ($pct / 100) * M_PI;
      $ticks[] = [
        'ix' => round(110 + 86  * cos($a), 1), 'iy' => round(120 - 86  * sin($a), 1),
        'ox' => round(110 + 103 * cos($a), 1), 'oy' => round(120 - 103 * sin($a), 1),
        'lx' => round(110 + 114 * cos($a), 1), 'ly' => round(120 - 114 * sin($a), 1),
        'pct' => $pct,
      ];
    }

    // ── Radar SVG pre-computation ──────────────────────────────────────────
    $cats    = $submission->categoryScores;
    $n       = max($cats->count(), 1);
    $cx      = 130; $cy = 130; $maxR = 95;
    $shortNames = [
      'Breach History'           => 'Breaches',
      'Data Sensitivity Exposed' => 'Data Sensitivity',
      'SPF Health'               => 'SPF',
      'DKIM Health'              => 'DKIM',
      'DMARC Enforcement'        => 'DMARC',
      'Domain Security Posture'  => 'Domain',
    ];

    // Grid polygons at 25 / 50 / 75 / 100
    $gridPolygons = [];
    foreach ([25, 50, 75, 100] as $lvl) {
      $pts = [];
      for ($i = 0; $i < $n; $i++) {
        $a = deg2rad(-90 + ($i * 360 / $n));
        $r = ($lvl / 100) * $maxR;
        $pts[] = round($cx + $r * cos($a), 1) . ',' . round($cy + $r * sin($a), 1);
      }
      $gridPolygons[] = ['pts' => implode(' ', $pts), 'lvl' => $lvl];
    }

    // Axis lines + labels
    $axes = [];
    foreach ($cats as $i => $cat) {
      $a     = deg2rad(-90 + ($i * 360 / $n));
      $label = $shortNames[$cat->category_name] ?? $cat->category_name;
      $lx    = round($cx + ($maxR + 24) * cos($a), 1);
      $ly    = round($cy + ($maxR + 24) * sin($a), 1);
      $anchor = abs(cos($a)) < 0.15 ? 'middle' : (cos($a) > 0 ? 'start' : 'end');
      $axes[] = [
        'x1' => $cx, 'y1' => $cy,
        'x2' => round($cx + $maxR * cos($a), 1),
        'y2' => round($cy + $maxR * sin($a), 1),
        'lx' => $lx, 'ly' => $ly,
        'label'  => $label,
        'anchor' => $anchor,
      ];
    }

    // Data polygon + point colors
    $dataPts   = [];
    $dotPoints = [];
    foreach ($cats as $i => $cat) {
      $a = deg2rad(-90 + ($i * 360 / $n));
      $r = ($cat->score / 100) * $maxR;
      $px = round($cx + $r * cos($a), 1);
      $py = round($cy + $r * sin($a), 1);
      $dataPts[]   = "$px,$py";
      $dotPoints[] = [
        'x'     => $px, 'y' => $py,
        'color' => catScoreColor($cat->score),
      ];
    }
    $dataPolygon = implode(' ', $dataPts);
  @endphp

  {{-- Score block: Gauge + Radar side-by-side ──────────────────────────── --}}
  <div style="padding:28px 32px 20px;border-bottom:1px solid #e2e8f0;">
    <p style="text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 18px;">Email Exposure Score</p>

    {{-- Two-column table for email client compatibility --}}
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        {{-- Left: Gauge --}}
        <td width="50%" align="center" valign="middle" style="padding:0 8px 0 0;">
          <svg viewBox="0 0 220 148" width="220" height="148" xmlns="http://www.w3.org/2000/svg">
            {{-- Tick marks --}}
            @foreach ($ticks as $t)
              <line x1="{{ $t['ix'] }}" y1="{{ $t['iy'] }}" x2="{{ $t['ox'] }}" y2="{{ $t['oy'] }}"
                stroke="#334155" stroke-width="2" stroke-linecap="round"/>
              <text x="{{ $t['lx'] }}" y="{{ $t['ly'] }}" text-anchor="middle" dominant-baseline="middle"
                fill="#64748b" font-size="9" font-family="Arial,sans-serif">{{ $t['pct'] }}</text>
            @endforeach
            {{-- Track --}}
            <path d="M 10,120 A 100,100 0 0,1 210,120"
              fill="none" stroke="#e2e8f0" stroke-width="14" stroke-linecap="round"/>
            {{-- Filled arc --}}
            <path d="M 10,120 A 100,100 0 0,1 210,120"
              fill="none" stroke="{{ $scoreColor }}" stroke-width="14" stroke-linecap="round"
              stroke-dasharray="{{ $filledArc }} {{ $arcLen }}"/>
            {{-- Score number --}}
            <text x="110" y="100" text-anchor="middle" dominant-baseline="middle"
              fill="{{ $scoreColor }}" font-size="46" font-weight="800" font-family="Arial,sans-serif">{{ $score }}</text>
            {{-- /100 --}}
            <text x="110" y="120" text-anchor="middle"
              fill="#94a3b8" font-size="12" font-family="Arial,sans-serif">/ 100</text>
            {{-- Grade label --}}
            <text x="110" y="140" text-anchor="middle"
              fill="{{ $scoreColor }}" font-size="14" font-weight="700" font-family="Arial,sans-serif">{{ $grade }}</text>
          </svg>
        </td>

        {{-- Right: Radar --}}
        <td width="50%" align="center" valign="middle" style="padding:0 0 0 8px;">
          @php $radarSize = 260; $vb = $cx * 2; @endphp
          <svg viewBox="0 0 {{ $vb }} {{ $vb }}" width="{{ $radarSize }}" height="{{ $radarSize }}" xmlns="http://www.w3.org/2000/svg">
            {{-- Grid polygons --}}
            @foreach ($gridPolygons as $grid)
              <polygon points="{{ $grid['pts'] }}"
                fill="none" stroke="#e2e8f0" stroke-width="{{ $grid['lvl'] === 100 ? 1.5 : 1 }}"/>
            @endforeach
            {{-- Axis lines --}}
            @foreach ($axes as $ax)
              <line x1="{{ $ax['x1'] }}" y1="{{ $ax['y1'] }}" x2="{{ $ax['x2'] }}" y2="{{ $ax['y2'] }}"
                stroke="#e2e8f0" stroke-width="1"/>
              <text x="{{ $ax['lx'] }}" y="{{ $ax['ly'] }}" text-anchor="{{ $ax['anchor'] }}" dominant-baseline="middle"
                fill="#64748b" font-size="11" font-weight="600" font-family="Arial,sans-serif">{{ $ax['label'] }}</text>
            @endforeach
            {{-- Data fill --}}
            <polygon points="{{ $dataPolygon }}"
              fill="rgba(59,130,246,0.12)" stroke="#3b82f6" stroke-width="2"/>
            {{-- Data points --}}
            @foreach ($dotPoints as $dot)
              <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="5"
                fill="{{ $dot['color'] }}" stroke="#ffffff" stroke-width="1.5"/>
            @endforeach
          </svg>
        </td>
      </tr>
    </table>

    @if ($submission->summary)
      <div style="color:#64748b;font-size:13px;margin-top:16px;text-align:center;">{{ $submission->summary }}</div>
    @endif

    {{-- Pills row --}}
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;align-items:center;">
      @if ($breachCount === 0)
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle('pass') }}">Breaches &middot; none found</span>
      @else
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle('fail') }}">Breaches &middot; {{ $breachCount }} found</span>
      @endif
      @if ($hasPassword)
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle('fail') }}">Passwords &middot; exposed</span>
      @endif
      @if ($hasSensitive)
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle('fail') }}">Sensitive Data &middot; exposed</span>
      @endif
      <span style="width:6px;height:6px;border-radius:50%;background:#cbd5e1;display:inline-block;"></span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle($spfOk ? 'pass' : 'fail') }}">SPF &middot; {{ $spfDetail }}</span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle($dkimOk ? 'pass' : ($dkimPartial ? 'warn' : 'fail')) }}">DKIM &middot; {{ $dkimDetail }}</span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;{{ chipStyle($dmarcStatus) }}">DMARC &middot; {{ $dmarcDetail }}</span>
    </div>
  </div>

  {{-- Category Breakdown --}}
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 16px;">Category Breakdown</h2>
    @foreach ($submission->categoryScores as $cat)
      <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f1f5f9;">
        <div>
          <div style="font-weight:600;font-size:14px;color:#1e293b;">{{ $cat->category_name }}</div>
          @if ($cat->rationale)
            <div style="font-size:12px;color:#64748b;margin-top:2px;">{{ $cat->rationale }}</div>
          @endif
        </div>
        <div style="font-size:22px;font-weight:800;text-align:right;min-width:50px;color:{{ catScoreColor($cat->score) }};">{{ $cat->score }}</div>
      </div>
    @endforeach
  </div>

  {{-- Breach History --}}
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px;">Breach History</h2>
    @if ($submission->breachEvents->isEmpty())
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;color:#15803d;font-size:14px;font-weight:600;">
        ✓ No known breaches found for this email address.
      </div>
    @else
      @foreach ($submission->breachEvents as $breach)
        @php
          $bAttrs = $breach->exposed_attributes_json;
          if (is_string($bAttrs)) { $bAttrs = json_decode($bAttrs, true) ?? []; }
        @endphp
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:8px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="font-weight:700;font-size:14px;color:#991b1b;">{{ $breach->breach_name }}</div>
            @if ($breach->breach_date)
              <div style="font-size:11px;color:#6b7280;background:#fee2e2;padding:2px 8px;border-radius:12px;white-space:nowrap;">
                {{ \Carbon\Carbon::parse($breach->breach_date)->format('M Y') }}
              </div>
            @endif
          </div>
          @if (!empty($bAttrs))
            <div style="font-size:12px;color:#475569;margin-top:6px;">
              <span style="font-weight:600;">Exposed:</span> {{ implode(', ', (array) $bAttrs) }}
            </div>
          @endif
        </div>
      @endforeach
    @endif
  </div>

  {{-- Data Sensitivity (mirrors web UI section) --}}
  @if (!empty($uniqueAttrs))
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px;">Data Sensitivity Exposed</h2>
    <p style="font-size:13px;color:#64748b;margin:0 0 12px;">The following data types were found across all breach records:</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      @foreach ($uniqueAttrs as $attr)
        @php
          $high = ['password','ssn','social security','credit card','bank'];
          $med  = ['date of birth','dob','phone','address'];
          $al   = strtolower($attr);
          $attrStyle = collect($high)->contains(fn($h) => str_contains($al,$h))
            ? 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;'
            : (collect($med)->contains(fn($m) => str_contains($al,$m))
              ? 'background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;'
              : 'background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;');
        @endphp
        <span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;{{ $attrStyle }}">{{ $attr }}</span>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Domain Breach Leaderboard --}}
  @php
    $domainData = $submission->domain_breach_json;
  @endphp
  @if ($domainData)
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">Domain Security Posture</h2>
    <p style="font-size:12px;color:#94a3b8;margin:0 0 16px;">Breach exposure across all accounts on {{ $submission->domain }}</p>

    @if (!empty($domainData['pending']))
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;color:#92400e;font-size:13px;">
        ⏳ Domain-level breach data is still being indexed. Run a new assessment in a few hours to see the full leaderboard.
      </div>
    @elseif (!empty($domainData['quota_exceeded']))
      {{-- Quota hit — omit the section body entirely, just skip --}}
    @elseif (empty($domainData['available']))
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;color:#94a3b8;font-size:13px;">
        Domain breach data unavailable.
      </div>
    @elseif (empty($domainData['found']))
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;color:#15803d;font-size:14px;font-weight:600;">
        ✓ No known breaches found for any accounts on {{ $submission->domain }}.
      </div>
    @else
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;margin-bottom:10px;">
        <span>{{ $domainData['breach_count'] }} breach(es) affecting this domain</span>
        <span>{{ number_format($domainData['total_exposed']) }} account(s) exposed total</span>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">#</th>
            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">Breach</th>
            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">Date</th>
            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">Data Exposed</th>
            <th style="text-align:right;padding:8px 10px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">Accounts</th>
          </tr>
        </thead>
        <tbody>
          @foreach (($domainData['top_breaches'] ?? []) as $idx => $b)
            @php
              $rowBg    = $idx % 2 === 0 ? '#ffffff' : '#f8fafc';
              $countClr = $b['email_count'] >= 10 ? '#dc2626' : ($b['email_count'] >= 3 ? '#d97706' : '#475569');
              $bDate    = isset($b['breach_date']) ? substr($b['breach_date'], 0, 7) : '—';
              $attrs    = $b['xposed_data'] ?? [];
              // Sort: passwords first
              usort($attrs, function($a, $b) {
                $ap = str_contains(strtolower($a), 'password') ? 0 : 1;
                $bp = str_contains(strtolower($b), 'password') ? 0 : 1;
                return $ap - $bp;
              });
              $shownAttrs = array_slice($attrs, 0, 3);
              $extraAttrs = count($attrs) - 3;
            @endphp
            <tr style="background:{{ $rowBg }};border-bottom:1px solid #f1f5f9;">
              <td style="padding:8px 10px;color:#94a3b8;font-size:12px;vertical-align:top;">{{ $idx + 1 }}</td>
              <td style="padding:8px 10px;vertical-align:top;">
                <div style="font-weight:700;color:#1e293b;margin-bottom:4px;">{{ $b['breach'] }}</div>
                @foreach (($b['emails'] ?? []) as $email)
                  <span style="display:inline-block;font-family:monospace;font-size:11px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:1px 6px;border-radius:4px;margin:1px 2px 1px 0;">{{ $email }}</span>
                @endforeach
              </td>
              <td style="padding:8px 10px;color:#64748b;font-size:12px;vertical-align:top;white-space:nowrap;">{{ $bDate }}</td>
              <td style="padding:8px 10px;vertical-align:top;">
                @foreach ($shownAttrs as $attr)
                  @php
                    $isPass = str_contains(strtolower($attr), 'password');
                    $tagStyle = $isPass
                      ? 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;'
                      : 'background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;';
                  @endphp
                  <span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin:1px 2px 1px 0;{{ $tagStyle }}">{{ $attr }}</span>
                @endforeach
                @if ($extraAttrs > 0)
                  <span style="font-size:11px;color:#94a3b8;">+{{ $extraAttrs }}</span>
                @endif
              </td>
              <td style="padding:8px 10px;text-align:right;font-weight:800;font-size:14px;color:{{ $countClr }};vertical-align:top;">{{ number_format($b['email_count']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
  @endif

  {{-- CTA --}}
  <div style="background:#eff6ff;padding:24px 32px;text-align:center;">
    <p style="font-size:14px;color:#475569;margin:0 0 12px;">Want expert help fixing these issues? Our team can help you configure SPF, DKIM, and DMARC correctly and monitor for future exposure.</p>
    <a href="{{ env('DISCOVERY_CALL_URL', 'https://meteortel.com/discovery-call/') }}"
       style="display:inline-block;background:#3b82f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;">
      Book a Security Review
    </a>
  </div>

  {{-- Footer --}}
  <div style="padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;">
    <p style="margin:0;">This report was generated by the MeteorTel Email Exposure Assessment tool.<br />
    Your data is handled per our privacy policy. This report was sent to {{ $submission->email }}.</p>
    <p style="margin-top:8px;color:#cbd5e1;">© {{ date('Y') }} MeteorTel Security. All rights reserved.</p>
  </div>

</div>
</body>
</html>
