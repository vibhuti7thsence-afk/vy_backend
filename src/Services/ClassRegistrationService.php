<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ClassPaymentRepository;
use App\Repositories\ClassRepository;
use App\Repositories\ClassUserFeeRepository;

final class ClassRegistrationService
{
    private readonly RegistrationFileUploadService $fileUpload;

    public function __construct(
        private readonly ClassRepository $classRepository = new ClassRepository(),
        private readonly ClassPaymentRepository $paymentRepository = new ClassPaymentRepository(),
        private readonly ClassUserFeeRepository $userFeeRepository = new ClassUserFeeRepository(),
        ?RegistrationFileUploadService $fileUpload = null
    ) {
        $this->fileUpload = $fileUpload ?? RegistrationFileUploadService::fromConfig();
    }

    public function listClasses(): array
    {
        return $this->classRepository->allActive();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    public function registerPayment(array $payload, array $files = []): array
    {
        $classId = (int) $payload['class_id'];
        $amount = (float) $payload['amount_paid'];

        if ($amount <= 0) {
            throw new HttpException('Amount must be greater than zero.', 422);
        }

        $class = $this->classRepository->findById($classId);
        if ($class === null) {
            throw new HttpException('Selected class is invalid.', 404);
        }

        $aadhaarNumber = (string) ($payload['aadhaar_number'] ?? '');
        if ($aadhaarNumber === '') {
            throw new HttpException('Aadhaar number is required.', 422);
        }
        $this->assertNoCrossIdentityConflictForClass($classId, (string) $payload['mobile'], $aadhaarNumber);
        $this->userFeeRepository->ensureAgreedFee($aadhaarNumber, $classId, (float) $class['total_fee']);
        $agreedFee = $this->userFeeRepository->getAgreedFee($aadhaarNumber, $classId)
            ?? (float) $class['total_fee'];
        $alreadyPaid = $this->paymentRepository->totalPaidByAadhaarAndClass($aadhaarNumber, $classId);
        $remainingBefore = max($agreedFee - $alreadyPaid, 0);

        if ($remainingBefore <= 0) {
            throw new HttpException('Class fee is already fully paid for this Aadhaar number.', 409);
        }

        if ($amount > $remainingBefore) {
            throw new HttpException('Amount exceeds remaining fee. Remaining: ' . $remainingBefore, 422);
        }

        $remainingAfter = $remainingBefore - $amount;
        $status = $remainingAfter > 0 ? 'partial' : 'paid';

        $docPaths = ['aadhaar_doc_path' => null, 'aadhaar_doc_back_path' => null, 'transaction_receipt_path' => null];
        if (isset($files['aadhaar_doc'], $files['aadhaar_doc_back'], $files['transaction_receipt_image'])) {
            $docPaths = $this->fileUpload->processRegistrationDocs($files);
        }

        $paymentId = $this->paymentRepository->create([
            'mobile' => $payload['mobile'],
            'aadhaar_number' => $aadhaarNumber,
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'class_id' => $classId,
            'preferred_time' => $payload['preferred_time'] ?? null,
            'location' => $payload['location'] ?? null,
            'siblings_name' => $payload['siblings_name'] ?? null,
            'message' => $payload['message'] ?? null,
            'amount_paid' => $amount,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'transaction_msg' => $payload['transaction_msg'] ?? null,
            'aadhaar_doc_path' => $docPaths['aadhaar_doc_path'],
            'aadhaar_doc_back_path' => $docPaths['aadhaar_doc_back_path'],
            'transaction_receipt_path' => $docPaths['transaction_receipt_path'],
            'payment_status' => $status,
        ]);

        return [
            'payment_id' => $paymentId,
            'class_id' => $classId,
            'class_name' => $class['class_name'],
            'agreed_fee' => $agreedFee,
            'amount_paid_now' => $amount,
            'paid_till_now' => $alreadyPaid + $amount,
            'remaining_amount' => $remainingAfter,
            'payment_status' => $status,
        ];
    }

    public function mobileSummary(string $mobile): array
    {
        $rows = $this->paymentRepository->summaryByMobile($mobile);
        foreach ($rows as &$row) {
            $row['payment_status'] = ((float) $row['remaining_amount'] <= 0) ? 'paid' : 'partial_or_unpaid';
        }

        return $rows;
    }

    public function summaryByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        $rows = $this->paymentRepository->summaryByMobileAndAadhaar($mobile, $aadhaarNumber);
        foreach ($rows as &$row) {
            $row['payment_status'] = ((float) $row['remaining_amount'] <= 0) ? 'paid' : 'partial_or_unpaid';
            $row['pending_amount'] = max((float) $row['remaining_amount'], 0);
        }

        return $rows;
    }

    /** Admin: set negotiated/agreed fee for a specific user (aadhaar) and class. */
    public function setAgreedFee(string $aadhaarNumber, int $classId, float $agreedFee): array
    {
        if ($agreedFee <= 0) {
            throw new HttpException('Agreed fee must be greater than zero.', 422);
        }
        $class = $this->classRepository->findById($classId);
        if ($class === null) {
            throw new HttpException('Class not found.', 404);
        }
        $this->userFeeRepository->setAgreedFee($aadhaarNumber, $classId, $agreedFee);
        $alreadyPaid = $this->paymentRepository->totalPaidByAadhaarAndClass($aadhaarNumber, $classId);
        $remaining = max($agreedFee - $alreadyPaid, 0);

        return [
            'aadhaar_number' => $aadhaarNumber,
            'class_id' => $classId,
            'class_name' => $class['class_name'],
            'agreed_fee' => $agreedFee,
            'paid_so_far' => $alreadyPaid,
            'remaining_amount' => $remaining,
        ];
    }

    /**
     * Pre-check before class registration payment: conflicts when mobile or Aadhaar is already tied to another identity for this class.
     *
     * @return array{
     *   status: string,
     *   can_submit_registration_payment: bool,
     *   message: ?string,
     *   agreed_fee?: float,
     *   paid_so_far?: float,
     *   remaining_amount?: float
     * }
     */
    public function verifyRegistrationForClass(string $mobile, string $aadhaarNumber, int $classId): array
    {
        $class = $this->classRepository->findById($classId);
        if ($class === null) {
            throw new HttpException('Selected class is invalid.', 404);
        }

        $pairs = $this->paymentRepository->distinctIdentitiesForClassByMobileOrAadhaar($classId, $mobile, $aadhaarNumber);
        if ($pairs === []) {
            return [
                'status' => 'new',
                'can_submit_registration_payment' => true,
                'message' => null,
            ];
        }

        $samePair = false;
        foreach ($pairs as $p) {
            if ($p['mobile'] === $mobile && $p['aadhaar_number'] === $aadhaarNumber) {
                $samePair = true;
                break;
            }
        }

        if (!$samePair) {
            foreach ($pairs as $p) {
                if ($p['mobile'] === $mobile && $p['aadhaar_number'] !== $aadhaarNumber) {
                    return [
                        'status' => 'mobile_already_registered',
                        'can_submit_registration_payment' => false,
                        'message' => 'This mobile number is already registered for this class with a different Aadhaar number.',
                    ];
                }
            }
            foreach ($pairs as $p) {
                if ($p['aadhaar_number'] === $aadhaarNumber && $p['mobile'] !== $mobile) {
                    return [
                        'status' => 'aadhaar_already_registered',
                        'can_submit_registration_payment' => false,
                        'message' => 'This Aadhaar number is already registered for this class with a different mobile number.',
                    ];
                }
            }
        }

        $this->userFeeRepository->ensureAgreedFee($aadhaarNumber, $classId, (float) $class['total_fee']);
        $agreedFee = $this->userFeeRepository->getAgreedFee($aadhaarNumber, $classId)
            ?? (float) $class['total_fee'];
        $alreadyPaid = $this->paymentRepository->totalPaidByAadhaarAndClass($aadhaarNumber, $classId);
        $remaining = max($agreedFee - $alreadyPaid, 0);

        if ($remaining <= 0) {
            return [
                'status' => 'registration_fee_complete',
                'can_submit_registration_payment' => false,
                'message' => 'Already registered for this class; the fee is fully paid.',
                'agreed_fee' => $agreedFee,
                'paid_so_far' => $alreadyPaid,
                'remaining_amount' => 0.0,
            ];
        }

        return [
            'status' => 'already_registered',
            'can_submit_registration_payment' => true,
            'message' => 'Already registered for this class. You can submit additional payments toward the remaining balance.',
            'agreed_fee' => $agreedFee,
            'paid_so_far' => $alreadyPaid,
            'remaining_amount' => $remaining,
        ];
    }

    /**
     * @return array{found: bool, name: ?string, message: string}
     */
    public function lookupRegisteredUser(string $mobile, string $aadhaarNumber): array
    {
        $name = $this->paymentRepository->findLatestRegistrantNameByMobileAndAadhaar($mobile, $aadhaarNumber);
        if ($name === null) {
            return [
                'found' => false,
                'name' => null,
                'message' => 'User not found.',
            ];
        }

        return [
            'found' => true,
            'name' => $name,
            'message' => 'User found.',
        ];
    }

    private function assertNoCrossIdentityConflictForClass(int $classId, string $mobile, string $aadhaarNumber): void
    {
        $pairs = $this->paymentRepository->distinctIdentitiesForClassByMobileOrAadhaar($classId, $mobile, $aadhaarNumber);
        foreach ($pairs as $p) {
            if ($p['mobile'] === $mobile && $p['aadhaar_number'] === $aadhaarNumber) {
                return;
            }
        }
        foreach ($pairs as $p) {
            if ($p['mobile'] === $mobile && $p['aadhaar_number'] !== $aadhaarNumber) {
                throw new HttpException('This mobile number is already registered for this class with a different Aadhaar number.', 409);
            }
        }
        foreach ($pairs as $p) {
            if ($p['aadhaar_number'] === $aadhaarNumber && $p['mobile'] !== $mobile) {
                throw new HttpException('This Aadhaar number is already registered for this class with a different mobile number.', 409);
            }
        }
    }
}
