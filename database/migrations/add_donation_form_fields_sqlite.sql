-- Email, place, donation category, and individual photo path (SQLite).
ALTER TABLE donations ADD COLUMN email TEXT NULL;
ALTER TABLE donations ADD COLUMN place TEXT NULL;
ALTER TABLE donations ADD COLUMN donation_category TEXT NULL;
ALTER TABLE donations ADD COLUMN individual_photo_path TEXT NULL;
