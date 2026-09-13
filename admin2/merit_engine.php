<?php
/**
 * MYDC Merit & Demerit Engine — Organization-based
 * Points are tracked per YOUTH ORGANIZATION, not per individual.
 */

// ── MERIT RULES (per MYDC Resolution) ────────────────────
const MERIT_RULES = [
    // Merit
    'official_event_present' => ['points' =>  2, 'type' => 'merit',   'label' => 'Attendance in Official MYDC/LYDO Event'],
    'meeting_present'        => ['points' =>  1, 'type' => 'merit',   'label' => 'Attendance in Patawag/Meeting'],
    'requirements_on_time'   => ['points' =>  1, 'type' => 'merit',   'label' => 'Submission of complete requirements on time'],
    'representative_sent'    => ['points' =>  1, 'type' => 'merit',   'label' => 'Sending official representative'],
    'volunteer_contribution' => ['points' =>  2, 'type' => 'merit',   'label' => 'Extra contribution/volunteer support'],
    'invitation_present'     => ['points' =>  1, 'type' => 'merit',   'label' => 'Attendance in fellow organization invitation'],
    // Demerit
    'official_event_absent'  => ['points' => -2, 'type' => 'demerit', 'label' => 'Unexcused absence in Official Event'],
    'meeting_absent'         => ['points' => -1, 'type' => 'demerit', 'label' => 'Unexcused absence in Meeting/Patawag'],
    'no_representative'      => ['points' => -2, 'type' => 'demerit', 'label' => 'No representative sent without valid reason'],
    'late_submission'        => ['points' => -1, 'type' => 'demerit', 'label' => 'Late submission of requirements'],
    'consecutive_absences'   => ['points' => -3, 'type' => 'demerit', 'label' => '3 consecutive absences without coordination'],
];

// ── CONSEQUENCE THRESHOLDS ────────────────────────────────
const DEMERIT_THRESHOLDS = [
     5 => ['level' => 'warning',          'label' => 'Warning Letter'],
     7 => ['level' => 'show_cause',       'label' => 'Show Cause Order'],
    10 => ['level' => 'revocation_flag',  'label' => 'Membership Revocation Flag'],
];

/**
 * Award or deduct points for an ORGANIZATION, then check consequences.
 */
function awardOrgPoints(PDO $pdo, int $orgId, string $ruleKey, int $adminId = 0, ?int $eventId = null): array {
    if (!isset(MERIT_RULES[$ruleKey])) {
        return ['success' => false, 'message' => 'Unknown rule key.'];
    }
    $rule = MERIT_RULES[$ruleKey];

    $pdo->prepare(
        'INSERT INTO org_merit_logs (organization_id, points, type, reason, category, event_id, awarded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$orgId, $rule['points'], $rule['type'], $rule['label'], $ruleKey, $eventId, $adminId ?: null]);

    $consequences = [];
    if ($rule['type'] === 'demerit') {
        $consequences = checkOrgConsequences($pdo, $orgId, $adminId);
    }

    return ['success' => true, 'points' => $rule['points'], 'consequences' => $consequences];
}

/**
 * Get total demerit points for an organization.
 */
function getOrgDemerits(PDO $pdo, int $orgId): int {
    $s = $pdo->prepare("SELECT COALESCE(SUM(ABS(points)),0) FROM org_merit_logs WHERE organization_id=? AND type='demerit'");
    $s->execute([$orgId]);
    return (int)$s->fetchColumn();
}

/**
 * Get total merit points for an organization.
 */
function getOrgMerits(PDO $pdo, int $orgId): int {
    $s = $pdo->prepare("SELECT COALESCE(SUM(points),0) FROM org_merit_logs WHERE organization_id=? AND type='merit'");
    $s->execute([$orgId]);
    return (int)$s->fetchColumn();
}

/**
 * Get net score for an organization.
 */
function getOrgNetScore(PDO $pdo, int $orgId): int {
    $s = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM org_merit_logs WHERE organization_id=?');
    $s->execute([$orgId]);
    return (int)$s->fetchColumn();
}

/**
 * Check demerit total and auto-generate consequences if thresholds are crossed.
 * Each level is only issued once.
 * Automatically notifies organization president.
 */
function checkOrgConsequences(PDO $pdo, int $orgId, int $adminId = 0): array {
    $total     = getOrgDemerits($pdo, $orgId);
    $triggered = [];

    // Get organization details
    $orgStmt = $pdo->prepare('SELECT name, president_id FROM organizations WHERE id=?');
    $orgStmt->execute([$orgId]);
    $org = $orgStmt->fetch(PDO::FETCH_ASSOC);

    foreach (DEMERIT_THRESHOLDS as $threshold => $consequence) {
        if ($total >= $threshold) {
            $exists = $pdo->prepare('SELECT id FROM org_warning_letters WHERE organization_id=? AND level=? LIMIT 1');
            $exists->execute([$orgId, $consequence['level']]);
            if (!$exists->fetch()) {
                $reason = "Auto-generated: Total demerit points reached {$total}. "
                        . "Threshold: {$threshold} pts → {$consequence['label']}.";
                
                // Insert warning letter
                $pdo->prepare('INSERT INTO org_warning_letters (organization_id,reason,level,issued_by,issued_at) VALUES (?,?,?,?,NOW())')
                    ->execute([$orgId, $reason, $consequence['level'], $adminId ?: null]);
                
                $triggered[] = $consequence['label'];

                // Send automatic notification to organization president
                if ($org && $org['president_id']) {
                    $notifTitle = getConsequenceNotificationTitle($consequence['level']);
                    $notifMessage = getConsequenceNotificationMessage($org['name'], $consequence['label'], $total, $threshold);
                    
                    sendOrgPresidentNotification(
                        $pdo,
                        $org['president_id'],
                        $notifTitle,
                        $notifMessage,
                        'warning',
                        "/lydo-system/org-president/warnings.php"
                    );
                }
            }
        }
    }
    return $triggered;
}

/**
 * Get notification title based on consequence level
 */
function getConsequenceNotificationTitle(string $level): string {
    return match($level) {
        'warning' => '⚠️ Warning Letter Issued',
        'show_cause' => '🚨 Show Cause Order',
        'revocation_flag' => '⛔ Membership Revocation Flag',
        default => '⚠️ Consequence Notice'
    };
}

/**
 * Get notification message based on consequence
 */
function getConsequenceNotificationMessage(string $orgName, string $consequenceLabel, int $totalPoints, int $threshold): string {
    $urgency = match($totalPoints) {
        5, 6 => 'requires your immediate attention',
        7, 8, 9 => 'is critical and requires urgent action',
        default => 'has reached a critical level'
    };
    
    return "Your organization '{$orgName}' has received a {$consequenceLabel}. "
         . "Total demerit points: {$totalPoints} (Threshold: {$threshold}). "
         . "This {$urgency}. Please review the warning details and take appropriate action to address the issues raised.";
}

/**
 * Send notification to organization president
 */
function sendOrgPresidentNotification(PDO $pdo, int $presidentId, string $title, string $message, string $type = 'info', string $link = null): bool {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type, link, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        return $stmt->execute([$presidentId, $title, $message, $type, $link]);
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Process event attendance for all organizations and auto-apply rules.
 * $attendanceMap = [ org_id => 'present'|'absent'|'excused'|'late'|'representative' ]
 */
function processEventAttendance(PDO $pdo, int $eventId, array $attendanceMap, int $adminId = 0): array {
    $ev = $pdo->prepare('SELECT * FROM events WHERE id=?');
    $ev->execute([$eventId]);
    $event = $ev->fetch();
    if (!$event) return ['success' => false, 'message' => 'Event not found.'];

    $results = [];

    foreach ($attendanceMap as $orgId => $status) {
        $orgId = (int)$orgId;

        // Upsert attendance record
        $pdo->prepare(
            'INSERT INTO event_attendance (event_id, organization_id, status)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE status=VALUES(status)'
        )->execute([$eventId, $orgId, $status]);

        // Map status + event type → rule key
        $ruleKey = null;
        if ($status === 'present') {
            $ruleKey = match($event['event_type']) {
                'official_event'  => 'official_event_present',
                'meeting_patawag' => 'meeting_present',
                'invitation'      => 'invitation_present',
                default           => 'official_event_present',
            };
        } elseif ($status === 'representative') {
            $ruleKey = 'representative_sent';
        } elseif ($status === 'absent') {
            $ruleKey = match($event['event_type']) {
                'official_event'  => 'official_event_absent',
                'meeting_patawag' => 'meeting_absent',
                default           => 'official_event_absent',
            };
        } elseif ($status === 'late') {
            // Late = present, no demerit
            $ruleKey = match($event['event_type']) {
                'official_event'  => 'official_event_present',
                'meeting_patawag' => 'meeting_present',
                default           => 'official_event_present',
            };
        }
        // 'excused' = no points

        if ($ruleKey) {
            $result = awardOrgPoints($pdo, $orgId, $ruleKey, $adminId, $eventId);
            $results[$orgId] = $result;
        }

        // Check 3 consecutive absences
        if ($status === 'absent') {
            checkConsecutiveOrgAbsences($pdo, $orgId, $adminId);
        }
    }

    return ['success' => true, 'results' => $results];
}

/**
 * Check if an organization has 3 consecutive unexcused absences.
 */
function checkConsecutiveOrgAbsences(PDO $pdo, int $orgId, int $adminId = 0): void {
    $s = $pdo->prepare(
        'SELECT ea.status FROM event_attendance ea
         JOIN events e ON e.id = ea.event_id
         WHERE ea.organization_id=?
         ORDER BY e.event_date DESC LIMIT 3'
    );
    $s->execute([$orgId]);
    $recent = $s->fetchAll(PDO::FETCH_COLUMN);

    if (count($recent) === 3 && count(array_unique($recent)) === 1 && $recent[0] === 'absent') {
        // Only apply once per 7-day window
        $check = $pdo->prepare(
            'SELECT id FROM org_merit_logs
             WHERE organization_id=? AND category="consecutive_absences"
             AND created_at >= CURRENT_TIMESTAMP - INTERVAL \'7 days\' LIMIT 1'
        );
        $check->execute([$orgId]);
        if (!$check->fetch()) {
            awardOrgPoints($pdo, $orgId, 'consecutive_absences', $adminId);
        }
    }
}
