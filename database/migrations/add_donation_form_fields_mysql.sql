-- Email, place, donation category, and individual photo path (MySQL).
-- place and donation_category are NULL for legacy rows; new submits require both.
ALTER TABLE donations
    ADD COLUMN email VARCHAR(255) NULL AFTER name,
    ADD COLUMN place VARCHAR(255) NULL AFTER email,
    ADD COLUMN donation_category VARCHAR(150) NULL AFTER place,
    ADD COLUMN individual_photo_path VARCHAR(500) NULL AFTER transaction_rep_path;
