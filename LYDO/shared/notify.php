<?php
/**
 * Notification helper — send to one user or broadcast to all
 */

function notifyUser(PDO $pdo, int $userId, string $type, string $message, string $title = '', string $link = '', string $category = 'general'): void {
    $pdo->prepare(
        'INSERT INTO notifications (user_id,type,title,category,message,link,is_read,created_at) VALUES (?,?,?,?,?,?,0,NOW())'
    )->execute([$userId, $type, $title ?: null, $category, $message, $link ?: null]);
}

function notifyAllYouth(PDO $pdo, string $title, string $message, string $category = 'general', string $link = ''): int {
    $users = $pdo->query("SELECT id FROM youth_users WHERE status='approved'")->fetchAll(PDO::FETCH_COLUMN);
    $stmt  = $pdo->prepare(
        'INSERT INTO notifications (user_id,type,title,category,message,link,is_read,created_at) VALUES (?,?,?,?,?,?,0,NOW())'
    );
    foreach ($users as $uid) {
        $stmt->execute([$uid, 'broadcast', $title, $category, $message, $link ?: null]);
    }
    return count($users);
}

function notifyApprovalUpdate(PDO $pdo, int $userId, string $status, string $type, string $link = ''): void {
    $statusLabels = [
        'approved'   => 'approved',
        'rejected'   => 'rejected',
        'pending'    => 'received and is pending review',
        'under_review' => 'now under review',
        'for_exam'   => 'scheduled for examination',
        'passed'     => 'passed the examination',
        'failed'     => 'did not pass the examination',
        'completed'  => 'marked as completed',
        'beneficiary'=> 'approved — you are now a beneficiary',
    ];
    $typeLabels = [
        'scholarship' => 'Scholarship application',
        'accreditation' => 'Accreditation application',
        'assistance'  => 'Assistance request',
        'volunteer'   => 'Volunteer registration',
        'approval'    => 'Registration',
    ];
    $label  = $typeLabels[$type]  ?? 'Your application';
    $sLabel = $statusLabels[$status] ?? $status;
    $title  = ucfirst($type) . ' Status Update';
    $msg    = "$label has been $sLabel.";
    notifyUser($pdo, $userId, 'approval', $msg, $title, $link, 'approval');
}
