-- ============================================================
-- Volunteer and Scholarship Tables for LYDO System
-- Run this AFTER add_missing_tables.sql
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
-- SCHOLARSHIP TABLES
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
