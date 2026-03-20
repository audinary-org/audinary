-- Migration: Add cover_gradient column to albums table
-- This stores a JSON object with gradient colors for placeholder display
-- Format: {"colors": ["#1a2b3c", "#4d5e6f"], "angle": 135}

ALTER TABLE albums ADD COLUMN cover_gradient TEXT DEFAULT NULL;
