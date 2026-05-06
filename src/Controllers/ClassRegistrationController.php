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

    /**
     * Sacred program registration form field names plus legacy keys.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function mergeRegistrationBody(array $body): array
    {
        $why = trim((string) ($body['why_attend_course'] ?? $body['why_attend'] ?? $body['reason_to_attend'] ?? ''));
        if ($why === '') {
            $why = trim((string) ($body['message'] ?? ''));
        }

        $additional = trim((string) ($body['additional_message'] ?? $body['additional_notes'] ?? ''));
        if ($additional === '') {
            $legacyMsg = trim((string) ($body['message'] ?? ''));
            if ($legacyMsg !== '' && $legacyMsg !== $why) {
                $additional = $legacyMsg;
            }
        }

        $amountPaid = $body['amount_paid'] ?? null;
        if ($amountPaid === null || $amountPaid === '') {
            $amountPaid = $body['fee_amount_paid'] ?? $body['amount'] ?? null;
        }

        $classId = $body['class_id'] ?? $body['selected_class_id'] ?? $body['course_id'] ?? null;

        return array_merge($body, [
            'name' => trim((string) ($body['your_full_name'] ?? $body['full_name'] ?? $body['name'] ?? '')),
            'mobile' => trim((string) ($body['phone_number'] ?? $body['phone'] ?? $body['mobile'] ?? '')),
            'class_id' => $classId,
            'amount_paid' => $amountPaid,
            'age_or_birth' => trim((string) ($body['age_or_birth'] ?? $body['age_or_date_of_birth'] ?? '')),
            'qualification' => trim((string) ($body['qualification'] ?? '')),
            'location' => trim((string) ($body['location'] ?? $body['present_address'] ?? '')),
            'father_name' => trim((string) ($body['father_name'] ?? $body['fathers_name'] ?? '')),
            'father_phone' => trim((string) ($body['father_phone'] ?? $body['fathers_phone'] ?? '')),
            'mother_name' => trim((string) ($body['mother_name'] ?? $body['mothers_name'] ?? '')),
            'mother_phone' => trim((string) ($body['mother_phone'] ?? $body['mothers_phone'] ?? '')),
            'why_attend_course' => $why,
            'additional_message' => $additional === '' ? null : $additional,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{mobile: string, aadhaar_number: string}
     */
    private static function resolvePhoneAadhaarQuery(array $query): array
    {
        $mobile = trim((string) ($query['mobile'] ?? $query['phone_number'] ?? $query['phone'] ?? ''));
        $aadhaarRaw = trim((string) ($query['aadhaar_number'] ?? $query['aadhaar'] ?? ''));
        $merged = array_merge($query, [
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarRaw !== '' ? preg_replace('/\D/', '', $aadhaarRaw) : '',
        ]);

        return Validator::validate($merged, [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function paymentSummaryWithAliases(array $rows): array
    {
        return array_map(static function (array $row): array {
            return array_merge($row, [
                'phone_number' => (string) ($row['mobile'] ?? ''),
                'aadhaar_front_path' => $row['aadhaar_doc_path'] ?? null,
                'aadhaar_back_path' => $row['aadhaar_doc_back_path'] ?? null,
                'fee_receipt_path' => $row['transaction_receipt_path'] ?? null,
            ]);
        }, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function paymentTransactionsWithAliases(array $rows): array
    {
        return array_map(static function (array $row): array {
            $amount = isset($row['amount_paid']) ? (float) $row['amount_paid'] : null;

            return array_merge($row, [
                'phone_number' => (string) ($row['mobile'] ?? ''),
                'your_full_name' => (string) ($row['name'] ?? ''),
                'present_address' => $row['location'] ?? null,
                'fee_amount_paid' => $amount,
                'transaction_reference_id' => $row['transaction_id'] ?? null,
                'fee_receipt_path' => $row['transaction_receipt_path'] ?? null,
                'aadhaar_front_path' => $row['aadhaar_doc_path'] ?? null,
                'aadhaar_back_path' => $row['aadhaar_doc_back_path'] ?? null,
            ]);
        }, $rows);
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
        $mergedBody = self::mergeRegistrationBody($request->body);
        $validated = Validator::validate($mergedBody, [
            'name' => 'required',
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'class_id' => 'required|numeric',
            'amount_paid' => 'required|numeric',
            'email' => 'email_optional',
            'age_or_birth' => 'required',
            'qualification' => 'required',
            'location' => 'required',
            'father_name' => 'required',
            'father_phone' => 'required|mobile',
            'mother_name' => 'required',
            'mother_phone' => 'required|mobile',
            'why_attend_course' => 'required',
        ], true);

        $validated['additional_message'] = $mergedBody['additional_message'] ?? null;

        $validated['preferred_time'] = isset($mergedBody['preferred_time']) ? trim((string) $mergedBody['preferred_time']) : null;
        if ($validated['preferred_time'] === '') {
            $validated['preferred_time'] = null;
        }

        $validated['siblings_name'] = isset($mergedBody['siblings_name']) ? trim((string) $mergedBody['siblings_name']) : null;
        if ($validated['siblings_name'] === '') {
            $validated['siblings_name'] = null;
        }

        $validated['transaction_msg'] = isset($mergedBody['transaction_msg']) ? trim((string) $mergedBody['transaction_msg']) : null;
        if ($validated['transaction_msg'] === '') {
            $validated['transaction_msg'] = null;
        }

        $validated['transaction_id'] = isset($mergedBody['transaction_id']) ? trim((string) $mergedBody['transaction_id']) : null;
        if ($validated['transaction_id'] === '') {
            $validated['transaction_id'] = null;
        }

        $validated['message'] = null;

        $emailVal = $validated['email'] ?? null;
        $validated['email'] = ($emailVal === null || $emailVal === '') ? null : (string) $emailVal;

        $result = $this->service->registerPayment($validated, $request->files);

        Response::json([
            'success' => true,
            'message' => 'Registration and payment recorded successfully.',
            'data' => $result,
        ], 201);
    }

    public function paymentSummary(Request $request): void
    {
        $validated = self::resolvePhoneAadhaarQuery($request->query);
        $rows = $this->service->summaryByMobileAndAadhaar(
            (string) $validated['mobile'],
            (string) $validated['aadhaar_number']
        );

        Response::json([
            'success' => true,
            'data' => self::paymentSummaryWithAliases($rows),
        ]);
    }

    /** GET /api/classes/payment-transactions — each payment row for this phone + Aadhaar, newest first. */
    public function paymentTransactions(Request $request): void
    {
        $validated = self::resolvePhoneAadhaarQuery($request->query);
        $classIdRaw = $request->query['class_id'] ?? $request->query['selected_class_id'] ?? $request->query['course_id'] ?? null;

        $classId = null;
        if ($classIdRaw !== null && trim((string) $classIdRaw) !== '') {
            $classValidated = Validator::validate(
                ['class_id' => $classIdRaw],
                ['class_id' => 'required|numeric']
            );
            $classId = (int) $classValidated['class_id'];
            if ($classId < 1) {
                throw new HttpException('Query param "class_id" must be a positive integer.', 422);
            }
        }

        $rows = $this->service->listPaymentTransactionsByMobileAndAadhaar(
            (string) $validated['mobile'],
            (string) $validated['aadhaar_number'],
            $classId
        );

        Response::json([
            'success' => true,
            'data' => self::paymentTransactionsWithAliases($rows),
        ]);
    }

    public function verifyRegistration(Request $request): void
    {
        $identifiers = self::resolvePhoneAadhaarQuery($request->query);
        $classIdRaw = $request->query['class_id'] ?? $request->query['selected_class_id'] ?? $request->query['course_id'] ?? null;
        $merged = array_merge($request->query, $identifiers, ['class_id' => $classIdRaw]);

        $validated = Validator::validate($merged, [
            'class_id' => 'required|numeric',
        ]);
        $classId = (int) $validated['class_id'];
        if ($classId < 1) {
            throw new HttpException('Query param "class_id" must be a positive integer.', 422);
        }

        Response::json([
            'success' => true,
            'data' => $this->service->verifyRegistrationForClass(
                (string) $identifiers['mobile'],
                (string) $identifiers['aadhaar_number'],
                $classId
            ),
        ]);
    }

    public function lookupUser(Request $request): void
    {
        $body = $request->body ?? [];
        $mobile = trim((string) ($body['mobile'] ?? $body['phone_number'] ?? $body['phone'] ?? ''));
        $aadhaarRaw = trim((string) ($body['aadhaar_number'] ?? $body['aadhaar'] ?? ''));
        $merged = array_merge($body, [
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarRaw !== '' ? preg_replace('/\D/', '', $aadhaarRaw) : '',
        ]);

        $validated = Validator::validate($merged, [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
        ]);

        $data = $this->service->lookupRegisteredUser(
            (string) $validated['mobile'],
            (string) $validated['aadhaar_number']
        );

        if ($data['found'] === true && isset($data['name'])) {
            $data['your_full_name'] = $data['name'];
            $data['phone_number'] = $validated['mobile'];
        }

        Response::json([
            'success' => true,
            'data' => $data,
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
