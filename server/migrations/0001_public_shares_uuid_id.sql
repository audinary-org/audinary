-- Migration: Convert public_shares.id from BIGINT to TEXT for UUID support
-- Created: 2025-09-04
-- No data migration needed - new feature

-- Drop existing indexes first (safer approach)
DROP INDEX IF EXISTS idx_public_shares_created_by;
DROP INDEX IF EXISTS idx_public_shares_expires;
DROP INDEX IF EXISTS idx_public_shares_uuid;

-- Drop the old table (no data to preserve)
DROP TABLE IF EXISTS public_shares;

-- Create a new table with the correct schema
CREATE TABLE IF NOT EXISTS public_shares (
  id TEXT PRIMARY KEY,
  type TEXT NOT NULL,
  item_id TEXT NOT NULL,
  share_uuid TEXT NOT NULL,
  download_enabled INTEGER NOT NULL DEFAULT 0,
  expires_at TIMESTAMPTZ,
  access_count INTEGER NOT NULL DEFAULT 0,
  password_hash TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  created_by TEXT NOT NULL,
  CONSTRAINT fk_public_shares_created_by FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT uq_public_shares_uuid UNIQUE (share_uuid),
  CONSTRAINT uq_public_shares_item UNIQUE (type, item_id),
  CONSTRAINT chk_public_shares_type CHECK (type IN ('song','album','playlist'))
);

-- Recreate indexes
CREATE INDEX idx_public_shares_created_by ON public_shares(created_by);
CREATE INDEX idx_public_shares_expires ON public_shares(expires_at);
CREATE INDEX idx_public_shares_uuid ON public_shares(share_uuid);