<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DonationService;
use App\Validation\Validator;

final class DonationController
{
    public function __construct(
        private readonly DonationService $service = new DonationService()
    ) {
    }

    public function store(Request $request): void
    {
        $validated = Validator::validate($request->body, [
            'name' => 'required',
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'amount_paid' => 'required|numeric',
        ]);
        $validated['transaction_id'] = isset($request->body['transaction_id']) ? trim((string) $request->body['transaction_id']) : null;
        if ($validated['transaction_id'] === '') {
            $validated['transaction_id'] = null;
        }

        $result = $this->service->store($validated, $request->files);

        Response::json([
            'success' => true,
            'message' => 'Donation saved successfully.',
            'data' => $result,
        ], 201);
    }

    public function listByMobile(Request $request): void
    {
        $mobile = trim((string) ($request->query['mobile'] ?? ''));
        $aadhaarNumber = trim((string) ($request->query['aadhaar_number'] ?? ''));
        $aadhaarNumber = $aadhaarNumber !== '' ? preg_replace('/\D/', '', $aadhaarNumber) : '';
        if ($mobile === '') {
            throw new HttpException('Query param "mobile" is required.', 422);
        }
        if ($aadhaarNumber === '') {
            throw new HttpException('Query param "aadhaar_number" is required.', 422);
        }
        if (strlen($aadhaarNumber) !== 12) {
            throw new HttpException('Query param "aadhaar_number" must be 12 digits.', 422);
        }

        Response::json([
            'success' => true,
            'data' => $this->service->listByMobileAndAadhaar($mobile, $aadhaarNumber),
        ]);
    }

    public function verifyDonationEligibility(Request $request): void
    {
        $validated = Validator::validate($request->query, [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
        ]);

        Response::json([
            'success' => true,
            'data' => $this->service->verifyDonationEligibility(
                (string) $validated['mobile'],
                (string) $validated['aadhaar_number']
            ),
        ]);
    }
}
