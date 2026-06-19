CREATE TABLE IF NOT EXISTS classes (
    id SERIAL PRIMARY KEY,
    class_name VARCHAR(255) NOT NULL,
    total_fee DECIMAL(10,2) NOT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS class_payments (
    id BIGSERIAL PRIMARY KEY,
    mobile VARCHAR(20) NOT NULL,
    aadhaar_number VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NULL,
    class_id INTEGER NOT NULL,
    preferred_time VARCHAR(100) NULL,
    location VARCHAR(255) NULL,
    siblings_name VARCHAR(255) NULL,
    age_or_birth VARCHAR(100) NULL,
    qualification VARCHAR(255) NULL,
    father_name VARCHAR(150) NULL,
    father_phone VARCHAR(20) NULL,
    mother_name VARCHAR(150) NULL,
    mother_phone VARCHAR(20) NULL,
    message TEXT NULL,
    why_attend_course TEXT NULL,
    additional_message TEXT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(150) NULL,
    transaction_msg VARCHAR(500) NULL,
    aadhaar_doc_path VARCHAR(500) NULL,
    aadhaar_doc_back_path VARCHAR(500) NULL,
    transaction_receipt_path VARCHAR(500) NULL,
    payment_status VARCHAR(10) NOT NULL CHECK (payment_status IN ('partial', 'paid')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_class_payments_class_id
        FOREIGN KEY (class_id) REFERENCES classes(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_class_payments_mobile_class ON class_payments (mobile, class_id);
CREATE INDEX IF NOT EXISTS idx_class_payments_aadhaar_class ON class_payments (aadhaar_number, class_id);

CREATE TABLE IF NOT EXISTS class_user_fees (
    id SERIAL PRIMARY KEY,
    aadhaar_number VARCHAR(20) NOT NULL,
    class_id INTEGER NOT NULL,
    agreed_fee DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (aadhaar_number, class_id),
    CONSTRAINT fk_class_user_fees_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgname = 'trg_class_user_fees_updated_at') THEN
        CREATE TRIGGER trg_class_user_fees_updated_at
            BEFORE UPDATE ON class_user_fees
            FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_class_user_fees_aadhaar_class ON class_user_fees (aadhaar_number, class_id);

CREATE TABLE IF NOT EXISTS donations (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NULL,
    place VARCHAR(255) NULL,
    donation_category VARCHAR(150) NULL,
    mobile VARCHAR(20) NOT NULL,
    aadhaar_number VARCHAR(20) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(150) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    aadhaar_front_path VARCHAR(500) NULL,
    aadhaar_back_path VARCHAR(500) NULL,
    transaction_rep_path VARCHAR(500) NULL,
    individual_photo_path VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_donations_mobile ON donations (mobile);
CREATE INDEX IF NOT EXISTS idx_donations_aadhaar ON donations (aadhaar_number);

CREATE TABLE IF NOT EXISTS yoga_form_submissions (
    id BIGSERIAL PRIMARY KEY,
    author_name VARCHAR(150) NOT NULL,
    father_or_mother_name VARCHAR(150) NOT NULL,
    course_name VARCHAR(150) NOT NULL,
    year_of_learning VARCHAR(100) NULL,
    qualification VARCHAR(255) NOT NULL,
    previous_course VARCHAR(255) NULL,
    sibling_details VARCHAR(255) NULL,
    age_or_birth_date VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    aadhaar_front_path VARCHAR(500) NOT NULL,
    aadhaar_back_path VARCHAR(500) NOT NULL,
    mentor_name VARCHAR(150) NULL,
    mentor_occupation VARCHAR(150) NULL,
    mentor_phone VARCHAR(20) NULL,
    referrer_name VARCHAR(150) NULL,
    referrer_phone VARCHAR(20) NULL,
    referrer_occupation VARCHAR(150) NULL,
    another_referrer_name VARCHAR(150) NULL,
    another_referrer_phone VARCHAR(20) NULL,
    another_referrer_occupation VARCHAR(150) NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(150) NULL,
    transaction_receipt_path VARCHAR(500) NOT NULL,
    additional_message TEXT NULL,
    mobile VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_yoga_form_submissions_mobile ON yoga_form_submissions (mobile);

CREATE TABLE IF NOT EXISTS healing_form_submissions (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    date_of_birth VARCHAR(50) NOT NULL,
    time_of_birth VARCHAR(50) NULL,
    place_of_birth VARCHAR(255) NULL,
    current_location VARCHAR(255) NULL,
    mobile VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    aadhaar_number VARCHAR(20) NOT NULL,
    aadhaar_front_path VARCHAR(500) NULL,
    aadhaar_back_path VARCHAR(500) NULL,
    star_name VARCHAR(150) NULL,
    issue_type VARCHAR(150) NULL,
    issue_description TEXT NULL,
    current_picture_path VARCHAR(500) NULL,
    declaration_accepted SMALLINT NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(150) NULL,
    transaction_receipt_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_healing_form_submissions_mobile ON healing_form_submissions (mobile);
