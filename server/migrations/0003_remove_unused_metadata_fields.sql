-- Migration: Remove unused metadata fields from albums, songs, and artists tables
-- Date: 2025-10-10
-- Description: Removes MusicBrainz, ISRC, and other metadata fields that are not used by the application

-- ============================================================================
-- ALBUMS TABLE - Remove unused metadata fields
-- ============================================================================

-- Remove MusicBrainz fields
ALTER TABLE albums DROP COLUMN IF EXISTS musicbrainz_albumartistid;
ALTER TABLE albums DROP COLUMN IF EXISTS musicbrainz_albumid;
ALTER TABLE albums DROP COLUMN IF EXISTS musicbrainz_releasegroupid;

-- Remove release metadata fields
ALTER TABLE albums DROP COLUMN IF EXISTS release_country;
ALTER TABLE albums DROP COLUMN IF EXISTS release_status;
ALTER TABLE albums DROP COLUMN IF EXISTS release_type;

-- Remove commercial/catalog fields
ALTER TABLE albums DROP COLUMN IF EXISTS asin;
ALTER TABLE albums DROP COLUMN IF EXISTS barcode;
ALTER TABLE albums DROP COLUMN IF EXISTS catalog_number;
ALTER TABLE albums DROP COLUMN IF EXISTS label;

-- Remove other unused fields
ALTER TABLE albums DROP COLUMN IF EXISTS media;
ALTER TABLE albums DROP COLUMN IF EXISTS script;

-- ============================================================================
-- SONGS TABLE - Remove unused metadata fields
-- ============================================================================

-- Remove MusicBrainz fields
ALTER TABLE songs DROP COLUMN IF EXISTS musicbrainz_artistid;
ALTER TABLE songs DROP COLUMN IF EXISTS musicbrainz_trackid;
ALTER TABLE songs DROP COLUMN IF EXISTS musicbrainz_releasetrackid;

-- Remove ISRC and other metadata
ALTER TABLE songs DROP COLUMN IF EXISTS isrc;
ALTER TABLE songs DROP COLUMN IF EXISTS script;

-- Remove artist variant fields (not displayed in UI)
ALTER TABLE songs DROP COLUMN IF EXISTS artist_sort;
ALTER TABLE songs DROP COLUMN IF EXISTS artists;

-- ============================================================================
-- ARTISTS TABLE - Remove unused metadata fields
-- ============================================================================

-- Remove biography and MusicBrainz fields
ALTER TABLE artists DROP COLUMN IF EXISTS bio;
ALTER TABLE artists DROP COLUMN IF EXISTS mbid;
ALTER TABLE artists DROP COLUMN IF EXISTS lastfm_url;

-- Remove image URL fields (images are stored as files, not in DB)
ALTER TABLE artists DROP COLUMN IF EXISTS image_small;
ALTER TABLE artists DROP COLUMN IF EXISTS image_medium;
ALTER TABLE artists DROP COLUMN IF EXISTS image_large;
