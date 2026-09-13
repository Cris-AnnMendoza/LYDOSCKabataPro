-- ============================================================
-- FIX EVENTS TABLE - Add Missing Columns
-- Run this in Supabase SQL Editor
-- ============================================================

-- Add missing columns to events table
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkin_code VARCHAR(6);
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_start_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_end_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkout_open BOOLEAN NOT NULL DEFAULT FALSE;

-- Generate checkin codes for existing events that don't have one
UPDATE events 
SET checkin_code = UPPER(SUBSTRING(MD5(CONCAT(id::text, COALESCE(qr_token,'x'))), 1, 6))
WHERE checkin_code IS NULL OR checkin_code = '';

-- Create index on checkin_code for faster lookups
CREATE INDEX IF NOT EXISTS idx_events_checkin_code ON events(checkin_code);

-- Verify the changes
-- SELECT column_name, data_type, is_nullable, column_default
-- FROM information_schema.columns
-- WHERE table_name = 'events' AND table_schema = 'public'
-- ORDER BY ordinal_position;
