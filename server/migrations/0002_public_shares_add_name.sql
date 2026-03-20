-- Migration: Add name field to public_shares table
-- Date: 2025-10-02

ALTER TABLE "public_shares" ADD COLUMN "name" TEXT DEFAULT NULL;