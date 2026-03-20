-- Migration: Add image column to artists table
-- Date: 2025-10-20
-- Description: Adds a single 'image' column to store the relative path to the artist's image file

-- Add image column to artists table
ALTER TABLE artists ADD COLUMN image TEXT DEFAULT NULL;

-- Create index for faster queries on artists with/without images
CREATE INDEX IF NOT EXISTS idx_artists_image ON artists(image);

