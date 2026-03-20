-- ----------------------------
-- Fix wishlist table - change user_id from INTEGER to TEXT
-- ----------------------------

-- Create new table with correct schema
CREATE TABLE wishlist_new (
  id BIGSERIAL PRIMARY KEY,
  user_id TEXT NOT NULL,
  artist TEXT NOT NULL,
  album TEXT,
  user_comment TEXT,
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending','in_progress','completed','rejected')),
  admin_comment TEXT,
  lastfm_artist_mbid TEXT,
  lastfm_album_mbid TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT fk_wishlist_new_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Copy data from old table (if any exists)
INSERT INTO wishlist_new SELECT * FROM wishlist;

-- Drop old table
DROP TABLE wishlist;

-- Rename new table
ALTER TABLE wishlist_new RENAME TO wishlist;

-- Recreate indexes
CREATE INDEX IF NOT EXISTS idx_wishlist_user_id ON wishlist(user_id);
CREATE INDEX IF NOT EXISTS idx_wishlist_status ON wishlist(status);
CREATE INDEX IF NOT EXISTS idx_wishlist_created_at ON wishlist(created_at DESC);
