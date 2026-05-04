-- Sacred program / course registration form fields (SQLite)
ALTER TABLE class_payments ADD COLUMN age_or_birth TEXT NULL;
ALTER TABLE class_payments ADD COLUMN qualification TEXT NULL;
ALTER TABLE class_payments ADD COLUMN father_name TEXT NULL;
ALTER TABLE class_payments ADD COLUMN father_phone TEXT NULL;
ALTER TABLE class_payments ADD COLUMN mother_name TEXT NULL;
ALTER TABLE class_payments ADD COLUMN mother_phone TEXT NULL;
ALTER TABLE class_payments ADD COLUMN why_attend_course TEXT NULL;
ALTER TABLE class_payments ADD COLUMN additional_message TEXT NULL;
