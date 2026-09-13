-- ============================================================
-- Missing Tables Migration for LYDO System
-- Add accreditation and assistance tables to Supabase
-- ============================================================

-- ============================================================
-- ACCREDITATION TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS accreditation_applications (
  id                  SERIAL PRIMARY KEY,
  organization_name   VARCHAR(200) NOT NULL,
  category            VARCHAR(100) DEFAULT NULL,
  barangay            VARCHAR(100) DEFAULT NULL,
  contact_person      VARCHAR(150) DEFAULT NULL,
  contact_email       VARCHAR(191) DEFAULT NULL,
  contact_phone       VARCHAR(30)  DEFAULT NULL,
  submitted_by        INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  status              VARCHAR(20) NOT NULL DEFAULT 'submitted' 
                      CHECK (status IN ('submitted','under_review','approved','rejected')),
  certificate_no      VARCHAR(50)  DEFAULT NULL UNIQUE,
  valid_until         DATE         DEFAULT NULL,
  reviewed_by         INTEGER      DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  reviewed_at         TIMESTAMP    DEFAULT NULL,
  rejection_reason    TEXT         DEFAULT NULL,
  created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_accred_apps_status ON accreditation_applications(status);
CREATE INDEX IF NOT EXISTS idx_accred_apps_submitted_by ON accreditation_applications(submitted_by);

CREATE TRIGGER update_accreditation_applications_updated_at BEFORE UPDATE ON accreditation_applications
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Accreditation Documents
CREATE TABLE IF NOT EXISTS accreditation_documents (
  id              SERIAL PRIMARY KEY,
  application_id  INTEGER NOT NULL REFERENCES accreditation_applications(id) ON DELETE CASCADE,
  doc_type        VARCHAR(50) NOT NULL,
  file_path       VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  file_size       INTEGER NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending' 
                  CHECK (status IN ('pending','verified','rejected')),
  reviewed_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  reviewed_at     TIMESTAMP DEFAULT NULL,
  uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_accred_docs_app_id ON accreditation_documents(application_id);

-- Accreditation Workflow Steps
CREATE TABLE IF NOT EXISTS accreditation_workflow (
  id              SERIAL PRIMARY KEY,
  application_id  INTEGER NOT NULL REFERENCES accreditation_applications(id) ON DELETE CASCADE,
  step            VARCHAR(100) NOT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending' 
                  CHECK (status IN ('pending','completed')),
  done_at         TIMESTAMP DEFAULT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_accred_workflow_app_id ON accreditation_workflow(application_id);

-- ============================================================
-- ASSISTANCE TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS assistance_requests (
  id                SERIAL PRIMARY KEY,
  organization_id   INTEGER NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
  submitted_by      INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  request_type      VARCHAR(100) NOT NULL,
  title             VARCHAR(200) NOT NULL,
  description       TEXT NOT NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'pending' 
                    CHECK (status IN ('pending','reviewing','approved','scheduled','completed','declined')),
  scheduled_date    DATE DEFAULT NULL,
  decline_reason    TEXT DEFAULT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_assist_req_status ON assistance_requests(status);
CREATE INDEX IF NOT EXISTS idx_assist_req_org_id ON assistance_requests(organization_id);
CREATE INDEX IF NOT EXISTS idx_assist_req_submitted_by ON assistance_requests(submitted_by);

CREATE TRIGGER update_assistance_requests_updated_at BEFORE UPDATE ON assistance_requests
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Assistance Documents
CREATE TABLE IF NOT EXISTS assistance_documents (
  id              SERIAL PRIMARY KEY,
  request_id      INTEGER NOT NULL REFERENCES assistance_requests(id) ON DELETE CASCADE,
  doc_type        VARCHAR(50) NOT NULL,
  file_path       VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NOT NULL,
  file_size       INTEGER NOT NULL DEFAULT 0,
  uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_assist_docs_request_id ON assistance_documents(request_id);

-- Assistance Timeline
CREATE TABLE IF NOT EXISTS assistance_timeline (
  id          SERIAL PRIMARY KEY,
  request_id  INTEGER NOT NULL REFERENCES assistance_requests(id) ON DELETE CASCADE,
  status      VARCHAR(100) NOT NULL,
  note        TEXT DEFAULT NULL,
  done_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_assist_timeline_request_id ON assistance_timeline(request_id);

-- Assistance Comments
CREATE TABLE IF NOT EXISTS assistance_comments (
  id          SERIAL PRIMARY KEY,
  request_id  INTEGER NOT NULL REFERENCES assistance_requests(id) ON DELETE CASCADE,
  author_id   INTEGER NOT NULL,
  author_type VARCHAR(20) NOT NULL CHECK (author_type IN ('admin','youth')),
  comment     TEXT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_assist_comments_request_id ON assistance_comments(request_id);

-- ============================================================
-- NOTES:
-- ============================================================
-- Run this SQL in your Supabase SQL Editor to add missing tables
-- After running, uncomment the menu items in sidebar.php
-- ============================================================

-- ============================================================
-- VOLUNTEER PROGRAM TABLES
-- ============================================================

CREATE TABLE IF NOT EXISTS volunteer_programs (
  id              SERIAL PRIMARY KEY,
  title           VARCHAR(200) NOT NULL,
  description     TEXT DEFAULT NULL,
  start_date      DATE NOT NULL,
  end_date        DATE DEFAULT NULL,
  location        VARCHAR(200) DEFAULT NULL,
  slots           INTEGER DEFAULT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'open' 
                  CHECK (status IN ('open','closed','completed','cancelled')),
  requirements    TEXT DEFAULT NULL,
  created_by      INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_volunteer_programs_status ON volunteer_programs(status);
CREATE INDEX IF NOT EXISTS idx_volunteer_programs_start_date ON volunteer_programs(start_date);

CREATE TRIGGER update_volunteer_programs_updated_at BEFORE UPDATE ON volunteer_programs
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Volunteer Registrations
CREATE TABLE IF NOT EXISTS volunteer_registrations (
  id              SERIAL PRIMARY KEY,
  program_id      INTEGER NOT NULL REFERENCES volunteer_programs(id) ON DELETE CASCADE,
  user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending' 
                  CHECK (status IN ('pending','approved','rejected','attended','completed')),
  application_note TEXT DEFAULT NULL,
  admin_note      TEXT DEFAULT NULL,
  reviewed_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  reviewed_at     TIMESTAMP DEFAULT NULL,
  registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(program_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_volunteer_reg_program_id ON volunteer_registrations(program_id);
CREATE INDEX IF NOT EXISTS idx_volunteer_reg_user_id ON volunteer_registrations(user_id);
CREATE INDEX IF NOT EXISTS idx_volunteer_reg_status ON volunteer_registrations(status);

-- Volunteer Attendance/Hours
CREATE TABLE IF NOT EXISTS volunteer_hours (
  id              SERIAL PRIMARY KEY,
  registration_id INTEGER NOT NULL REFERENCES volunteer_registrations(id) ON DELETE CASCADE,
  hours           DECIMAL(5,2) NOT NULL DEFAULT 0,
  date            DATE NOT NULL,
  notes           TEXT DEFAULT NULL,
  verified_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  verified_at     TIMESTAMP DEFAULT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_volunteer_hours_reg_id ON volunteer_hours(registration_id);
CREATE INDEX IF NOT EXISTS idx_volunteer_hours_date ON volunteer_hours(date);

-- ============================================================
-- SCHOLARSHIP TABLES (if needed)
-- ============================================================

CREATE TABLE IF NOT EXISTS scholarship_programs (
  id              SERIAL PRIMARY KEY,
  name            VARCHAR(200) NOT NULL,
  description     TEXT DEFAULT NULL,
  type            VARCHAR(50) DEFAULT NULL,
  amount          DECIMAL(10,2) DEFAULT NULL,
  slots           INTEGER DEFAULT NULL,
  requirements    TEXT DEFAULT NULL,
  deadline        DATE DEFAULT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'open' 
                  CHECK (status IN ('open','closed')),
  created_by      INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_scholarship_programs_status ON scholarship_programs(status);

CREATE TRIGGER update_scholarship_programs_updated_at BEFORE UPDATE ON scholarship_programs
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Scholarship Applications
CREATE TABLE IF NOT EXISTS scholarship_applications (
  id              SERIAL PRIMARY KEY,
  program_id      INTEGER NOT NULL REFERENCES scholarship_programs(id) ON DELETE CASCADE,
  user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending' 
                  CHECK (status IN ('pending','reviewing','shortlisted','approved','rejected')),
  gwa             DECIMAL(3,2) DEFAULT NULL,
  family_income   DECIMAL(10,2) DEFAULT NULL,
  essay           TEXT DEFAULT NULL,
  documents       TEXT DEFAULT NULL,
  reviewed_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  reviewed_at     TIMESTAMP DEFAULT NULL,
  admin_notes     TEXT DEFAULT NULL,
  applied_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(program_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_scholarship_apps_program_id ON scholarship_applications(program_id);
CREATE INDEX IF NOT EXISTS idx_scholarship_apps_user_id ON scholarship_applications(user_id);
CREATE INDEX IF NOT EXISTS idx_scholarship_apps_status ON scholarship_applications(status);

CREATE TRIGGER update_scholarship_applications_updated_at BEFORE UPDATE ON scholarship_applications
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
