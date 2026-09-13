-- ============================================================
-- EVENTS MODULE - COMPLETE DATABASE SETUP
-- Run this in Supabase SQL Editor
-- Creates/updates ALL tables needed for events functionality
-- ============================================================

-- ============================================================
-- Step 1: Update events table with missing columns
-- ============================================================
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkin_code VARCHAR(6);
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_start_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_end_time TIME;
ALTER TABLE events ADD COLUMN IF NOT EXISTS checkout_open BOOLEAN NOT NULL DEFAULT FALSE;

-- Generate checkin codes for existing events
UPDATE events 
SET checkin_code = UPPER(SUBSTRING(MD5(CONCAT(id::text, COALESCE(qr_token,'x'))), 1, 6))
WHERE checkin_code IS NULL OR checkin_code = '';

-- ============================================================
-- Step 2: Ensure event_checkins table exists
-- ============================================================
CREATE TABLE IF NOT EXISTS event_checkins (
    id              SERIAL PRIMARY KEY,
    event_id        INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
    checked_in_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_out_at  TIMESTAMP,
    ip_address      VARCHAR(45),
    photo_path      VARCHAR(500),
    latitude        DECIMAL(10, 8),
    longitude       DECIMAL(11, 8),
    device_info     TEXT,
    UNIQUE (event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_checkins_event ON event_checkins(event_id);
CREATE INDEX IF NOT EXISTS idx_event_checkins_user ON event_checkins(user_id);

-- ============================================================
-- Step 3: Ensure event_certificates table exists
-- ============================================================
CREATE TABLE IF NOT EXISTS event_certificates (
    id              SERIAL PRIMARY KEY,
    event_id        INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    user_id         INTEGER NOT NULL REFERENCES youth_users(id) ON DELETE CASCADE,
    cert_number     VARCHAR(50) NOT NULL UNIQUE,
    generated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_at     TIMESTAMP,
    upload_filename VARCHAR(255),
    upload_original VARCHAR(255),
    merit_awarded   BOOLEAN NOT NULL DEFAULT FALSE,
    merit_points    SMALLINT NOT NULL DEFAULT 2,
    UNIQUE (event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_certs_event ON event_certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_event_certs_user ON event_certificates(user_id);
CREATE INDEX IF NOT EXISTS idx_event_certs_number ON event_certificates(cert_number);

-- ============================================================
-- Step 4: Create indexes for events table
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_events_checkin_code ON events(checkin_code);
CREATE INDEX IF NOT EXISTS idx_events_checkout_open ON events(checkout_open);
CREATE INDEX IF NOT EXISTS idx_events_date ON events(event_date);
CREATE INDEX IF NOT EXISTS idx_events_qr_token ON events(qr_token);
CREATE INDEX IF NOT EXISTS idx_events_year_quarter ON events(year, quarter);

-- ============================================================
-- Step 5: Verify the setup
-- ============================================================
DO $$
DECLARE
    events_count INTEGER;
    checkins_count INTEGER;
    certs_count INTEGER;
BEGIN
    -- Count tables
    SELECT COUNT(*) INTO events_count FROM events;
    SELECT COUNT(*) INTO checkins_count FROM event_checkins;
    SELECT COUNT(*) INTO certs_count FROM event_certificates;
    
    -- Display results
    RAISE NOTICE '========================================';
    RAISE NOTICE 'EVENTS MODULE SETUP COMPLETE!';
    RAISE NOTICE '========================================';
    RAISE NOTICE 'Events: % records', events_count;
    RAISE NOTICE 'Check-ins: % records', checkins_count;
    RAISE NOTICE 'Certificates: % records', certs_count;
    RAISE NOTICE '========================================';
    RAISE NOTICE 'All tables and indexes created successfully.';
    RAISE NOTICE 'Events page should now work without errors!';
    RAISE NOTICE '========================================';
END $$;

-- ============================================================
-- Optional: View events table structure
-- ============================================================
-- Uncomment to see the complete events table structure:
/*
SELECT 
    column_name, 
    data_type, 
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_name = 'events' 
  AND table_schema = 'public'
ORDER BY ordinal_position;
*/
