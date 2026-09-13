-- ============================================================
-- Event QR Check-in & Certificate System Migration
-- Run this in phpMyAdmin on local_youth_development_db
-- ============================================================

USE local_youth_development_db;

-- Add missing columns to events table
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS event_type         VARCHAR(50)  NOT NULL DEFAULT 'official_event' AFTER description,
  ADD COLUMN IF NOT EXISTS requires_representative TINYINT(1) NOT NULL DEFAULT 0 AFTER event_type,
  ADD COLUMN IF NOT EXISTS qr_token           VARCHAR(64)  DEFAULT NULL AFTER requires_representative,
  ADD COLUMN IF NOT EXISTS qr_code_path       VARCHAR(255) DEFAULT NULL AFTER qr_token,
  ADD COLUMN IF NOT EXISTS checkin_open       TINYINT(1)   NOT NULL DEFAULT 1 AFTER qr_code_path,
  ADD UNIQUE KEY IF NOT EXISTS uq_qr_token (qr_token);

-- Fix event_attendance to support both org-based and individual check-ins
ALTER TABLE event_attendance
  ADD COLUMN IF NOT EXISTS organization_id INT UNSIGNED DEFAULT NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS checked_in_at   TIMESTAMP   DEFAULT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS certificate_uploaded TINYINT(1) NOT NULL DEFAULT 0 AFTER checked_in_at;

-- Individual event check-ins (QR scan by youth)
CREATE TABLE IF NOT EXISTS event_checkins (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  checked_in_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address      VARCHAR(45)  DEFAULT NULL,
  UNIQUE KEY uq_event_user_checkin (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Certificates generated after check-in
CREATE TABLE IF NOT EXISTS event_certificates (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id        INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  cert_number     VARCHAR(50)  NOT NULL UNIQUE,
  generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_at     TIMESTAMP    DEFAULT NULL,
  upload_filename VARCHAR(255) DEFAULT NULL,
  upload_original VARCHAR(255) DEFAULT NULL,
  merit_awarded   TINYINT(1)   NOT NULL DEFAULT 0,
  merit_points    TINYINT      NOT NULL DEFAULT 2,
  UNIQUE KEY uq_event_user_cert (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual merit logs (separate from org-based)
CREATE TABLE IF NOT EXISTS user_merit_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  points      SMALLINT     NOT NULL,
  type        ENUM('merit','demerit') NOT NULL,
  reason      VARCHAR(255) NOT NULL,
  category    VARCHAR(100) DEFAULT NULL,
  event_id    INT UNSIGNED DEFAULT NULL,
  cert_id     INT UNSIGNED DEFAULT NULL,
  awarded_by  INT UNSIGNED DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES youth_users(id) ON DELETE CASCADE,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add status column to youth_users if missing
ALTER TABLE youth_users
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'approved' AFTER zip_code;

-- Notifications table (used by youth portal)
CREATE TABLE IF NOT EXISTS notifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(200) NOT NULL,
  message    TEXT         NOT NULL,
  type       VARCHAR(50)  DEFAULT 'info',
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES youth_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Org-based merit tables (used by merit_engine.php)
CREATE TABLE IF NOT EXISTS org_merit_logs (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  points          SMALLINT     NOT NULL,
  type            ENUM('merit','demerit') NOT NULL,
  reason          VARCHAR(255) NOT NULL,
  category        VARCHAR(100) DEFAULT NULL,
  event_id        INT UNSIGNED DEFAULT NULL,
  awarded_by      INT UNSIGNED DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_warning_letters (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  reason          TEXT         NOT NULL,
  level           ENUM('warning','show_cause','revocation_flag') NOT NULL DEFAULT 'warning',
  issued_by       INT UNSIGNED DEFAULT NULL,
  issued_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS org_explanation_letters (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  organization_id INT UNSIGNED NOT NULL,
  subject         VARCHAR(255) NOT NULL,
  content         TEXT         NOT NULL,
  status          ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     INT UNSIGNED DEFAULT NULL,
  reviewed_at     DATETIME     DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
