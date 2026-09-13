-- ============================================================
-- Add Organization President Role to LYDO System
-- Database: local_youth_development_db
-- Import this file via phpMyAdmin or run via MySQL CLI
-- ============================================================

USE local_youth_development_db;

-- ============================================================
-- STEP 1: Create organization_presidents table
-- ============================================================

CREATE TABLE IF NOT EXISTS organization_presidents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    email           VARCHAR(191) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    full_name       VARCHAR(200) NOT NULL,
    contact_number  VARCHAR(20) DEFAULT NULL,
    term_start      DATE DEFAULT NULL,
    term_end        DATE DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login      DATETIME DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_org_president (organization_id),
    INDEX idx_org_pres_user (user_id),
    INDEX idx_org_pres_active (is_active),
    INDEX idx_org_pres_email (email),
    
    CONSTRAINT fk_org_pres_org FOREIGN KEY (organization_id) 
        REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_pres_user FOREIGN KEY (user_id) 
        REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 2: Create organization_president_sessions table
-- ============================================================

CREATE TABLE IF NOT EXISTS organization_president_sessions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    president_id INT UNSIGNED NOT NULL,
    token        VARCHAR(64) NOT NULL UNIQUE,
    expires_at   DATETIME NOT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_org_pres_session_token (token),
    INDEX idx_org_pres_session_expires (expires_at),
    
    CONSTRAINT fk_org_pres_session FOREIGN KEY (president_id) 
        REFERENCES organization_presidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 3: Create organization_president_activity_log table
-- ============================================================

CREATE TABLE IF NOT EXISTS organization_president_activity_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    president_id INT UNSIGNED NOT NULL,
    action       VARCHAR(255) NOT NULL,
    details      TEXT DEFAULT NULL,
    ip_address   VARCHAR(45) DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_org_pres_log_date (created_at),
    INDEX idx_org_pres_log_president (president_id),
    
    CONSTRAINT fk_org_pres_log FOREIGN KEY (president_id) 
        REFERENCES organization_presidents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STEP 4: Add president_id to organizations table (if not exists)
-- ============================================================

-- Check if column exists before adding (MySQL will error if column exists)
-- Run this carefully or check manually first

ALTER TABLE organizations 
ADD COLUMN IF NOT EXISTS president_id INT UNSIGNED DEFAULT NULL AFTER id;

-- Add index for president_id
CREATE INDEX IF NOT EXISTS idx_org_president ON organizations(president_id);

-- ============================================================
-- EXAMPLE: Create a demo organization president
-- ============================================================

-- Example: Create president for organization ID 1
-- Note: Replace with actual data. Password is 'President@123' hashed
/*
INSERT INTO organization_presidents 
(organization_id, user_id, email, password, full_name, contact_number, term_start, is_active)
VALUES 
(1, 1, 'president1@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Dela Cruz', '09171234567', '2024-01-01', 1);
*/

-- ============================================================
-- VERIFICATION QUERIES
-- ============================================================

-- Check if tables were created
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'local_youth_development_db' 
AND TABLE_NAME LIKE 'organization_president%';

-- Check organizations table structure
DESCRIBE organizations;

-- Count organization presidents
SELECT COUNT(*) as total_presidents FROM organization_presidents;

-- List all presidents with their organizations
SELECT 
    op.id,
    op.full_name,
    op.email,
    o.name as organization_name,
    op.term_start,
    op.term_end,
    op.is_active,
    op.last_login
FROM organization_presidents op
JOIN organizations o ON o.id = op.organization_id
ORDER BY op.created_at DESC;

-- ============================================================
-- NOTES
-- ============================================================
-- 
-- 1. Each organization can have only ONE active president at a time
-- 2. President must be a registered youth_users member
-- 3. President uses separate email/password for president portal
-- 4. All president actions are logged in activity_log table
-- 5. Foreign keys ensure data integrity
-- 
-- Access URLs:
-- - President Login: /LYDO/lydo-system/org-president/login.php
-- - President Dashboard: /LYDO/lydo-system/org-president/dashboard.php
-- - Admin Manage Presidents: /LYDO/lydo-system/admin2/org_presidents.php
-- 
-- ============================================================
