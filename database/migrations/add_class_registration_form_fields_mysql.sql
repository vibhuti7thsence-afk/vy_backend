-- Sacred program / course registration form fields (MySQL)
ALTER TABLE class_payments
    ADD COLUMN age_or_birth VARCHAR(100) NULL AFTER siblings_name,
    ADD COLUMN qualification VARCHAR(255) NULL AFTER age_or_birth,
    ADD COLUMN father_name VARCHAR(150) NULL AFTER qualification,
    ADD COLUMN father_phone VARCHAR(20) NULL AFTER father_name,
    ADD COLUMN mother_name VARCHAR(150) NULL AFTER father_phone,
    ADD COLUMN mother_phone VARCHAR(20) NULL AFTER mother_name,
    ADD COLUMN why_attend_course TEXT NULL AFTER message,
    ADD COLUMN additional_message TEXT NULL AFTER why_attend_course;
