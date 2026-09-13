-- ============================================================
-- COMPLETE EVENTS TABLE FIX FOR POSTGRESQL
-- Run this in Supabase SQL Editor to fix all events-related issues
-- ============================================================

-- Step 1: Add missing columns to events table
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkin_code VARCHAR(6);
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_start_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_end_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkout_open BOOLEAN NOT NULL DEFAULT FALSE;

-- Step 2: Generate checkin codes for existing events
UPDATE events 
SET checkin_code = UPPER(SUBSTRING(MD5(CONCAT(id::text, COALESCE(qr_token,'x'))), 1, 6))
WHERE checkin_code IS NULL OR checkin_code = '';

-- Step 3: Ensure checkin_open is boolean (not integer)
-- PostgreSQL schema already has this as BOOLEAN, but let's ensure data is consistent
UPDATE events SET checkin_open = TRUE WHERE checkin_open IS NULL;

-- Step 4: Create index for performance
CREATE INDEX IF NOT EXISTS idx_events_checkin_code ON events(checkin_code);
CREATE INDEX IF NOT EXISTS idx_events_checkout_open ON events(checkout_open);

-- Step 5: Verify the structure
SELECT 
    column_name, 
    data_type, 
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name = 'events' 
  AND table_schema = 'public'
  AND column_name IN ('checkin_code', 'event_start_time', 'event_end_time', 'checkout_open', 'checkin_open')
ORDER BY ordinal_position;

-- Success message
DO $$
BEGIN
    RAISE NOTICE 'Events table successfully updated with all required columns!';
END $$;
