-- ============================================================
-- VERIFY AND FIX SCHOLARSHIP TABLES
-- Run this in Supabase SQL Editor
-- ============================================================

-- Drop existing tables if they have wrong structure
DROP TABLE IF EXISTS scholarship_documents CASCADE;
DROP TABLE IF EXISTS scholarship_applications CASCADE;
DROP TABLE IF EXISTS scholarship_batches CASCADE;
DROP TABLE IF EXISTS scholarship_programs CASCADE;

-- ============================================================
-- Scholarship Batches (main program/batch)
-- ============================================================
CREATE TABLE scholarship_batches (
  id              SERIAL PRIMARY KEY,
  name            VARCHAR(200) NOT NULL,
  school_year     VARCHAR(20) NOT NULL,
  semester        VARCHAR(50),
  slots           INTEGER DEFAULT 50,
  gwa_required    DECIMAL(3,2) DEFAULT 1.75,
  income_limit    DECIMAL(10,2),
  app_start       DATE,
  app_end         DATE,
  exam_date       DATE,
  exam_venue      VARCHAR(300),
  exam_time       TIME,
  description     TEXT,
  status          VARCHAR(50) DEFAULT 'open' CHECK (status IN ('open','closed','evaluation','completed')),
  created_by      INTEGER REFERENCES admin_users(id),
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Scholarship Applications
-- ============================================================
CREATE TABLE scholarship_applications (
  id                    SERIAL PRIMARY KEY,
  batch_id              INTEGER NOT NULL REFERENCES scholarship_batches(id) ON DELETE CASCADE,
  user_id               INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  full_name             VARCHAR(200) NOT NULL,
  email                 VARCHAR(150) NOT NULL,
  contact_number        VARCHAR(20),
  age                   INTEGER,
  gender                VARCHAR(20),
  address               TEXT,
  school_name           VARCHAR(200),
  course                VARCHAR(200),
  year_level            VARCHAR(50),
  gwa                   DECIMAL(3,2),
  household_income      DECIMAL(10,2),
  parent_name           VARCHAR(200),
  parent_occupation     VARCHAR(150),
  siblings              INTEGER,
  exam_score            DECIMAL(5,2),
  qualification_score   DECIMAL(5,2),
  status                VARCHAR(50) DEFAULT 'submitted' CHECK (status IN ('submitted','under_review','for_exam','passed','failed','approved','rejected','beneficiary')),
  rejection_reason      TEXT,
  exam_scheduled        DATE,
  reviewed_by           INTEGER REFERENCES admin_users(id),
  reviewed_at           TIMESTAMP,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Scholarship Documents
-- ============================================================
CREATE TABLE scholarship_documents (
  id              SERIAL PRIMARY KEY,
  application_id  INTEGER NOT NULL REFERENCES scholarship_applications(id) ON DELETE CASCADE,
  doc_type        VARCHAR(100) NOT NULL,
  file_path       VARCHAR(500) NOT NULL,
  original_name   VARCHAR(300),
  status          VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending','verified','rejected')),
  reviewed_by     INTEGER REFERENCES admin_users(id),
  reviewed_at     TIMESTAMP,
  uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Create indexes for better performance
-- ============================================================
CREATE INDEX idx_scholarship_apps_batch ON scholarship_applications(batch_id);
CREATE INDEX idx_scholarship_apps_user ON scholarship_applications(user_id);
CREATE INDEX idx_scholarship_apps_status ON scholarship_applications(status);
CREATE INDEX idx_scholarship_docs_app ON scholarship_documents(application_id);

-- ============================================================
-- Create triggers for updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION update_scholarship_batches_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_scholarship_batches_updated_at
BEFORE UPDATE ON scholarship_batches
FOR EACH ROW
EXECUTE FUNCTION update_scholarship_batches_updated_at();

CREATE OR REPLACE FUNCTION update_scholarship_applications_updated_at()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_scholarship_applications_updated_at
BEFORE UPDATE ON scholarship_applications
FOR EACH ROW
EXECUTE FUNCTION update_scholarship_applications_updated_at();

-- ============================================================
-- Verification Query
-- ============================================================
-- Run this after creating tables to verify:
-- SELECT table_name FROM information_schema.tables 
-- WHERE table_schema = 'public' AND table_name LIKE 'scholarship%';
