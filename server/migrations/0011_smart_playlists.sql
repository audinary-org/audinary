-- Smart Playlists: Allow 'smart' as playlist type and add rules columns

-- Extend type constraint to include 'smart' (drop if exists, then re-add)
DO $$
BEGIN
    ALTER TABLE playlists DROP CONSTRAINT IF EXISTS chk_playlists_type;
EXCEPTION WHEN undefined_object THEN
    -- constraint doesn't exist, nothing to do
END;
$$;
ALTER TABLE playlists ADD CONSTRAINT chk_playlists_type
    CHECK (type IN ('user', 'global', 'smart'));

-- Add smart playlist columns
ALTER TABLE playlists ADD COLUMN IF NOT EXISTS rules JSONB DEFAULT NULL;
ALTER TABLE playlists ADD COLUMN IF NOT EXISTS smart_sort_by TEXT DEFAULT NULL;
ALTER TABLE playlists ADD COLUMN IF NOT EXISTS smart_sort_direction TEXT DEFAULT 'asc';
ALTER TABLE playlists ADD COLUMN IF NOT EXISTS smart_limit INTEGER DEFAULT NULL;

-- Index for quick type lookup
CREATE INDEX IF NOT EXISTS idx_playlists_type ON playlists(type);
