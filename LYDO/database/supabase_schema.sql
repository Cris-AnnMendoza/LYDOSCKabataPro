-- ============================================================
-- Local Youth Development Sta. Cruz Laguna System
-- PostgreSQL Schema for Supabase
-- Converted from MySQL to PostgreSQL
-- ============================================================

-- Enable UUID extension (for potential future use)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================
-- YOUTH USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS youth_users (
  id                    SERIAL PRIMARY KEY,
  first_name            VARCHAR(100)  NOT NULL,
  middle_name           VARCHAR(100)  DEFAULT NULL,
  last_name             VARCHAR(100)  NOT NULL,
  suffix                VARCHAR(20)   DEFAULT NULL,
  gender                VARCHAR(30)   NOT NULL,
  birthdate             DATE          NOT NULL,
  age                   SMALLINT      NOT NULL CHECK (age >= 0 AND age <= 255),
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
  status                VARCHAR(20)   NOT NULL DEFAULT 'approved',
  youth_classification  TEXT          DEFAULT NULL,
  educational_status    VARCHAR(100)  DEFAULT NULL,
  school_name           VARCHAR(200)  DEFAULT NULL,
  course_or_grade       VARCHAR(150)  DEFAULT NULL,
  employment_status     VARCHAR(100)  DEFAULT NULL,
  organization_name     VARCHAR(200)  DEFAULT NULL,
  organization_type     VARCHAR(100)  DEFAULT NULL,
  organization_role     VARCHAR(100)  DEFAULT NULL,
  years_membership      SMALLINT      DEFAULT 0 CHECK (years_membership >= 0),
  skills                TEXT          DEFAULT NULL,
  interests             TEXT          DEFAULT NULL,
  programs_interested   TEXT          DEFAULT NULL,
  volunteer_availability VARCHAR(100) DEFAULT NULL,
  valid_id              VARCHAR(255)  DEFAULT NULL,
  profile_picture       VARCHAR(255)  DEFAULT NULL,
  created_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Create index on email for faster lookups
CREATE INDEX IF NOT EXISTS idx_youth_users_email ON youth_users(email);
CREATE INDEX IF NOT EXISTS idx_youth_users_status ON youth_users(status);
CREATE INDEX IF NOT EXISTS idx_youth_users_barangay ON youth_users(barangay);

-- Trigger to auto-update updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_youth_users_updated_at BEFORE UPDATE ON youth_users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- ADMIN TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS admin_users (
  id           SERIAL PRIMARY KEY,
  full_name    VARCHAR(150) NOT NULL,
  email        VARCHAR(191) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  role         VARCHAR(30)  NOT NULL DEFAULT 'staff_encoder' 
               CHECK (role IN ('super_admin','youth_coordinator','barangay_admin','staff_encoder')),
  barangay     VARCHAR(100) DEFAULT NULL,
  is_active    BOOLEAN      NOT NULL DEFAULT TRUE,
  last_login   TIMESTAMP    DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Default super admin (password: Admin@1234)
INSERT INTO admin_users (full_name, email, password, role) VALUES
('Super Administrator', 'admin@lydo.gov.ph',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin')
ON CONFLICT (email) DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_admin_users_email ON admin_users(email);
CREATE INDEX IF NOT EXISTS idx_admin_users_role ON admin_users(role);

CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON admin_users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Admin Sessions
CREATE TABLE IF NOT EXISTS admin_sessions (
  id         SERIAL PRIMARY KEY,
  admin_id   INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  expires_at TIMESTAMP    NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_sessions_token ON admin_sessions(token);
CREATE INDEX IF NOT EXISTS idx_admin_sessions_admin_id ON admin_sessions(admin_id);

-- Admin Activity Log
CREATE TABLE IF NOT EXISTS admin_activity_log (
  id         SERIAL PRIMARY KEY,
  admin_id   INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
  action     VARCHAR(255) NOT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_activity_admin_id ON admin_activity_log(admin_id);
CREATE INDEX IF NOT EXISTS idx_admin_activity_created_at ON admin_activity_log(created_at);

-- ============================================================
-- ORGANIZATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS organizations (
  id            SERIAL PRIMARY KEY,
  name          VARCHAR(200) NOT NULL,
  category      VARCHAR(100) DEFAULT NULL,
  description   TEXT         DEFAULT NULL,
  adviser_name  VARCHAR(150) DEFAULT NULL,
  adviser_email VARCHAR(191) DEFAULT NULL,
  adviser_phone VARCHAR(30)  DEFAULT NULL,
  barangay      VARCHAR(100) DEFAULT NULL,
  is_active     BOOLEAN      NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_organizations_name ON organizations(name);
CREATE INDEX IF NOT EXISTS idx_organizations_category ON organizations(category);

CREATE TRIGGER update_organizations_updated_at BEFORE UPDATE ON organizations
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Organization Members (Link youth to organizations)
CREATE TABLE IF NOT EXISTS organization_members (
  id              SERIAL PRIMARY KEY,
  organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  role            VARCHAR(100) DEFAULT 'Member',
  joined_at       DATE         DEFAULT NULL,
  is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
  UNIQUE(organization_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_org_members_org_id ON organization_members(organization_id);
CREATE INDEX IF NOT EXISTS idx_org_members_user_id ON organization_members(user_id);

-- ============================================================
-- EVENTS / ACTIVITIES
-- ============================================================

CREATE TABLE IF NOT EXISTS events (
  id                        SERIAL PRIMARY KEY,
  title                     VARCHAR(200) NOT NULL,
  description               TEXT         DEFAULT NULL,
  event_type                VARCHAR(50)  NOT NULL DEFAULT 'official_event',
  requires_representative   BOOLEAN      NOT NULL DEFAULT FALSE,
  qr_token                  VARCHAR(64)  DEFAULT NULL UNIQUE,
  qr_code_path              VARCHAR(255) DEFAULT NULL,
  checkin_open              BOOLEAN      NOT NULL DEFAULT TRUE,
  event_date                DATE         NOT NULL,
  event_time                TIME         DEFAULT NULL,
  location                  VARCHAR(200) DEFAULT NULL,
  organization_id           INTEGER      DEFAULT NULL REFERENCES organizations(id) ON DELETE SET NULL,
  merit_points              SMALLINT     NOT NULL DEFAULT 0 CHECK (merit_points >= 0),
  quarter                   SMALLINT     DEFAULT NULL CHECK (quarter >= 1 AND quarter <= 4),
  year                      INTEGER      DEFAULT NULL,
  created_by                INTEGER      DEFAULT NULL,
  created_at                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_events_org_id ON events(organization_id);
CREATE INDEX IF NOT EXISTS idx_events_qr_token ON events(qr_token);
CREATE INDEX IF NOT EXISTS idx_events_year_quarter ON events(year, quarter);

-- Event Attendance
CREATE TABLE IF NOT EXISTS event_attendance (
  id                    SERIAL PRIMARY KEY,
  event_id              INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  user_id               INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  organization_id       INTEGER DEFAULT NULL REFERENCES organizations(id) ON DELETE SET NULL,
  status                VARCHAR(20) NOT NULL DEFAULT 'present' 
                        CHECK (status IN ('present','absent','excused','late')),
  checked_in_at         TIMESTAMP   DEFAULT NULL,
  certificate_uploaded  BOOLEAN     NOT NULL DEFAULT FALSE,
  remarks               VARCHAR(255) DEFAULT NULL,
  recorded_at           TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_attendance_event_id ON event_attendance(event_id);
CREATE INDEX IF NOT EXISTS idx_event_attendance_user_id ON event_attendance(user_id);

-- Event Check-ins (Individual QR scans)
CREATE TABLE IF NOT EXISTS event_checkins (
  id              SERIAL PRIMARY KEY,
  event_id        INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  checked_in_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address      VARCHAR(45)  DEFAULT NULL,
  UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_checkins_event_id ON event_checkins(event_id);
CREATE INDEX IF NOT EXISTS idx_event_checkins_user_id ON event_checkins(user_id);

-- Event Certificates
CREATE TABLE IF NOT EXISTS event_certificates (
  id              SERIAL PRIMARY KEY,
  event_id        INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
  user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  cert_number     VARCHAR(50)  NOT NULL UNIQUE,
  generated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  uploaded_at     TIMESTAMP    DEFAULT NULL,
  upload_filename VARCHAR(255) DEFAULT NULL,
  upload_original VARCHAR(255) DEFAULT NULL,
  merit_awarded   BOOLEAN      NOT NULL DEFAULT FALSE,
  merit_points    SMALLINT     NOT NULL DEFAULT 2,
  UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_certificates_cert_number ON event_certificates(cert_number);
CREATE INDEX IF NOT EXISTS idx_event_certificates_user_id ON event_certificates(user_id);

-- ============================================================
-- MERIT & DEMERIT SYSTEM
-- ============================================================

-- Individual Merit Logs
CREATE TABLE IF NOT EXISTS merit_logs (
  id          SERIAL PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  points      SMALLINT     NOT NULL,
  type        VARCHAR(10)  NOT NULL CHECK (type IN ('merit','demerit')),
  reason      VARCHAR(255) NOT NULL,
  category    VARCHAR(100) DEFAULT NULL,
  reference_id INTEGER     DEFAULT NULL,
  awarded_by  INTEGER      DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_merit_logs_user_id ON merit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_merit_logs_type ON merit_logs(type);
CREATE INDEX IF NOT EXISTS idx_merit_logs_created_at ON merit_logs(created_at);

-- User Merit Logs (Enhanced individual tracking)
CREATE TABLE IF NOT EXISTS user_merit_logs (
  id          SERIAL PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  points      SMALLINT     NOT NULL,
  type        VARCHAR(10)  NOT NULL CHECK (type IN ('merit','demerit')),
  reason      VARCHAR(255) NOT NULL,
  category    VARCHAR(100) DEFAULT NULL,
  event_id    INTEGER      DEFAULT NULL REFERENCES events(id) ON DELETE SET NULL,
  cert_id     INTEGER      DEFAULT NULL,
  awarded_by  INTEGER      DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_user_merit_logs_user_id ON user_merit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_user_merit_logs_event_id ON user_merit_logs(event_id);

-- Organization Merit Logs
CREATE TABLE IF NOT EXISTS org_merit_logs (
  id              SERIAL PRIMARY KEY,
  organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  points          SMALLINT     NOT NULL,
  type            VARCHAR(10)  NOT NULL CHECK (type IN ('merit','demerit')),
  reason          VARCHAR(255) NOT NULL,
  category        VARCHAR(100) DEFAULT NULL,
  event_id        INTEGER      DEFAULT NULL REFERENCES events(id) ON DELETE SET NULL,
  awarded_by      INTEGER      DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_org_merit_logs_org_id ON org_merit_logs(organization_id);

-- ============================================================
-- WARNING & EXPLANATION LETTERS
-- ============================================================

-- Individual Warning Letters
CREATE TABLE IF NOT EXISTS warning_letters (
  id          SERIAL PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  reason      TEXT         NOT NULL,
  level       VARCHAR(20)  NOT NULL DEFAULT 'warning' 
              CHECK (level IN ('warning','final_warning','revocation')),
  issued_by   INTEGER      DEFAULT NULL,
  issued_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_warning_letters_user_id ON warning_letters(user_id);

-- Individual Explanation Letters
CREATE TABLE IF NOT EXISTS explanation_letters (
  id          SERIAL PRIMARY KEY,
  user_id     INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  subject     VARCHAR(255) NOT NULL,
  content     TEXT         NOT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'pending' 
              CHECK (status IN ('pending','accepted','rejected')),
  reviewed_by INTEGER      DEFAULT NULL,
  reviewed_at TIMESTAMP    DEFAULT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_explanation_letters_user_id ON explanation_letters(user_id);
CREATE INDEX IF NOT EXISTS idx_explanation_letters_status ON explanation_letters(status);

-- Organization Warning Letters
CREATE TABLE IF NOT EXISTS org_warning_letters (
  id              SERIAL PRIMARY KEY,
  organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  reason          TEXT         NOT NULL,
  level           VARCHAR(20)  NOT NULL DEFAULT 'warning' 
                  CHECK (level IN ('warning','show_cause','revocation_flag')),
  issued_by       INTEGER      DEFAULT NULL,
  issued_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_org_warning_letters_org_id ON org_warning_letters(organization_id);

-- Organization Explanation Letters
CREATE TABLE IF NOT EXISTS org_explanation_letters (
  id              SERIAL PRIMARY KEY,
  organization_id INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  subject         VARCHAR(255) NOT NULL,
  content         TEXT         NOT NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'pending' 
                  CHECK (status IN ('pending','accepted','rejected')),
  reviewed_by     INTEGER      DEFAULT NULL,
  reviewed_at     TIMESTAMP    DEFAULT NULL,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_org_explanation_letters_org_id ON org_explanation_letters(organization_id);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
  id         SERIAL PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  title      VARCHAR(200) NOT NULL,
  message    TEXT         NOT NULL,
  type       VARCHAR(50)  DEFAULT 'info',
  is_read    BOOLEAN      NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);

-- ============================================================
-- NOTES:
-- ============================================================
-- MySQL → PostgreSQL Conversions:
-- 1. AUTO_INCREMENT → SERIAL
-- 2. TINYINT(1) → BOOLEAN
-- 3. TINYINT UNSIGNED → SMALLINT with CHECK
-- 4. INT UNSIGNED → INTEGER
-- 5. ENUM → VARCHAR with CHECK constraint
-- 6. ON UPDATE CURRENT_TIMESTAMP → Trigger function
-- 7. IF NOT EXISTS in ALTER → Not supported, use IF EXISTS checks
-- ============================================================
