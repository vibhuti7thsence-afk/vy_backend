-- Add star_name to healing form submissions (SQLite)
ALTER TABLE healing_form_submissions
    ADD COLUMN star_name TEXT NULL;
