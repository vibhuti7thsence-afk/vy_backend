<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\ClassRegistrationService;
use App\Validation\Validator;

final class ClassRegistrationController
{
    public function __construct(
        private readonly ClassRegistrationService $service = new ClassRegistrationService()
    ) {
    }

    public function listClasses(): void
    {
        Response::json([
            'success' => true,
            'data' => $this->service->listClasses(),
        ]);
    }

    public function registerPayment(Request $request): void
    {
        $validated = Validator::validate($request->body, [
            'name' => 'required',
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'class_id' => 'required|numeric',
            'amount_paid' => 'required|numeric',
            'email' => 'email_optional',
        ]);

        $validated['preferred_time'] = isset($request->body['preferred_time']) ? trim((string) $request->body['preferred_time']) : null;
        $validated['location'] = isset($request->body['location']) ? trim((string) $request->body['location']) : null;
        $validated['siblings_name'] = isset($request->body['siblings_name']) ? trim((string) $request->body['siblings_name']) : null;
        $validated['transaction_msg'] = isset($request->body['transaction_msg']) ? trim((string) $request->body['transaction_msg']) : null;
        $validated['transaction_id'] = isset($request->body['transaction_id']) ? trim((string) $request->body['transaction_id']) : null;
        $validated['message'] = isset($request->body['message']) ? trim((string) $request->body['message']) : null;
        if ($validated['message'] === '') {
            $validated['message'] = null;
        }

        $files = $request->files;
        $aadhaarFront = $files['aadhaar_doc'] ?? null;
        $aadhaarBack = $files['aadhaar_doc_back'] ?? null;
        $transactionReceipt = $files['transaction_receipt_image'] ?? null;
        if (!$aadhaarFront || $aadhaarFront['error'] === UPLOAD_ERR_NO_FILE || $aadhaarFront['tmp_name'] === '') {
            throw new HttpException('Aadhaar document (front) is required.', 422);
        }
        if (!$aadhaarBack || $aadhaarBack['error'] === UPLOAD_ERR_NO_FILE || $aadhaarBack['tmp_name'] === '') {
            throw new HttpException('Aadhaar document (back) is required.', 422);
        }
        if (!$transactionReceipt || $transactionReceipt['error'] === UPLOAD_ERR_NO_FILE || $transactionReceipt['tmp_name'] === '') {
            throw new HttpException('Transaction receipt image is required.', 422);
        }

        $result = $this->service->registerPayment($validated, $files);

        Response::json([
            'success' => true,
            'message' => 'Registration and payment recorded successfully.',
            'data' => $result,
        ], 201);
    }

    public function paymentSummary(Request $request): void
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
            'data' => $this->service->summaryByMobileAndAadhaar($mobile, $aadhaarNumber),
        ]);
    }

    public function verifyRegistration(Request $request): void
    {
        $validated = Validator::validate($request->query, [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'class_id' => 'required|numeric',
        ]);
        $classId = (int) $validated['class_id'];
        if ($classId < 1) {
            throw new HttpException('Query param "class_id" must be a positive integer.', 422);
        }

        Response::json([
            'success' => true,
            'data' => $this->service->verifyRegistrationForClass(
                (string) $validated['mobile'],
                (string) $validated['aadhaar_number'],
                $classId
            ),
        ]);
    }

    public function lookupUser(Request $request): void
    {
        $validated = Validator::validate($request->body ?? [], [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
        ]);

        Response::json([
            'success' => true,
            'data' => $this->service->lookupRegisteredUser(
                (string) $validated['mobile'],
                (string) $validated['aadhaar_number']
            ),
        ]);
    }

    /** Admin: set negotiated/agreed fee for a specific user (aadhaar) and class. */
    public function putAgreedFee(Request $request): void
    {
        $validated = Validator::validate($request->body ?? [], [
            'aadhaar_number' => 'required|aadhaar',
            'class_id' => 'required|numeric',
            'agreed_fee' => 'required|numeric',
        ]);
        $agreedFee = (float) $validated['agreed_fee'];
        if ($agreedFee <= 0) {
            throw new HttpException('Agreed fee must be greater than zero.', 422);
        }
        $result = $this->service->setAgreedFee(
            $validated['aadhaar_number'],
            (int) $validated['class_id'],
            $agreedFee
        );
        Response::json([
            'success' => true,
            'message' => 'Agreed fee updated.',
            'data' => $result,
        ]);
    }
}
