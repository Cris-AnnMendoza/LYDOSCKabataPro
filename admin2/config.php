<?php
require_once __DIR__ . '/../shared/config.php';

// ── Admin auth helpers ────────────────────────────────────
function isLoggedIn(): bool {
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /LYDO/lydo-system/login.php');
        exit;
    }
}

function currentAdmin(): array {
    return $_SESSION['admin'] ?? [];
}

function hasPermission(string $perm): bool {
    $role = $_SESSION['admin']['role'] ?? $_SESSION['org_president']['role'] ?? '';
    
    // Organization President permissions
    if ($role === 'organization_president') {
        $orgPresPerms = [
            'view_own_org',
            'manage_own_members',
            'view_own_events',
            'create_events',
            'view_own_reports',
            'manage_attendance',
            'view_merit_points'
        ];
        return in_array($perm, $orgPresPerms, true);
    }
    
    // Admin permissions
    $map  = [
        'super_admin'       => ['manage_admins','view_users','edit_users','delete_users','view_reports','manage_programs','manage_events','view_logs','manage_organizations','manage_presidents'],
        'youth_coordinator' => ['view_users','edit_users','view_reports','manage_programs','manage_events'],
        'barangay_admin'    => ['view_users','edit_users','manage_events','view_reports'],
        'staff_encoder'     => ['view_users','edit_users'],
    ];
    return in_array($perm, $map[$role] ?? [], true);
}

function roleBadge(string $role): string {
    $map = [
        'super_admin'            => ['#f57f17','#fff8e1','Super Admin'],
        'youth_coordinator'      => ['#1565c0','#e3f2fd','Youth Coordinator'],
        'barangay_admin'         => ['#2e7d32','#e8f5e9','Barangay Admin'],
        'staff_encoder'          => ['#475569','#f1f5f9','Staff Encoder'],
        'organization_president' => ['#7b1fa2','#f3e5f5','Organization President'],
    ];
    [$color,$bg,$label] = $map[$role] ?? ['#475569','#f1f5f9',$role];
    return "<span style='background:$bg;color:$color;padding:3px 10px;border-radius:50px;font-size:.72rem;font-weight:700;'>$label</span>";
}

// Check if user is organization president
function isOrgPresident(): bool {
    return !empty($_SESSION['org_president_id']);
}

function currentOrgPresident(): array {
    return $_SESSION['org_president'] ?? [];
}
