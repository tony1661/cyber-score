<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your Email Exposure Assessment Report</title>
</head>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;margin:0;padding:20px;color:#1e293b;">
<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);">

  
  <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);padding:36px 32px;text-align:center;">
    <p style="color:#94a3b8;font-size:14px;margin:0;">Email Exposure Assessment Report</p>
    <p style="color:#64748b;font-size:13px;margin:8px 0 0;"><?php echo e($submission->email); ?> &nbsp;|&nbsp; <?php echo e($submission->created_at->format('F j, Y')); ?></p>
  </div>

  <?php
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
  ?>

  
  <div style="padding:28px 32px 20px;border-bottom:1px solid #e2e8f0;">
    <p style="text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 18px;">Email Exposure Score</p>

    
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr>
        
        <td width="50%" align="center" valign="middle" style="padding:0 8px 0 0;">
          <svg viewBox="0 0 220 148" width="220" height="148" xmlns="http://www.w3.org/2000/svg">
            
            <?php $__currentLoopData = $ticks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <line x1="<?php echo e($t['ix']); ?>" y1="<?php echo e($t['iy']); ?>" x2="<?php echo e($t['ox']); ?>" y2="<?php echo e($t['oy']); ?>"
                stroke="#334155" stroke-width="2" stroke-linecap="round"/>
              <text x="<?php echo e($t['lx']); ?>" y="<?php echo e($t['ly']); ?>" text-anchor="middle" dominant-baseline="middle"
                fill="#64748b" font-size="9" font-family="Arial,sans-serif"><?php echo e($t['pct']); ?></text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            
            <path d="M 10,120 A 100,100 0 0,1 210,120"
              fill="none" stroke="#e2e8f0" stroke-width="14" stroke-linecap="round"/>
            
            <path d="M 10,120 A 100,100 0 0,1 210,120"
              fill="none" stroke="<?php echo e($scoreColor); ?>" stroke-width="14" stroke-linecap="round"
              stroke-dasharray="<?php echo e($filledArc); ?> <?php echo e($arcLen); ?>"/>
            
            <text x="110" y="100" text-anchor="middle" dominant-baseline="middle"
              fill="<?php echo e($scoreColor); ?>" font-size="46" font-weight="800" font-family="Arial,sans-serif"><?php echo e($score); ?></text>
            
            <text x="110" y="120" text-anchor="middle"
              fill="#94a3b8" font-size="12" font-family="Arial,sans-serif">/ 100</text>
            
            <text x="110" y="140" text-anchor="middle"
              fill="<?php echo e($scoreColor); ?>" font-size="14" font-weight="700" font-family="Arial,sans-serif"><?php echo e($grade); ?></text>
          </svg>
        </td>

        
        <td width="50%" align="center" valign="middle" style="padding:0 0 0 8px;">
          <?php $radarSize = 260; $vb = $cx * 2; ?>
          <svg viewBox="0 0 <?php echo e($vb); ?> <?php echo e($vb); ?>" width="<?php echo e($radarSize); ?>" height="<?php echo e($radarSize); ?>" xmlns="http://www.w3.org/2000/svg">
            
            <?php $__currentLoopData = $gridPolygons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $grid): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <polygon points="<?php echo e($grid['pts']); ?>"
                fill="none" stroke="#e2e8f0" stroke-width="<?php echo e($grid['lvl'] === 100 ? 1.5 : 1); ?>"/>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            
            <?php $__currentLoopData = $axes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ax): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <line x1="<?php echo e($ax['x1']); ?>" y1="<?php echo e($ax['y1']); ?>" x2="<?php echo e($ax['x2']); ?>" y2="<?php echo e($ax['y2']); ?>"
                stroke="#e2e8f0" stroke-width="1"/>
              <text x="<?php echo e($ax['lx']); ?>" y="<?php echo e($ax['ly']); ?>" text-anchor="<?php echo e($ax['anchor']); ?>" dominant-baseline="middle"
                fill="#64748b" font-size="11" font-weight="600" font-family="Arial,sans-serif"><?php echo e($ax['label']); ?></text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            
            <polygon points="<?php echo e($dataPolygon); ?>"
              fill="rgba(59,130,246,0.12)" stroke="#3b82f6" stroke-width="2"/>
            
            <?php $__currentLoopData = $dotPoints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <circle cx="<?php echo e($dot['x']); ?>" cy="<?php echo e($dot['y']); ?>" r="5"
                fill="<?php echo e($dot['color']); ?>" stroke="#ffffff" stroke-width="1.5"/>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </svg>
        </td>
      </tr>
    </table>

    <?php if($submission->summary): ?>
      <div style="color:#64748b;font-size:13px;margin-top:16px;text-align:center;"><?php echo e($submission->summary); ?></div>
    <?php endif; ?>

    
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center;align-items:center;">
      <?php if($breachCount === 0): ?>
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle('pass')); ?>">Breaches &middot; none found</span>
      <?php else: ?>
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle('fail')); ?>">Breaches &middot; <?php echo e($breachCount); ?> found</span>
      <?php endif; ?>
      <?php if($hasPassword): ?>
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle('fail')); ?>">Passwords &middot; exposed</span>
      <?php endif; ?>
      <?php if($hasSensitive): ?>
        <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle('fail')); ?>">Sensitive Data &middot; exposed</span>
      <?php endif; ?>
      <span style="width:6px;height:6px;border-radius:50%;background:#cbd5e1;display:inline-block;"></span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle($spfOk ? 'pass' : 'fail')); ?>">SPF &middot; <?php echo e($spfDetail); ?></span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle($dkimOk ? 'pass' : ($dkimPartial ? 'warn' : 'fail'))); ?>">DKIM &middot; <?php echo e($dkimDetail); ?></span>
      <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;<?php echo e(chipStyle($dmarcStatus)); ?>">DMARC &middot; <?php echo e($dmarcDetail); ?></span>
    </div>
  </div>

  
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 16px;">Category Breakdown</h2>
    <?php $__currentLoopData = $submission->categoryScores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:12px 0;border-bottom:1px solid #f1f5f9;">
        <div>
          <div style="font-weight:600;font-size:14px;color:#1e293b;"><?php echo e($cat->category_name); ?></div>
          <?php if($cat->rationale): ?>
            <div style="font-size:12px;color:#64748b;margin-top:2px;"><?php echo e($cat->rationale); ?></div>
          <?php endif; ?>
        </div>
        <div style="font-size:22px;font-weight:800;text-align:right;min-width:50px;color:<?php echo e(catScoreColor($cat->score)); ?>;"><?php echo e($cat->score); ?></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px;">Breach History</h2>
    <?php if($submission->breachEvents->isEmpty()): ?>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;color:#15803d;font-size:14px;font-weight:600;">
        ✓ No known breaches found for this email address.
      </div>
    <?php else: ?>
      <?php $__currentLoopData = $submission->breachEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $breach): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $bAttrs = $breach->exposed_attributes_json;
          if (is_string($bAttrs)) { $bAttrs = json_decode($bAttrs, true) ?? []; }
        ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:8px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
            <div style="font-weight:700;font-size:14px;color:#991b1b;"><?php echo e($breach->breach_name); ?></div>
            <?php if($breach->breach_date): ?>
              <div style="font-size:11px;color:#6b7280;background:#fee2e2;padding:2px 8px;border-radius:12px;white-space:nowrap;">
                <?php echo e(\Carbon\Carbon::parse($breach->breach_date)->format('M Y')); ?>

              </div>
            <?php endif; ?>
          </div>
          <?php if(!empty($bAttrs)): ?>
            <div style="font-size:12px;color:#475569;margin-top:6px;">
              <span style="font-weight:600;">Exposed:</span> <?php echo e(implode(', ', (array) $bAttrs)); ?>

            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>
  </div>

  
  <?php if(!empty($uniqueAttrs)): ?>
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 12px;">Data Sensitivity Exposed</h2>
    <p style="font-size:13px;color:#64748b;margin:0 0 12px;">The following data types were found across all breach records:</p>
    <div style="display:flex;flex-wrap:wrap;gap:6px;">
      <?php $__currentLoopData = $uniqueAttrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $high = ['password','ssn','social security','credit card','bank'];
          $med  = ['date of birth','dob','phone','address'];
          $al   = strtolower($attr);
          $attrStyle = collect($high)->contains(fn($h) => str_contains($al,$h))
            ? 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;'
            : (collect($med)->contains(fn($m) => str_contains($al,$m))
              ? 'background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;'
              : 'background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;');
        ?>
        <span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;<?php echo e($attrStyle); ?>"><?php echo e($attr); ?></span>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
  <?php endif; ?>

  
  <?php
    $domainData = $submission->domain_breach_json;
  ?>
  <?php if($domainData): ?>
  <div style="padding:24px 32px;border-bottom:1px solid #e2e8f0;">
    <h2 style="font-size:13px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px;">Domain Security Posture</h2>
    <p style="font-size:12px;color:#94a3b8;margin:0 0 16px;">Breach exposure across all accounts on <?php echo e($submission->domain); ?></p>

    <?php if(!empty($domainData['pending'])): ?>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;color:#92400e;font-size:13px;">
        ⏳ Domain-level breach data is still being indexed. Run a new assessment in a few hours to see the full leaderboard.
      </div>
    <?php elseif(!empty($domainData['quota_exceeded'])): ?>
      
    <?php elseif(empty($domainData['available'])): ?>
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;color:#94a3b8;font-size:13px;">
        Domain breach data unavailable.
      </div>
    <?php elseif(empty($domainData['found'])): ?>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;color:#15803d;font-size:14px;font-weight:600;">
        ✓ No known breaches found for any accounts on <?php echo e($submission->domain); ?>.
      </div>
    <?php else: ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;margin-bottom:10px;">
        <span><?php echo e($domainData['breach_count']); ?> breach(es) affecting this domain</span>
        <span><?php echo e(number_format($domainData['total_exposed'])); ?> account(s) exposed total</span>
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
          <?php $__currentLoopData = ($domainData['top_breaches'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
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
            ?>
            <tr style="background:<?php echo e($rowBg); ?>;border-bottom:1px solid #f1f5f9;">
              <td style="padding:8px 10px;color:#94a3b8;font-size:12px;vertical-align:top;"><?php echo e($idx + 1); ?></td>
              <td style="padding:8px 10px;vertical-align:top;">
                <div style="font-weight:700;color:#1e293b;margin-bottom:4px;"><?php echo e($b['breach']); ?></div>
                <?php $__currentLoopData = ($b['emails'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $email): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <span style="display:inline-block;font-family:monospace;font-size:11px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:1px 6px;border-radius:4px;margin:1px 2px 1px 0;"><?php echo e($email); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </td>
              <td style="padding:8px 10px;color:#64748b;font-size:12px;vertical-align:top;white-space:nowrap;"><?php echo e($bDate); ?></td>
              <td style="padding:8px 10px;vertical-align:top;">
                <?php $__currentLoopData = $shownAttrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php
                    $isPass = str_contains(strtolower($attr), 'password');
                    $tagStyle = $isPass
                      ? 'background:#fef2f2;border:1px solid #fecaca;color:#991b1b;'
                      : 'background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;';
                  ?>
                  <span style="display:inline-block;font-size:11px;padding:1px 6px;border-radius:4px;margin:1px 2px 1px 0;<?php echo e($tagStyle); ?>"><?php echo e($attr); ?></span>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php if($extraAttrs > 0): ?>
                  <span style="font-size:11px;color:#94a3b8;">+<?php echo e($extraAttrs); ?></span>
                <?php endif; ?>
              </td>
              <td style="padding:8px 10px;text-align:right;font-weight:800;font-size:14px;color:<?php echo e($countClr); ?>;vertical-align:top;"><?php echo e(number_format($b['email_count'])); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  
  <div style="background:#eff6ff;padding:24px 32px;text-align:center;">
    <p style="font-size:14px;color:#475569;margin:0 0 12px;">Want expert help fixing these issues? Our team can help you configure SPF, DKIM, and DMARC correctly and monitor for future exposure.</p>
    <a href="<?php echo e(env('DISCOVERY_CALL_URL', 'https://meteortel.com/discovery-call/')); ?>"
       style="display:inline-block;background:#3b82f6;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;">
      Book a Security Review
    </a>
  </div>

  
  <div style="padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;">
    <p style="margin:0;">This report was generated by the MeteorTel Email Exposure Assessment tool.<br />
    Your data is handled per our privacy policy. This report was sent to <?php echo e($submission->email); ?>.</p>
    <p style="margin-top:8px;color:#cbd5e1;">© <?php echo e(date('Y')); ?> MeteorTel Security. All rights reserved.</p>
  </div>

</div>
</body>
</html>
<?php /**PATH /home/tfernandez/git/cyber-score/resources/views/email/report.blade.php ENDPATH**/ ?>