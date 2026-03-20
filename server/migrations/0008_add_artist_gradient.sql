-- Migration: Add artist_gradient column to artists table
-- This stores a JSON object with gradient colors for placeholder display
-- Format: {"colors": ["#1a2b3c", "#4d5e6f"], "angle": 135}

ALTER TABLE artists ADD COLUMN artist_gradient TEXT DEFAULT NULL;
