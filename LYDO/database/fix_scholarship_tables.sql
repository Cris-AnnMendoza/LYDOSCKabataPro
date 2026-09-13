-- ============================================================
-- Fix Scholarship Tables to Match PHP Code Expectations
-- ============================================================

-- Drop existing tables (in correct order)
DROP TABLE IF EXISTS scholarship_documents CASCADE;
DROP TABLE IF EXISTS scholarship_applications CASCADE;
DROP TABLE IF EXISTS scholarship_batches CASCADE;
DROP TABLE IF EXISTS scholarship_programs CASCADE;

-- Scholarship Batches (main program/batch)
CREATE TABLE scholarship_batches (
  id              SERIAL PRIMARY KEY,
  name            VARCHAR(200) NOT NULL,
  school_year     VARCHAR(20) DEFAULT NULL,
  semester        VARCHAR(20) DEFAULT NULL,
  slots           INTEGER DEFAULT 50,
  gwa_required    DECIMAL(3,2) DEFAULT NULL,
  income_limit    DECIMAL(10,2) DEFAULT NULL,
  app_start       DATE DEFAULT NULL,
  app_end         DATE DEFAULT NULL,
  exam_date       DATE DEFAULT NULL,
  exam_venue      VARCHAR(200) DEFAULT NULL,
  exam_time       TIME DEFAULT NULL,
  description     TEXT DEFAULT NULL,
  status          VARCHAR(20) NOT NULL DEFAULT 'open' 
                  CHECK (status IN ('open','closed','completed')),
  created_by      INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_scholarship_batches_status ON scholarship_batches(status);
CREATE INDEX idx_scholarship_batches_school_year ON scholarship_batches(school_year);

CREATE TRIGGER update_scholarship_batches_updated_at BEFORE UPDATE ON scholarship_batches
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Scholarship Applications
CREATE TABLE scholarship_applications (
  id                    SERIAL PRIMARY KEY,
  batch_id              INTEGER NOT NULL REFERENCES scholarship_batches(id) ON DELETE CASCADE,
  user_id               INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
  status                VARCHAR(20) NOT NULL DEFAULT 'submitted' 
                        CHECK (status IN ('submitted','under_review','for_exam','passed','failed','approved','rejected','beneficiary')),
  gwa                   DECIMAL(3,2) DEFAULT NULL,
  family_income         DECIMAL(10,2) DEFAULT NULL,
  household_size        SMALLINT DEFAULT NULL,
  course                VARCHAR(150) DEFAULT NULL,
  year_level            VARCHAR(50) DEFAULT NULL,
  school_name           VARCHAR(200) DEFAULT NULL,
  essay                 TEXT DEFAULT NULL,
  exam_score            DECIMAL(5,2) DEFAULT NULL,
  qualification_score   DECIMAL(5,2) DEFAULT NULL,
  exam_scheduled        DATE DEFAULT NULL,
  rejection_reason      TEXT DEFAULT NULL,
  reviewed_by           INTEGER DEFAULT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
  reviewed_at           TIMESTAMP DEFAULT NULL,
  applied_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(batch_id, user_id)
);

CREATE INDEX idx_scholarship_apps_batch_id ON scholarship_applications(batch_id);
CREATE INDEX idx_scholarship_apps_user_id ON scholarship_applications(user_id);
CREATE INDEX idx_scholarship_apps_status ON scholarship_applications(status);

CREATE TRIGGER update_scholarship_applications_updated_at BEFORE UPDATE ON scholarship_applications
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Scholarship Documents
CREATE TABLE scholarship_documents (
  id              SERIAL PRIMARY KEY,
  application_id  INTEGER NOT NULL REFERENCES scholarship_applications(id) ON DELETE CASCADE,
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

CREATE INDEX idx_scholarship_docs_app_id ON scholarship_documents(application_id);
CREATE INDEX idx_scholarship_docs_status ON scholarship_documents(status);
