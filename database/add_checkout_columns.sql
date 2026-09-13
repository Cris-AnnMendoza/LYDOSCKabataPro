-- Add missing columns to event_checkins table for check-out and photos
-- Run this in Supabase SQL Editor

-- Add checked_out_at column
ALTER TABLE event_checkins 
ADD COLUMN IF NOT EXISTS checked_out_at TIMESTAMP DEFAULT NULL;

-- Add photo columns
ALTER TABLE event_checkins 
ADD COLUMN IF NOT EXISTS checkin_photo VARCHAR(255) DEFAULT NULL;

ALTER TABLE event_checkins 
ADD COLUMN IF NOT EXISTS checkout_photo VARCHAR(255) DEFAULT NULL;

-- Verify columns were added
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'event_checkins' 
ORDER BY ordinal_position;
