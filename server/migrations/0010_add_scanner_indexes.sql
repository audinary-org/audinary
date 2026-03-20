-- Add missing indexes for scanner performance (critical after SQLite -> PostgreSQL migration)

-- Songs: album_id is used in album stats queries and bulk operations
CREATE INDEX IF NOT EXISTS idx_songs_album_id ON songs(album_id);

-- Songs: file_path is used to look up existing songs during scan
CREATE INDEX IF NOT EXISTS idx_songs_file_path ON songs(file_path);

-- Songs: composite index for scanner queries filtering by album + deleted status
CREATE INDEX IF NOT EXISTS idx_songs_album_id_is_deleted ON songs(album_id, is_deleted);

-- Albums: folder_path is used to find albums by directory path during scan
CREATE INDEX IF NOT EXISTS idx_albums_folder_path ON albums(folder_path);

-- Albums: is_deleted filtering
CREATE INDEX IF NOT EXISTS idx_albums_is_deleted ON albums(is_deleted);

-- Artists: artist_name + is_deleted for artist lookup during scan
CREATE INDEX IF NOT EXISTS idx_artists_name_not_deleted ON artists(artist_name, is_deleted);
