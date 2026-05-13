CREATE TABLE IF NOT EXISTS healing_form_submissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    issue_type VARCHAR(150) NULL,
    issue_description TEXT NULL,
    current_picture_path VARCHAR(500) NULL,
    declaration_accepted TINYINT(1) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) NOT NULL,
    transaction_id VARCHAR(150) NULL,
    transaction_receipt_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_healing_form_submissions_mobile ON healing_form_submissions (mobile);
