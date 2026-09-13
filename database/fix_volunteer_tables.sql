-- ============================================================
-- Fix Volunteer Tables to Match PHP Code Expectations
-- Run this to replace the incorrectly structured volunteer tables
-- ============================================================

-- Drop existing tables (in correct order due to foreign keys)
DROP TABLE IF EXISTS volunteer_hours CASCADE;
DROP TABLE IF EXISTS volunteer_attendance CASCADE;
DROP TABLE IF EXISTS volunteer_registrations CASCADE;
DROP TABLE IF EXISTS volunteer_programs CASCADE;

-- Recreate volunteer_programs with correct columns
CREATE TABLE volunteer_programs (
  id              SERIAL PRIMARY KEY,
  name            VARCHAR(200) NOT NULL,
  type            VARCHAR(50) NOT NULL,
  description     TEXT DEFAULT NULL,
  min_age         SMALLINT DEFAULT 15,
  max_age         SMALLINT DEFAULT 30,
  slots           INTEGER DEFAULT 50,
  start_date      DATE DEFAULT NULL,
  end_date        DATE DEFAULT NULL,
  location        VARCHAR(200) DEFAULT NULL,
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  created_by      INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_volunteer_programs_type ON volunteer_programs(type);
CREATE INDEX idx_volunteer_programs_is_active ON volunteer_programs(is_active);

CREATE TRIGGER update_volunteer_programs_updated_at BEFORE UPDATE ON volunteer_programs
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Volunteer Registrations (matches volunteer_admin.php expectations)
CREATE TABLE volunteer_registrations (
  id                  SERIAL PRIMARY KEY,
  program_id          INTEGER NOT NULL REFERENCES volunteer_programs(id) ON DELETE CASCADE,
  user_id             INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  age                 SMALLINT DEFAULT NULL,
  contact_number      VARCHAR(30) DEFAULT NULL,
  status              VARCHAR(20) NOT NULL DEFAULT 'pending' 
                      CHECK (status IN ('pending','approved','rejected','completed')),
  orientation_date    DATE DEFAULT NULL,
  notes               TEXT DEFAULT NULL,
  total_hours         DECIMAL(6,2) NOT NULL DEFAULT 0,
  certificate_issued  BOOLEAN NOT NULL DEFAULT FALSE,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(program_id, user_id)
);

CREATE INDEX idx_volunteer_reg_program_id ON volunteer_registrations(program_id);
CREATE INDEX idx_volunteer_reg_user_id ON volunteer_registrations(user_id);
CREATE INDEX idx_volunteer_reg_status ON volunteer_registrations(status);

-- Volunteer Attendance (matches volunteer_admin.php expectations)
CREATE TABLE volunteer_attendance (
  id              SERIAL PRIMARY KEY,
  registration_id INTEGER NOT NULL REFERENCES volunteer_registrations(id) ON DELETE CASCADE,
  event_name      VARCHAR(200) NOT NULL,
  event_date      DATE NOT NULL,
  hours           DECIMAL(5,2) NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'present' 
                  CHECK (status IN ('present','absent','excused')),
  recorded_by     INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_volunteer_att_reg_id ON volunteer_attendance(registration_id);
CREATE INDEX idx_volunteer_att_date ON volunteer_attendance(event_date);
