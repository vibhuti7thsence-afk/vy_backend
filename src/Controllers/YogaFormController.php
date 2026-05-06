<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\YogaFormService;
use App\Validation\Validator;

final class YogaFormController
{
    public function __construct(
        private readonly YogaFormService $service = new YogaFormService()
    ) {
    }

    public function submit(Request $request): void
    {
        $validated = Validator::validate($request->body, [
            'author_name' => 'required',
            'father_or_mother_name' => 'required',
            'course_name' => 'required',
            'qualification' => 'required',
            'age_or_birth_date' => 'required',
            'location' => 'required',
            'amount_paid' => 'required|numeric',
            'mobile' => 'required|mobile',
        ], true);

        $optionalFields = [
            'year_of_learning',
            'previous_course',
            'sibling_details',
            'mentor_name',
            'mentor_occupation',
            'mentor_phone',
            'referrer_name',
            'referrer_phone',
            'referrer_occupation',
            'another_referrer_name',
            'another_referrer_phone',
            'another_referrer_occupation',
            'transaction_id',
            'additional_message',
        ];
        foreach ($optionalFields as $field) {
            $value = isset($request->body[$field]) ? trim((string) $request->body[$field]) : null;
            $validated[$field] = $value === '' ? null : $value;
        }

        $files = $request->files;
        foreach (['aadhar_card_front', 'aadhar_card_back', 'transaction_receipt_image'] as $fileKey) {
            $file = $files[$fileKey] ?? null;
            if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
                throw new HttpException('Missing required file: ' . $fileKey, 422);
            }
        }

        $data = $this->service->submit($validated, $files);

        Response::json([
            'success' => true,
            'message' => 'Form submitted successfully.',
            'data' => $data,
        ], 201);
    }

    public function listByMobile(Request $request): void
    {
        $mobile = trim((string) ($request->query['mobile'] ?? ''));
        if ($mobile === '') {
            throw new HttpException('Query param "mobile" is required.', 422);
        }
        if (!preg_match('/^[0-9]{10,15}$/', $mobile)) {
            throw new HttpException('Query param "mobile" must be 10-15 digits.', 422);
        }

        Response::json([
            'success' => true,
            'data' => $this->service->listByMobile($mobile),
        ]);
    }
}
