-- ============================================================
-- Local Youth Development Sta. Cruz Laguna System
-- Database: local_youth_development_db
-- Import this file via phpMyAdmin or MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS local_youth_development_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE local_youth_development_db;

CREATE TABLE IF NOT EXISTS youth_users (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name            VARCHAR(100)  NOT NULL,
  middle_name           VARCHAR(100)  DEFAULT NULL,
  last_name             VARCHAR(100)  NOT NULL,
  suffix                VARCHAR(20)   DEFAULT NULL,
  gender                VARCHAR(30)   NOT NULL,
  birthdate             DATE          NOT NULL,
  age                   TINYINT UNSIGNED NOT NULL,
  civil_status          VARCHAR(30)   NOT NULL,
  contact_number        VARCHAR(20)   NOT NULL,
  email                 VARCHAR(191)  NOT NULL UNIQUE,
  password              VARCHAR(255)  NOT NULL,
  house_number          VARCHAR(100)  DEFAULT NULL,
  street                VARCHAR(150)  DEFAULT NULL,
  barangay              VARCHAR(100)  NOT NULL,
  municipality          VARCHAR(100)  NOT NULL DEFAULT 'Sta. Cruz',
  province              VARCHAR(100)  NOT NULL DEFAULT 'Laguna',
  zip_code              VARCHAR(10)   NOT NULL DEFAULT '4009',
  youth_classification  TEXT          DEFAULT NULL,
  educational_status    VARCHAR(100)  DEFAULT NULL,
  school_name           VARCHAR(200)  DEFAULT NULL,
  course_or_grade       VARCHAR(150)  DEFAULT NULL,
  employment_status     VARCHAR(100)  DEFAULT NULL,
  organization_name     VARCHAR(200)  DEFAULT NULL,
  organization_type     VARCHAR(100)  DEFAULT NULL,
  organization_role     VARCHAR(100)  DEFAULT NULL,
  years_membership      TINYINT UNSIGNED DEFAULT 0,
  skills                TEXT          DEFAULT NULL,
  interests             TEXT          DEFAULT NULL,
  programs_interested   TEXT          DEFAULT NULL,
  volunteer_availability VARCHAR(100) DEFAULT NULL,
  valid_id              VARCHAR(255)  DEFAULT NULL,
  profile_picture       VARCHAR(255)  DEFAULT NULL,
  created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ADMIN TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name    VARCHAR(150) NOT NULL,
  email        VARCHAR(191) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         ENUM('super_admin','youth_coordinator','barangay_admin','staff_encoder') NOT NULL DEFAULT 'staff_encoder',
  barangay     VARCHAR(100) DEFAULT NULL,   -- for barangay_admin scope
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  last_login   DATETIME     DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default super admin  (password: Admin@1234)
INSERT INTO admin_users (full_name, email, password, role) VALUES
('Super Administrator', 'admin@lydo.gov.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

CREATE TABLE IF NOT EXISTS admin_sessions (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT UNSIGNED NOT NULL,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  expires_at DATETIME     NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_activity_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   INT UNSIGNED NOT NULL,
  action     VARCHAR(255) NOT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MERIT & DEMERIT SYSTEM + ORGANIZATIONS
-- ============================================================

-- Organizations
CREATE TABLE IF NOT EXISTS organizations (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(200) NOT NULL,
  category      VARCHAR(100) DEFAULT NULL,
  description   TEXT         DEFAULT NULL,
  adviser_name  VARCHAR(150) DEFAULT NULL,
  adviser_email VARCHAR(191) DEFAULT NULL,
  adviser_phone VARCHAR(30)  DEFAULT NULL,
  barangay      VARCHAR(100) DEFAULT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link youth users to organizations
CREATE TABLE IF NOT EXISTS organization_members (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  role            VARCHAR(100) DEFAULT 'Member',
  joined_at       DATE         DEFAULT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  UNIQUE KEY uq_org_user (organization_id, user_id),
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES youth_users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events / Activities
CREATE TABLE IF NOT EXISTS events (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(200) NOT NULL,
  description     TEXT         DEFAULT NULL,
  event_date      DATE         NOT NULL,
  event_time      TIME         DEFAULT NULL,
  location        VARCHAR(200) DEFAULT NULL,
  organization_id INT UNSIGNED DEFAULT NULL,
  merit_points    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  quarter         TINYINT UNSIGNED DEFAULT NULL,
  year            YEAR         DEFAULT NULL,
  created_by      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance per event
CREATE TABLE IF NOT EXISTS event_attendance (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  status     ENUM('present','absent','excused','late') NOT NULL DEFAULT 'present',
  remarks    VARCHAR(255) DEFAULT NULL,
  recorded_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_user (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merit points log
CREATE TABLE IF NOT EXISTS merit_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  points      SMALLINT     NOT NULL,          -- positive = merit, negative = demerit
  type        ENUM('merit','demerit')  NOT NULL,
  reason      VARCHAR(255) NOT NULL,
  category    VARCHAR(100) DEFAULT NULL,      -- attendance, volunteer, document, etc.
  reference_id INT UNSIGNED DEFAULT NULL,     -- event_id or other ref
  awarded_by  INT UNSIGNED DEFAULT NULL,      -- admin_user id
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warning letters
CREATE TABLE IF NOT EXISTS warning_letters (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  reason      TEXT         NOT NULL,
  level       ENUM('warning','final_warning','revocation') NOT NULL DEFAULT 'warning',
  issued_by   INT UNSIGNED DEFAULT NULL,
  issued_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explanation letters submitted by youth
CREATE TABLE IF NOT EXISTS explanation_letters (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  content     TEXT         NOT NULL,
  status      ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME     DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
