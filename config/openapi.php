<?php

declare(strict_types=1);

/**
 * OpenAPI 3.0 spec for Yoga API. Served at GET /api/openapi.json and embedded in /api/docs.
 */

$paths = [];

$paths['/'] = [
    'get' => [
        'summary' => 'Root',
        'operationId' => 'root',
        'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['service' => ['type' => 'string'], 'health' => ['type' => 'string'], 'docs' => ['type' => 'string']]]]]]],
    ],
];

$paths['/api/health'] = [
    'get' => [
        'summary' => 'Health check',
        'operationId' => 'health',
        'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'message' => ['type' => 'string']]]]]]],
    ],
];

$paths['/api/classes'] = [
    'get' => [
        'summary' => 'List classes',
        'operationId' => 'listClasses',
        'responses' => ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'class_name' => ['type' => 'string'], 'total_fee' => ['type' => 'number']]]]]]]]]],
    ],
    'post' => [
        'summary' => 'Create class',
        'operationId' => 'createClass',
        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['class_name', 'total_fee'], 'properties' => ['class_name' => ['type' => 'string'], 'total_fee' => ['type' => 'number', 'minimum' => 0.01], 'is_active' => ['type' => 'boolean']]]]]],
        'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Validation failed']],
    ],
    'put' => [
        'summary' => 'Update class',
        'operationId' => 'updateClass',
        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['id'], 'properties' => ['id' => ['type' => 'integer'], 'class_name' => ['type' => 'string'], 'total_fee' => ['type' => 'number'], 'is_active' => ['type' => 'boolean']]]]]],
        'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Class not found'], '422' => ['description' => 'Validation failed']],
    ],
];

$paths['/api/classes/agreed-fee'] = [
    'put' => [
        'summary' => 'Admin: set agreed/negotiated fee for a user and class',
        'operationId' => 'putAgreedFee',
        'description' => 'Set or update the agreed fee for a specific person (Aadhaar) and class. Used when the user negotiates the price; remaining and pending amount are computed from this agreed fee.',
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['aadhaar_number', 'class_id', 'agreed_fee'],
                        'properties' => [
                            'aadhaar_number' => ['type' => 'string', 'description' => '12-digit Aadhaar number'],
                            'class_id' => ['type' => 'integer'],
                            'agreed_fee' => ['type' => 'number', 'minimum' => 0.01, 'description' => 'Negotiated/agreed fee for this user and class'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Class not found'], '422' => ['description' => 'Validation failed']],
    ],
];

$paths['/api/classes/register-payment'] = [
    'post' => [
        'summary' => 'Register class & record payment',
        'operationId' => 'registerPayment',
        'requestBody' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['name', 'mobile', 'aadhaar_number', 'class_id', 'amount_paid', 'aadhaar_doc', 'aadhaar_doc_back', 'transaction_receipt_image'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'mobile' => ['type' => 'string'],
                            'aadhaar_number' => ['type' => 'string', 'description' => '12-digit Aadhaar number (identifies the person; same mobile can have multiple persons)'],
                            'email' => ['type' => 'string'],
                            'class_id' => ['type' => 'integer'],
                            'preferred_time' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                            'siblings_name' => ['type' => 'string'],
                            'transaction_msg' => ['type' => 'string'],
                            'transaction_id' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'amount_paid' => ['type' => 'number'],
                            'aadhaar_doc' => ['type' => 'string', 'format' => 'binary'],
                            'aadhaar_doc_back' => ['type' => 'string', 'format' => 'binary'],
                            'transaction_receipt_image' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['201' => ['description' => 'Created'], '404' => ['description' => 'Class not found'], '409' => ['description' => 'Already paid'], '422' => ['description' => 'Validation failed']],
    ],
];

$paths['/api/classes/payment-summary'] = [
    'get' => [
        'summary' => 'User: pending amount and payment summary by mobile and Aadhaar',
        'operationId' => 'paymentSummary',
        'description' => 'Returns class payment summary for the given person (mobile + Aadhaar). One row per class they have registered for. Each row includes agreed_fee (negotiated price for that user), paid_amount, remaining_amount, pending_amount (same as remaining), and payment_status. Use this so the user can see how much they have paid and how much is pending.',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10–15 digits)'],
            ['name' => 'aadhaar_number', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => '12-digit Aadhaar number'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Missing or invalid mobile / aadhaar_number']],
    ],
];

$paths['/api/classes/registration-verify'] = [
    'get' => [
        'summary' => 'Verify class registration eligibility before payment',
        'operationId' => 'verifyRegistration',
        'description' => 'Checks whether this Aadhaar can register for the given class: new user, same person continuing payment, fee already complete, or Aadhaar conflict (same Aadhaar already used for this class with a different mobile). Mobile numbers can be shared by different Aadhaar holders.',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10–15 digits)'],
            ['name' => 'aadhaar_number', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => '12-digit Aadhaar number'],
            ['name' => 'class_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer'], 'description' => 'Class id from GET /api/classes'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Class not found'], '422' => ['description' => 'Missing or invalid query params']],
    ],
];

$paths['/api/classes/lookup-user'] = [
    'post' => [
        'summary' => 'Lookup registered user by mobile and Aadhaar',
        'operationId' => 'lookupRegisteredUser',
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['mobile', 'aadhaar_number'],
                        'properties' => [
                            'mobile' => ['type' => 'string', 'description' => 'Mobile number (10-15 digits)'],
                            'aadhaar_number' => ['type' => 'string', 'description' => '12-digit Aadhaar number'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['200' => ['description' => 'User found or not found'], '422' => ['description' => 'Validation failed']],
    ],
];

$paths['/api/donations'] = [
    'post' => [
        'summary' => 'Submit donation',
        'operationId' => 'createDonation',
        'requestBody' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['name', 'mobile', 'aadhaar_number', 'amount_paid', 'aadhaar_front_doc', 'aadhaar_back_doc', 'transaction_rep_doc'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'mobile' => ['type' => 'string'],
                            'aadhaar_number' => ['type' => 'string', 'description' => '12-digit Aadhaar number'],
                            'amount_paid' => ['type' => 'number'],
                            'transaction_id' => ['type' => 'string'],
                            'aadhaar_front_doc' => ['type' => 'string', 'format' => 'binary'],
                            'aadhaar_back_doc' => ['type' => 'string', 'format' => 'binary'],
                            'transaction_rep_doc' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Validation failed, or donor has no class registration for this mobile and Aadhaar']],
    ],
    'get' => [
        'summary' => 'Donation history by mobile and Aadhaar',
        'operationId' => 'listDonations',
        'description' => 'Returns donations for the given person (mobile + Aadhaar).',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10–15 digits)'],
            ['name' => 'aadhaar_number', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => '12-digit Aadhaar number'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Missing or invalid mobile / aadhaar_number']],
    ],
];

$paths['/api/donations/verify-eligibility'] = [
    'get' => [
        'summary' => 'Verify donor has completed class registration',
        'operationId' => 'verifyDonationEligibility',
        'description' => 'Donations require at least one class registration payment row with the same mobile and Aadhaar. Returns can_donate and a message when false.',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10–15 digits)'],
            ['name' => 'aadhaar_number', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => '12-digit Aadhaar number'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Missing or invalid query params']],
    ],
];

$paths['/api/yoga-form/submissions'] = [
    'post' => [
        'summary' => 'Submit Vibhuti Yoga form',
        'operationId' => 'submitYogaForm',
        'requestBody' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => [
                            'author_name',
                            'father_or_mother_name',
                            'course_name',
                            'qualification',
                            'age_or_birth_date',
                            'location',
                            'mobile',
                            'amount_paid',
                            'aadhar_card_front',
                            'aadhar_card_back',
                            'transaction_receipt_image',
                        ],
                        'properties' => [
                            'author_name' => ['type' => 'string'],
                            'father_or_mother_name' => ['type' => 'string'],
                            'course_name' => ['type' => 'string'],
                            'year_of_learning' => ['type' => 'string'],
                            'qualification' => ['type' => 'string'],
                            'previous_course' => ['type' => 'string'],
                            'sibling_details' => ['type' => 'string'],
                            'age_or_birth_date' => ['type' => 'string'],
                            'location' => ['type' => 'string'],
                            'mentor_name' => ['type' => 'string'],
                            'mentor_occupation' => ['type' => 'string'],
                            'mentor_phone' => ['type' => 'string'],
                            'referrer_name' => ['type' => 'string'],
                            'referrer_phone' => ['type' => 'string'],
                            'referrer_occupation' => ['type' => 'string'],
                            'another_referrer_name' => ['type' => 'string'],
                            'another_referrer_phone' => ['type' => 'string'],
                            'another_referrer_occupation' => ['type' => 'string'],
                            'amount_paid' => ['type' => 'number'],
                            'transaction_id' => ['type' => 'string'],
                            'additional_message' => ['type' => 'string'],
                            'mobile' => ['type' => 'string', 'description' => '10-15 digits'],
                            'aadhar_card_front' => ['type' => 'string', 'format' => 'binary'],
                            'aadhar_card_back' => ['type' => 'string', 'format' => 'binary'],
                            'transaction_receipt_image' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Validation failed']],
    ],
    'get' => [
        'summary' => 'List form submissions by mobile',
        'operationId' => 'listYogaFormSubmissions',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10-15 digits)'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Missing or invalid mobile']],
    ],
];

$paths['/api/healing-form/submissions'] = [
    'post' => [
        'summary' => 'Submit healing registration form',
        'operationId' => 'submitHealingForm',
        'requestBody' => [
            'required' => true,
            'content' => [
                'multipart/form-data' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => [
                            'full_name',
                            'date_of_birth',
                            'mobile',
                            'aadhaar_number',
                            'amount_paid',
                            'declaration_accepted',
                            'aadhar_card_front',
                            'aadhar_card_back',
                            'transaction_receipt_image',
                        ],
                        'properties' => [
                            'full_name' => ['type' => 'string'],
                            'date_of_birth' => ['type' => 'string', 'description' => 'dd/mm/yyyy or ISO date'],
                            'time_of_birth' => ['type' => 'string'],
                            'place_of_birth' => ['type' => 'string'],
                            'current_location' => ['type' => 'string'],
                            'mobile' => ['type' => 'string', 'description' => '10-15 digits'],
                            'email' => ['type' => 'string'],
                            'address' => ['type' => 'string'],
                            'aadhaar_number' => ['type' => 'string', 'description' => '12-digit Aadhaar number'],
                            'issue_type' => ['type' => 'string'],
                            'issue_description' => ['type' => 'string'],
                            'declaration_accepted' => ['type' => 'boolean'],
                            'amount_paid' => ['type' => 'number'],
                            'transaction_id' => ['type' => 'string'],
                            'aadhar_card_front' => ['type' => 'string', 'format' => 'binary'],
                            'aadhar_card_back' => ['type' => 'string', 'format' => 'binary'],
                            'current_picture' => ['type' => 'string', 'format' => 'binary'],
                            'transaction_receipt_image' => ['type' => 'string', 'format' => 'binary'],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['201' => ['description' => 'Created'], '422' => ['description' => 'Validation failed']],
    ],
    'get' => [
        'summary' => 'List healing form submissions by mobile',
        'operationId' => 'listHealingFormSubmissions',
        'parameters' => [
            ['name' => 'mobile', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'], 'description' => 'Mobile number (10-15 digits)'],
        ],
        'responses' => ['200' => ['description' => 'OK'], '422' => ['description' => 'Missing or invalid mobile']],
    ],
];

$paths['/api/admin/dashboard'] = [
    'get' => [
        'summary' => 'Admin dashboard overview and payment summary',
        'operationId' => 'adminDashboard',
        'parameters' => [
            ['name' => 'start_date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date'], 'description' => 'Filter by date (Y-m-d)'],
            ['name' => 'end_date', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'format' => 'date'], 'description' => 'Filter by date (Y-m-d)'],
        ],
        'responses' => ['200' => ['description' => 'Overview counts + payment summary (total_donations, total_collected, pending_amount, verified_donations_count)']],
    ],
];

$paths['/api/admin/course-distribution'] = [
    'get' => [
        'summary' => 'Admin course distribution (enrollment count per class)',
        'operationId' => 'adminCourseDistribution',
        'responses' => ['200' => ['description' => 'List of classes with class_id, class_name, enrollment_count']],
    ],
];

$paths['/api/admin/recent-activity'] = [
    'get' => [
        'summary' => 'Admin recent activity (registrations and donations)',
        'operationId' => 'adminRecentActivity',
        'parameters' => [['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]]],
        'responses' => ['200' => ['description' => 'Combined list with type (donation|registration), name, detail, status, created_at']],
    ],
];

$paths['/api/admin/registrations'] = [
    'get' => [
        'summary' => 'Admin list all registrations (no mobile or Aadhaar required)',
        'description' => 'Returns all registrations directly. No mobile number or Aadhaar needed; optional search and filters only.',
        'operationId' => 'adminListRegistrations',
        'parameters' => [
            ['name' => 'search', 'in' => 'query', 'description' => 'Search by name, phone, course, location'],
            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['all', 'pending', 'partial', 'completed']]],
            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'start_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'end_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
        ],
        'responses' => ['200' => ['description' => 'data (list), total']],
    ],
];

$paths['/api/admin/donations'] = [
    'get' => [
        'summary' => 'Admin list all donations (no mobile or Aadhaar required)',
        'description' => 'Returns all donations directly. No mobile number or Aadhaar needed; optional search and filters only.',
        'operationId' => 'adminListDonations',
        'parameters' => [
            ['name' => 'search', 'in' => 'query', 'description' => 'Search by name, phone, transaction ID'],
            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['all', 'pending', 'verified', 'rejected']]],
            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'start_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'end_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
        ],
        'responses' => ['200' => ['description' => 'data (list), total']],
    ],
];

$paths['/api/admin/healing-submissions'] = [
    'get' => [
        'summary' => 'Admin list all healing form submissions',
        'description' => 'Returns all healing submissions directly. Optional search and filters supported.',
        'operationId' => 'adminListHealingSubmissions',
        'parameters' => [
            ['name' => 'search', 'in' => 'query', 'description' => 'Search by name, phone, Aadhaar, transaction ID, issue details'],
            ['name' => 'issue_type', 'in' => 'query', 'description' => 'Filter by selected issue type'],
            ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer']],
            ['name' => 'start_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'end_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
        ],
        'responses' => ['200' => ['description' => 'data (list), total']],
    ],
];

$paths['/api/admin/donations/summary'] = [
    'get' => [
        'summary' => 'Admin donations summary',
        'operationId' => 'adminDonationsSummary',
        'parameters' => [
            ['name' => 'start_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
            ['name' => 'end_date', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
        ],
        'responses' => ['200' => ['description' => 'total_amount, verified_count, pending_count, rejected_count']],
    ],
];

$paths['/api/admin/donations/status'] = [
    'put' => [
        'summary' => 'Admin update donation status',
        'operationId' => 'adminUpdateDonationStatus',
        'requestBody' => [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'required' => ['id', 'status'],
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Donation ID'],
                            'status' => ['type' => 'string', 'enum' => ['pending', 'verified', 'rejected']],
                        ],
                    ],
                ],
            ],
        ],
        'responses' => ['200' => ['description' => 'Updated donation'], '404' => ['description' => 'Donation not found'], '422' => ['description' => 'Validation failed']],
    ],
];

$paths['/api/openapi.json'] = [
    'get' => [
        'summary' => 'OpenAPI spec',
        'operationId' => 'openapiJson',
        'responses' => ['200' => ['description' => 'OpenAPI 3.0 JSON']],
    ],
];

return [
    'openapi' => '3.0.3',
    'info' => [
        'title' => 'Yoga Class & Donation API',
        'description' => 'APIs for class registration (with partial payment), payment summary, donations, and donation history.',
        'version' => '1.0.0',
    ],
    'servers' => [['url' => '/', 'description' => 'Current host']],
    'paths' => $paths,
];
