-- ============================================================
-- Create All Missing Tables for LYDO System
-- Run this in phpMyAdmin if the PHP script doesn't work
-- ============================================================

USE local_youth_development_db;

-- ============================================================
-- ASSISTANCE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS assistance_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submitted_by INT UNSIGNED NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    amount_requested DECIMAL(10,2) DEFAULT NULL,
    supporting_documents VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','under_review','approved','rejected','completed','declined') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER MERIT LOGS (Individual merit points)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_merit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    points SMALLINT NOT NULL,
    type ENUM('merit','demerit') NOT NULL,
    reason VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    awarded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VOLUNTEER APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS volunteer_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    program_name VARCHAR(200) NOT NULL,
    availability TEXT DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    motivation TEXT NOT NULL,
    experience TEXT DEFAULT NULL,
    hours_per_week TINYINT UNSIGNED DEFAULT NULL,
    preferred_activities TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected','active','completed') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SCHOLARSHIP APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS scholarship_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    scholarship_type VARCHAR(100) NOT NULL,
    school_name VARCHAR(200) NOT NULL,
    course VARCHAR(150) NOT NULL,
    year_level VARCHAR(50) NOT NULL,
    gpa DECIMAL(3,2) DEFAULT NULL,
    financial_need TEXT NOT NULL,
    achievements TEXT DEFAULT NULL,
    essay TEXT DEFAULT NULL,
    supporting_documents TEXT DEFAULT NULL,
    status ENUM('pending','under_review','shortlisted','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PROGRAMS
-- ============================================================
CREATE TABLE IF NOT EXISTS programs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    objectives TEXT DEFAULT NULL,
    target_participants VARCHAR(200) DEFAULT NULL,
    duration VARCHAR(100) DEFAULT NULL,
    status ENUM('active','inactive','upcoming','completed') NOT NULL DEFAULT 'active',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    coordinator_name VARCHAR(150) DEFAULT NULL,
    coordinator_contact VARCHAR(50) DEFAULT NULL,
    max_participants INT UNSIGNED DEFAULT NULL,
    requirements TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','error','announcement') NOT NULL DEFAULT 'info',
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id INT UNSIGNED DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ANNOUNCEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    target_audience ENUM('all','youth','organizations','barangay_specific') NOT NULL DEFAULT 'all',
    target_barangay VARCHAR(100) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    publish_date DATETIME DEFAULT NULL,
    expire_date DATETIME DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published (is_published, publish_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACCREDITATION APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS accreditation_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    application_type ENUM('new','renewal') NOT NULL DEFAULT 'new',
    officers_list TEXT NOT NULL,
    members_count INT UNSIGNED NOT NULL,
    constitution_bylaws VARCHAR(255) DEFAULT NULL,
    action_plan TEXT DEFAULT NULL,
    financial_statement VARCHAR(255) DEFAULT NULL,
    other_documents TEXT DEFAULT NULL,
    status ENUM('pending','under_review','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONE!
-- ============================================================
