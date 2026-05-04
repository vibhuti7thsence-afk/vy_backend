CREATE TABLE IF NOT EXISTS classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT NOT NULL,
    total_fee NUMERIC NOT NULL CHECK(total_fee > 0),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS class_payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mobile TEXT NOT NULL,
    aadhaar_number TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NULL,
    class_id INTEGER NOT NULL,
    preferred_time TEXT NULL,
    location TEXT NULL,
    siblings_name TEXT NULL,
    age_or_birth TEXT NULL,
    qualification TEXT NULL,
    father_name TEXT NULL,
    father_phone TEXT NULL,
    mother_name TEXT NULL,
    mother_phone TEXT NULL,
    message TEXT NULL,
    why_attend_course TEXT NULL,
    additional_message TEXT NULL,
    amount_paid NUMERIC NOT NULL CHECK(amount_paid > 0),
    transaction_id TEXT NULL,
    transaction_msg TEXT NULL,
    aadhaar_doc_path TEXT NULL,
    aadhaar_doc_back_path TEXT NULL,
    transaction_receipt_path TEXT NULL,
    payment_status TEXT NOT NULL CHECK(payment_status IN ('partial', 'paid')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE INDEX IF NOT EXISTS idx_class_payments_mobile_class
ON class_payments (mobile, class_id);

CREATE INDEX IF NOT EXISTS idx_class_payments_aadhaar_class
ON class_payments (aadhaar_number, class_id);

CREATE TABLE IF NOT EXISTS class_user_fees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    aadhaar_number TEXT NOT NULL,
    class_id INTEGER NOT NULL,
    agreed_fee NUMERIC NOT NULL CHECK(agreed_fee > 0),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(aadhaar_number, class_id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE INDEX IF NOT EXISTS idx_class_user_fees_aadhaar_class
ON class_user_fees (aadhaar_number, class_id);

CREATE TABLE IF NOT EXISTS donations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NULL,
    place TEXT NULL,
    donation_category TEXT NULL,
    mobile TEXT NOT NULL,
    aadhaar_number TEXT NOT NULL,
    amount_paid NUMERIC NOT NULL CHECK(amount_paid > 0),
    transaction_id TEXT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'verified', 'rejected')),
    aadhaar_front_path TEXT NULL,
    aadhaar_back_path TEXT NULL,
    transaction_rep_path TEXT NULL,
    individual_photo_path TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_donations_mobile
ON donations (mobile);

CREATE INDEX IF NOT EXISTS idx_donations_aadhaar
ON donations (aadhaar_number);

CREATE TABLE IF NOT EXISTS yoga_form_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_name TEXT NOT NULL,
    father_or_mother_name TEXT NOT NULL,
    course_name TEXT NOT NULL,
    year_of_learning TEXT NULL,
    qualification TEXT NOT NULL,
    previous_course TEXT NULL,
    sibling_details TEXT NULL,
    age_or_birth_date TEXT NOT NULL,
    location TEXT NOT NULL,
    aadhaar_front_path TEXT NOT NULL,
    aadhaar_back_path TEXT NOT NULL,
    mentor_name TEXT NULL,
    mentor_occupation TEXT NULL,
    mentor_phone TEXT NULL,
    referrer_name TEXT NULL,
    referrer_phone TEXT NULL,
    referrer_occupation TEXT NULL,
    another_referrer_name TEXT NULL,
    another_referrer_phone TEXT NULL,
    another_referrer_occupation TEXT NULL,
    amount_paid NUMERIC NOT NULL CHECK(amount_paid > 0),
    transaction_id TEXT NULL,
    transaction_receipt_path TEXT NOT NULL,
    additional_message TEXT NULL,
    mobile TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_yoga_form_submissions_mobile
ON yoga_form_submissions (mobile);

CREATE TABLE IF NOT EXISTS healing_form_submissions (
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
    aadhaar_front_path TEXT NOT NULL,
    aadhaar_back_path TEXT NOT NULL,
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

CREATE INDEX IF NOT EXISTS idx_healing_form_submissions_mobile
ON healing_form_submissions (mobile);
