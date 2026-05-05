# Vibhuti Yoga Backend API

Base paths below are relative; prepend your host (e.g. `https://example.com`).

**Machine-readable spec:** `GET /api/openapi.json` or Swagger UI at `GET /docs` / `GET /api/docs`.

## Conventions

| Topic | Detail |
|-------|--------|
| JSON APIs | `Content-Type: application/json` |
| File uploads | `multipart/form-data` where noted |
| Success | `{ "success": true, ... }` |
| Error | `{ "success": false, "message": "<reason>" }` — HTTP status 422 (validation/business), 404, 409, 500 |
| Validation | 422 `message` may embed JSON field errors from `Validator` |

---

## Discovery & health

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| GET | `/` | — | `{ "service", "health", "docs" }` |
| GET | `/health`, `/api/health` | — | `{ "status": "ok", "message": "..." }` |
| GET | `/docs`, `/api/docs` | — | HTML (Swagger UI) |
| GET | `/openapi.json`, `/api/openapi.json` | — | OpenAPI 3 JSON |

---

## Classes (courses)

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| GET | `/api/classes` | — | `{ "success": true, "data": [ { "id", "class_name", "total_fee", … } ] }` |
| POST | `/api/classes` | JSON: `{ "class_name", "total_fee", "is_active"? }` | `{ "success": true, "message", "data" }` **201** |
| PUT | `/api/classes` | JSON: `{ "id", "class_name"?, "total_fee"?, "is_active"? }` | `{ "success": true, "message", "data" }` |

---

## Class registration & payments

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| PUT | `/api/classes/agreed-fee` | JSON: `{ "aadhaar_number", "class_id", "agreed_fee" }` | `{ "success": true, "message", "data": { "aadhaar_number", "class_id", "class_name", "agreed_fee", "paid_so_far", "remaining_amount" } }` |
| POST | `/api/classes/register-payment` | **multipart:** identity & course fields — `your_full_name` \| `full_name` \| `name`, `phone_number` \| `mobile` \| `phone`, `aadhaar_number`, `class_id` \| `selected_class_id` \| `course_id`, `fee_amount_paid` \| `amount_paid`, `age_or_birth`, `qualification`, `location`, parent names/phones, `why_attend_course` (or legacy `message`), optional `email`, `additional_message`, `transaction_id`, … **Files:** Aadhaar front/back, receipt (aliases: `aadhaar_front`, `aadhaar_doc`, `transaction_receipt`, `fee_receipt`, … — see OpenAPI). | `{ "success": true, "message", "data": { "payment_id", "class_id", "class_name", "agreed_fee", "amount_paid_now", "paid_till_now", "remaining_amount", "payment_status", "your_full_name", "phone_number", "fee_amount_paid" } }` **201** |
| GET | `/api/classes/payment-summary` | Query: `phone_number` \| `mobile` \| `phone`, `aadhaar_number` \| `aadhaar` | `{ "success": true, "data": [ per-class aggregates: `mobile`, `phone_number`, `aadhaar_number`, `class_id`, `class_name`, fees, `paid_amount`, `remaining_amount`, `pending_amount`, `payment_status` ] }` |
| GET | `/api/classes/payment-transactions` | Same phone + Aadhaar; optional `class_id` \| `selected_class_id` \| `course_id` | `{ "success": true, "data": [ each payment row: `id`, `created_at`, `amount_paid`, `transaction_id`, `payment_status`, `class_id`, `class_name`, `location`, paths… + `phone_number`, `your_full_name`, `present_address`, `fee_amount_paid`, `transaction_reference_id`, `fee_receipt_path` ] }` — newest first |
| GET | `/api/classes/registration-verify` | Query: phone + Aadhaar + `class_id` \| `selected_class_id` \| `course_id` | `{ "success": true, "data": { "status", "can_submit_registration_payment", "message", … } }` |
| POST | `/api/classes/lookup-user` | JSON: `phone_number` \| `mobile` \| `phone`, `aadhaar_number` \| `aadhaar` | `{ "success": true, "data": { "found", "name", "your_full_name"?, "phone_number"?, "message" } }` |

---

## Donations

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| POST | `/api/donations` | **multipart:** `aadhaar_number`, `full_name` \| `name`, `phone_number` \| `mobile`, `place`, `donation_category`, `amount` \| `amount_paid`, optional `email`, `transaction_id`, files `aadhaar_front`, `aadhaar_back`, `receipt_photo`, `individual_photo` (+ legacy `_doc` keys). Donor must have class registration for same phone + Aadhaar. | `{ "success": true, "message", "data": { "donation_id", "amount", "amount_paid", paths + aliases } }` **201** |
| GET | `/api/donations` | Query: phone + Aadhaar aliases | `{ "success": true, "data": [ donation rows + aliases ] }` |
| GET | `/api/donations/verify-eligibility` | Query: phone + Aadhaar aliases | `{ "success": true, "data": { "can_donate", "message" } }` |

---

## Yoga form (Vibhuti Yoga long form)

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| POST | `/api/yoga-form/submissions` | **multipart:** `author_name`, `father_or_mother_name`, `course_name`, `qualification`, `age_or_birth_date`, `location`, `mobile`, `amount_paid`, optional mentor/referrer fields, `transaction_id`, `additional_message`, files `aadhar_card_front`, `aadhar_card_back`, `transaction_receipt_image` | `{ "success": true, "message", "data": { "id", "author_name", "course_name", "amount_paid", "transaction_id", "mobile" } }` **201** |
| GET | `/api/yoga-form/submissions` | Query: `mobile` (10–15 digits) | `{ "success": true, "data": [ … ] }` |

---

## Healing form

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| POST | `/api/healing-form/submissions` | **multipart:** `your_name`, `phone_number` \| `mobile`, `aadhaar_number`, `date_of_birth`, `time_of_birth`, `place_of_birth`, `address`, `issues`, `problem_description`, `fee_amount_paid`, `declaration_accepted`, optional `star`, `email`, `transaction_id`, files `aadhaar_front`, `aadhaar_back`, `recent_picture`, `fee_receipt` (+ legacy aliases) | `{ "success": true, "message", "data": { "id", "full_name", "your_name", "mobile", "phone_number", "aadhaar_number", "amount_paid", "fee_amount_paid" } }` **201** |
| GET | `/api/healing-form/submissions` | Query: phone + Aadhaar aliases | `{ "success": true, "data": [ rows + UI aliases ] }` |

---

## Admin

| Method | Path | Payload | Response |
|--------|------|---------|----------|
| GET | `/api/admin/dashboard` | Query: optional `start_date`, `end_date` (`Y-m-d`) | `{ "success": true, "data": { "overview": { … }, "payment_summary": { … } } }` |
| GET | `/api/admin/course-distribution` | — | `{ "success": true, "data": [ { "class_id", "class_name", "enrollment_count" } ] }` |
| GET | `/api/admin/recent-activity` | Query: `limit` (optional, default 20, max 100) | `{ "success": true, "data": [ { "type": "donation" \| "registration", … } ] }` |
| GET | `/api/admin/registrations` | Query: `search`, `status` (`all`\|`pending`\|`partial`\|`completed`), `limit`, `offset`, `start_date`, `end_date` | `{ "success": true, "data": [ … ], "total": number }` |
| GET | `/api/admin/donations` | Query: `search`, `status` (`all`\|`pending`\|`verified`\|`rejected`), `limit`, `offset`, dates | `{ "success": true, "data": [ … ], "total": number }` |
| GET | `/api/admin/healing-submissions` | Query: `search`, `issue_type`, `limit`, `offset`, dates | `{ "success": true, "data": [ … ], "total": number }` |
| GET | `/api/admin/donations/summary` | Query: optional `start_date`, `end_date` | `{ "success": true, "data": { "total_amount", "verified_count", "pending_count", "rejected_count" } }` |
| PUT | `/api/admin/donations/status` | JSON: `{ "id", "status": "pending" \| "verified" \| "rejected" }` | `{ "success": true, "message", "data": updated donation }` |

---

## Field aliases (quick reference)

Many endpoints accept equivalent keys:

- **Phone:** `phone_number`, `mobile`, `phone`
- **Aadhaar:** `aadhaar_number`, `aadhaar`
- **Course:** `class_id`, `selected_class_id`, `course_id`
- **Registration amount:** `fee_amount_paid`, `amount_paid`, `amount`

Exact multipart field lists and binary keys are defined in `config/openapi.php`.

---

## CORS

`OPTIONS` preflight returns **204**. Responses allow `GET, POST, PUT, PATCH, OPTIONS` and `Content-Type, Authorization` headers (see `public/index.php`).
