-- PostgreSQL-compatible initial schema

CREATE TABLE IF NOT EXISTS users (
  user_id TEXT PRIMARY KEY,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name TEXT,
  email TEXT,
  is_admin INTEGER DEFAULT 0,
  transcoding_quality TEXT,
  session_timeout INTEGER,
  image_uuid TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  last_login TIMESTAMPTZ,
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  can_create_public_share INTEGER DEFAULT 1
);

CREATE TABLE IF NOT EXISTS artists (
  artist_id TEXT PRIMARY KEY,
  artist_name TEXT NOT NULL,
  is_deleted INTEGER DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS albums (
  album_id TEXT PRIMARY KEY,
  artist_id TEXT NOT NULL,
  album_name TEXT NOT NULL,
  album_artist TEXT,
  album_artist_sort TEXT,
  total_discs INTEGER,
  total_tracks INTEGER,
  original_year INTEGER,
  album_duration INTEGER DEFAULT 0,
  album_genre TEXT,
  album_year INTEGER DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  cover_path TEXT,
  is_deleted INTEGER DEFAULT 0,
  folder_path TEXT,
  last_played TIMESTAMPTZ,
  filetype TEXT,
  CONSTRAINT fk_albums_artist FOREIGN KEY (artist_id) REFERENCES artists(artist_id)
);

CREATE INDEX IF NOT EXISTS idx_albums_filetype ON albums(filetype);
CREATE INDEX IF NOT EXISTS idx_albums_last_played ON albums(last_played);

CREATE TABLE IF NOT EXISTS songs (
  song_id TEXT PRIMARY KEY,
  album_id TEXT NOT NULL,
  disc_number INTEGER,
  track_number INTEGER,
  title TEXT,
  artist TEXT,
  genre TEXT,
  year INTEGER,
  duration INTEGER,
  bitrate TEXT,
  size INTEGER,
  last_mtime INTEGER,
  file_path TEXT,
  is_deleted INTEGER DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  last_played TIMESTAMPTZ,
  filetype TEXT,
  CONSTRAINT fk_songs_album FOREIGN KEY (album_id) REFERENCES albums(album_id)
);

CREATE INDEX IF NOT EXISTS idx_songs_filetype ON songs(filetype);
CREATE INDEX IF NOT EXISTS idx_songs_last_played ON songs(last_played);

CREATE TABLE IF NOT EXISTS playlists (
  id BIGSERIAL PRIMARY KEY,
  user_id TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'user',
  name TEXT NOT NULL,
  description TEXT,
  song_count INTEGER NOT NULL DEFAULT 0,
  duration INTEGER NOT NULL DEFAULT 0,
  cover_image_uuid TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT fk_playlists_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT chk_playlists_type CHECK (type IN ('user','global'))
);

CREATE INDEX IF NOT EXISTS idx_playlists_created_at ON playlists(created_at);
CREATE INDEX IF NOT EXISTS idx_playlists_name ON playlists(name);
CREATE INDEX IF NOT EXISTS idx_playlists_updated_at ON playlists(updated_at);
CREATE INDEX IF NOT EXISTS idx_playlists_user_type ON playlists(user_id, type);

CREATE TABLE IF NOT EXISTS public_shares (
  id BIGSERIAL PRIMARY KEY,
  type TEXT NOT NULL,
  item_id TEXT NOT NULL,
  share_uuid TEXT NOT NULL,
  download_enabled INTEGER NOT NULL DEFAULT 0,
  expires_at TIMESTAMPTZ,
  access_count INTEGER NOT NULL DEFAULT 0,
  password_hash TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  created_by TEXT NOT NULL,
  CONSTRAINT fk_public_shares_created_by FOREIGN KEY (created_by) REFERENCES users(user_id),
  CONSTRAINT uq_public_shares_uuid UNIQUE (share_uuid),
  CONSTRAINT uq_public_shares_item UNIQUE (type, item_id),
  CONSTRAINT chk_public_shares_type CHECK (type IN ('song','album','playlist'))
);

CREATE INDEX IF NOT EXISTS idx_public_shares_created_by ON public_shares(created_by);
CREATE INDEX IF NOT EXISTS idx_public_shares_expires ON public_shares(expires_at);
CREATE INDEX IF NOT EXISTS idx_public_shares_uuid ON public_shares(share_uuid);

CREATE TABLE IF NOT EXISTS favorites (
  favorite_id BIGSERIAL PRIMARY KEY,
  user_id TEXT NOT NULL,
  favorite_type TEXT NOT NULL,
  song_id TEXT,
  album_id TEXT,
  artist_id TEXT,
  playlist_id BIGINT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_fav_song FOREIGN KEY (song_id) REFERENCES songs(song_id),
  CONSTRAINT fk_fav_album FOREIGN KEY (album_id) REFERENCES albums(album_id),
  CONSTRAINT fk_fav_artist FOREIGN KEY (artist_id) REFERENCES artists(artist_id),
  CONSTRAINT fk_fav_playlist FOREIGN KEY (playlist_id) REFERENCES playlists(id),
  CONSTRAINT chk_fav_type CHECK (favorite_type IN ('song','album','artist','playlist'))
);

CREATE INDEX IF NOT EXISTS idx_favorites_type ON favorites(favorite_type);
CREATE UNIQUE INDEX IF NOT EXISTS idx_favorites_unique ON favorites(user_id, favorite_type, song_id, album_id, artist_id, playlist_id);
CREATE INDEX IF NOT EXISTS idx_favorites_user ON favorites(user_id);

CREATE TABLE IF NOT EXISTS play_history (
  play_id BIGSERIAL PRIMARY KEY,
  user_id TEXT NOT NULL,
  song_id TEXT NOT NULL,
  played_at TIMESTAMPTZ DEFAULT NOW(),
  play_count INTEGER NOT NULL DEFAULT 1,
  CONSTRAINT fk_play_history_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_play_history_song FOREIGN KEY (song_id) REFERENCES songs(song_id)
);

CREATE INDEX IF NOT EXISTS idx_play_history_played_at ON play_history(played_at);
CREATE INDEX IF NOT EXISTS idx_play_history_user_song ON play_history(user_id, song_id);

CREATE TABLE IF NOT EXISTS playlist_permissions (
  id BIGSERIAL PRIMARY KEY,
  playlist_id BIGINT NOT NULL,
  user_id TEXT NOT NULL,
  permission_type TEXT NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT fk_playlist_permissions_playlist FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_playlist_permissions_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT uq_playlist_permissions UNIQUE (playlist_id, user_id),
  CONSTRAINT chk_playlist_permissions_type CHECK (permission_type IN ('view','edit'))
);

CREATE INDEX IF NOT EXISTS idx_playlist_permissions_user ON playlist_permissions(user_id);

CREATE TABLE IF NOT EXISTS playlist_songs (
  id BIGSERIAL PRIMARY KEY,
  playlist_id BIGINT NOT NULL,
  song_id TEXT NOT NULL,
  position NUMERIC(10,2) NOT NULL,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  CONSTRAINT fk_playlist_songs_playlist FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_playlist_songs_song FOREIGN KEY (song_id) REFERENCES songs(song_id) ON DELETE CASCADE,
  CONSTRAINT uq_playlist_songs UNIQUE (playlist_id, position)
);

CREATE INDEX IF NOT EXISTS idx_playlist_songs_playlist_position ON playlist_songs(playlist_id, position);

CREATE TABLE IF NOT EXISTS global_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT
);

CREATE TABLE IF NOT EXISTS user_settings (
  user_id TEXT NOT NULL,
  setting_key TEXT NOT NULL,
  setting_value TEXT,
  PRIMARY KEY (user_id, setting_key),
  CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE IF NOT EXISTS password_reset_rate_limit (
  id BIGSERIAL PRIMARY KEY,
  ip_address TEXT NOT NULL,
  request_count INTEGER NOT NULL DEFAULT 1,
  last_request_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  reset_hour TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_password_reset_rate_limit_cleanup ON password_reset_rate_limit(reset_hour);
CREATE UNIQUE INDEX IF NOT EXISTS idx_password_reset_rate_limit_ip_hour ON password_reset_rate_limit(ip_address, reset_hour);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGSERIAL PRIMARY KEY,
  user_id TEXT NOT NULL,
  token TEXT NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  used_at TIMESTAMPTZ,
  ip_address TEXT,
  CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT uq_password_reset_token UNIQUE (token)
);

CREATE INDEX IF NOT EXISTS idx_password_reset_cleanup ON password_reset_tokens(expires_at, used_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_expires_at ON password_reset_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_token_valid ON password_reset_tokens(token, expires_at, used_at);
CREATE INDEX IF NOT EXISTS idx_password_reset_user_id ON password_reset_tokens(user_id);

CREATE TABLE IF NOT EXISTS migrations (
  id BIGSERIAL PRIMARY KEY,
  migration_name TEXT NOT NULL UNIQUE,
  executed_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS scan_status (
  id BIGSERIAL PRIMARY KEY,
  process_id INTEGER,
  status TEXT NOT NULL DEFAULT 'idle',
  option_name TEXT,
  full_scan INTEGER DEFAULT 0,
  start_time INTEGER,
  end_time INTEGER,
  total_albums INTEGER DEFAULT 0,
  processed_albums INTEGER DEFAULT 0,
  current_album TEXT,
  current_step TEXT,
  duration INTEGER DEFAULT 0,
  statistics TEXT,
  error_message TEXT,
  created_at TIMESTAMPTZ DEFAULT NOW(),
  updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Playlist stats trigger and function
CREATE OR REPLACE FUNCTION playlist_update_stats() RETURNS trigger AS $$
BEGIN
    UPDATE playlists
    SET song_count = (SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = COALESCE(NEW.playlist_id, OLD.playlist_id)),
        duration = (
            SELECT COALESCE(SUM(s.duration), 0)
            FROM playlist_songs ps
            JOIN songs s ON ps.song_id = s.song_id
            WHERE ps.playlist_id = COALESCE(NEW.playlist_id, OLD.playlist_id)
        ),
        updated_at = NOW()
    WHERE id = COALESCE(NEW.playlist_id, OLD.playlist_id);
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_playlist_songs_insert
AFTER INSERT ON playlist_songs
FOR EACH ROW EXECUTE FUNCTION playlist_update_stats();

CREATE TRIGGER trg_playlist_songs_delete
AFTER DELETE ON playlist_songs
FOR EACH ROW EXECUTE FUNCTION playlist_update_stats();

