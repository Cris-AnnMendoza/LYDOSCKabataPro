-- Fix accreditation_applications table - add missing description column

USE local_youth_development_db;

-- Add description column if it doesn't exist
ALTER TABLE accreditation_applications 
ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER contact_phone;

-- Verify the change
DESCRIBE accreditation_applications;
