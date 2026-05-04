-- Add star_name to healing form submissions (MySQL)
ALTER TABLE healing_form_submissions
    ADD COLUMN star_name VARCHAR(150) NULL AFTER aadhaar_back_path;
