<?php
/**
 * Shared Form Checker Engine
 * Used by: accreditation, volunteer, scholarship
 */

// ── Generic field checker ─────────────────────────────────
function checkFields(array $data, array $rules): array {
    $issues = [];
    $passed = [];

    foreach ($rules as $field => $rule) {
        $val = trim($data[$field] ?? '');
        $ok  = true;
        $msg = '';

        if (!empty($rule['required']) && $val === '') {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' is required.';
        } elseif ($val !== '' && !empty($rule['min_len']) && strlen($val) < $rule['min_len']) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' is too short (min ' . $rule['min_len'] . ' characters).';
        } elseif ($val !== '' && !empty($rule['min']) && (float)$val < $rule['min']) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' must be at least ' . $rule['min'] . '.';
        } elseif ($val !== '' && !empty($rule['max']) && (float)$val > $rule['max']) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' must be at most ' . $rule['max'] . '.';
        } elseif ($val !== '' && !empty($rule['type']) && $rule['type'] === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' must be a valid email address.';
        } elseif ($val !== '' && !empty($rule['type']) && $rule['type'] === 'date' && !strtotime($val)) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' must be a valid date.';
        } elseif ($val !== '' && !empty($rule['future']) && strtotime($val) <= time()) {
            $ok  = false;
            $msg = ($rule['label'] ?? $field) . ' must be a future date.';
        }

        if ($ok && $val !== '') {
            $passed[] = $rule['label'] ?? $field;
        } elseif (!$ok) {
            $issues[] = ['field' => $field, 'label' => $rule['label'] ?? $field, 'message' => $msg];
        }
    }

    return ['issues' => $issues, 'passed' => $passed];
}

// ── File checker ──────────────────────────────────────────
function checkFiles(array $files, array $required, array $sessionFiles = []): array {
    $issues   = [];
    $warnings = [];
    $uploaded = [];
    $allowed  = ['pdf','doc','docx','jpg','jpeg','png'];

    foreach ($required as $type => $label) {
        $fromSession = isset($sessionFiles[$type]) && file_exists($sessionFiles[$type]['tmp_path'] ?? '');
        $fromPost    = isset($files[$type]) && ($files[$type]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        if (!$fromSession && !$fromPost) {
            $issues[] = ['field' => $type, 'label' => $label, 'message' => "Missing required document: <strong>$label</strong>"];
            continue;
        }

        if ($fromPost) {
            $ext  = strtolower(pathinfo($files[$type]['name'], PATHINFO_EXTENSION));
            $size = $files[$type]['size'];
            if (!in_array($ext, $allowed, true)) {
                $issues[] = ['field' => $type, 'label' => $label, 'message' => "<strong>$label</strong>: Invalid file type '.$ext'. Allowed: PDF, DOC, DOCX, JPG, PNG."];
                continue;
            }
            if ($size > 10 * 1024 * 1024) {
                $issues[] = ['field' => $type, 'label' => $label, 'message' => "<strong>$label</strong>: File too large (" . round($size/1024/1024, 1) . "MB). Max 10MB."];
                continue;
            }
        }

        $uploaded[] = $type;
    }

    return ['issues' => $issues, 'warnings' => $warnings, 'uploaded' => $uploaded];
}

// ── Score calculator ──────────────────────────────────────
function calcScore(array $fieldResult, array $fileResult, int $totalFields, int $totalFiles): array {
    $fieldScore = $totalFields > 0 ? round((count($fieldResult['passed']) / $totalFields) * 60) : 60;
    $fileScore  = $totalFiles  > 0 ? round((count($fileResult['uploaded']) / $totalFiles) * 40) : 40;
    $total      = $fieldScore + $fileScore;

    $rating = match(true) {
        $total >= 90 => ['Ready to Submit', '#2e7d32', 'fa-check-circle', true],
        $total >= 70 => ['Almost Ready',    '#1565c0', 'fa-info-circle',  false],
        $total >= 50 => ['Needs More Info', '#f57f17', 'fa-exclamation-triangle', false],
        default      => ['Incomplete',      '#c62828', 'fa-times-circle', false],
    };

    return [
        'score'      => $total,
        'color'      => $rating[0],
        'label'      => $rating[0],
        'icon'       => $rating[2],
        'can_submit' => $rating[3] && empty($fileResult['issues']),
    ];
}

// ── Render check panel (HTML) ─────────────────────────────
function renderCheckPanel(array $fieldResult, array $fileResult, array $scoreResult, array $requiredFiles): string {
    $score = $scoreResult['score'];
    $color = match(true) { $score >= 90 => '#2e7d32', $score >= 70 => '#1565c0', $score >= 50 => '#f57f17', default => '#c62828' };
    $canSubmit = $scoreResult['can_submit'];

    ob_start(); ?>
    <div style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:16px">
      <!-- Header -->
      <div style="padding:12px 18px;background:linear-gradient(135deg,#0d3b6e,#1565c0);color:#fff;display:flex;align-items:center;justify-content:space-between">
        <span style="font-weight:700;font-size:.9rem"><i class="fas fa-robot" style="margin-right:7px"></i>Submission Check</span>
        <span style="background:<?=$color?>;color:#fff;padding:3px 12px;border-radius:50px;font-size:.78rem;font-weight:700">
          <i class="fas fa-<?=$scoreResult['icon']?>"></i> <?=$scoreResult['label']?> — <?=$score?>%
        </span>
      </div>

      <!-- Score bar -->
      <div style="padding:12px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc">
        <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#475569;margin-bottom:5px">
          <span>Completeness</span><span style="font-weight:700;color:<?=$color?>"><?=$score?>%</span>
        </div>
        <div style="height:9px;background:#e2e8f0;border-radius:50px;overflow:hidden">
          <div style="height:100%;width:<?=$score?>%;background:<?=$color?>;border-radius:50px;transition:width .6s ease"></div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
        <!-- Field check -->
        <div style="padding:14px 18px;border-right:1px solid #e2e8f0">
          <div style="font-size:.78rem;font-weight:700;color:#1e293b;margin-bottom:10px"><i class="fas fa-list-check" style="color:#1565c0;margin-right:5px"></i>Form Fields</div>
          <?php if (empty($fieldResult['issues'])): ?>
            <div style="color:#2e7d32;font-size:.82rem"><i class="fas fa-check-circle"></i> All required fields filled</div>
          <?php else: foreach ($fieldResult['issues'] as $issue): ?>
            <div style="display:flex;align-items:flex-start;gap:6px;padding:3px 0;font-size:.8rem;color:#c62828">
              <i class="fas fa-times-circle" style="margin-top:2px;flex-shrink:0"></i>
              <span><?=$issue['message']?></span>
            </div>
          <?php endforeach; endif; ?>
          <?php if (!empty($fieldResult['passed'])): ?>
            <div style="margin-top:8px;font-size:.72rem;color:#2e7d32">
              <strong>✓ Complete:</strong> <?=implode(', ', array_slice($fieldResult['passed'], 0, 5))?><?=count($fieldResult['passed'])>5?' +'.( count($fieldResult['passed'])-5).' more':'';?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Document check -->
        <div style="padding:14px 18px">
          <div style="font-size:.78rem;font-weight:700;color:#1e293b;margin-bottom:10px"><i class="fas fa-paperclip" style="color:#1565c0;margin-right:5px"></i>Documents</div>
          <?php foreach ($requiredFiles as $type => $label):
            $hasIssue = array_filter($fileResult['issues'], fn($i) => $i['field'] === $type);
            $uploaded = in_array($type, $fileResult['uploaded']);
          ?>
          <div style="display:flex;align-items:center;gap:7px;padding:3px 0;font-size:.8rem">
            <?php if ($hasIssue): ?>
              <i class="fas fa-times-circle" style="color:#c62828;width:14px"></i>
              <span style="color:#c62828"><?=$label?></span>
            <?php elseif ($uploaded): ?>
              <i class="fas fa-check-circle" style="color:#2e7d32;width:14px"></i>
              <span style="color:#2e7d32"><?=$label?></span>
            <?php else: ?>
              <i class="fas fa-circle" style="color:#94a3b8;width:14px;font-size:.4rem"></i>
              <span style="color:#94a3b8"><?=$label?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Result bar -->
      <?php if ($canSubmit): ?>
      <div style="padding:10px 18px;background:#e8f5e9;font-size:.83rem;color:#2e7d32;border-top:1px solid #e2e8f0;font-weight:600">
        <i class="fas fa-check-circle"></i> <strong>Ready to submit!</strong> All checks passed.
      </div>
      <?php else: ?>
      <div style="padding:10px 18px;background:#ffebee;font-size:.83rem;color:#c62828;border-top:1px solid #e2e8f0;font-weight:600">
        <i class="fas fa-lock"></i> <strong>Not ready.</strong> Fix the issues above before submitting.
      </div>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
