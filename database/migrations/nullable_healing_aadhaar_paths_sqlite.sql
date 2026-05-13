-- SQLite does not support ALTER COLUMN; recreate the table with nullable aadhaar paths.
PRAGMA foreign_keys = OFF;

CREATE TABLE healing_form_submissions_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    date_of_birth TEXT NOT NULL,
    time_of_birth TEXT NULL,
    place_of_birth TEXT NULL,
    current_location TEXT NULL,
    mobile TEXT NOT NULL,
    email TEXT NULL,
    address TEXT NULL,
    aadhaar_number TEXT NOT NULL,
    aadhaar_front_path TEXT NULL,
    aadhaar_back_path TEXT NULL,
    star_name TEXT NULL,
    issue_type TEXT NULL,
    issue_description TEXT NULL,
    current_picture_path TEXT NULL,
    declaration_accepted INTEGER NOT NULL DEFAULT 0,
    amount_paid NUMERIC NOT NULL CHECK(amount_paid > 0),
    transaction_id TEXT NULL,
    transaction_receipt_path TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO healing_form_submissions_new SELECT * FROM healing_form_submissions;

DROP TABLE healing_form_submissions;
ALTER TABLE healing_form_submissions_new RENAME TO healing_form_submissions;

CREATE INDEX IF NOT EXISTS idx_healing_form_submissions_mobile
ON healing_form_submissions (mobile);

PRAGMA foreign_keys = ON;
