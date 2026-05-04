<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ClassPaymentRepository;
use App\Repositories\DonationRepository;

final class DonationService
{
    private readonly DonationFileUploadService $fileUpload;

    public function __construct(
        private readonly DonationRepository $donationRepository = new DonationRepository(),
        private readonly ClassPaymentRepository $classPaymentRepository = new ClassPaymentRepository(),
        ?DonationFileUploadService $fileUpload = null
    ) {
        $this->fileUpload = $fileUpload ?? DonationFileUploadService::fromConfig();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    public function store(array $payload, array $files = []): array
    {
        $amount = (float) $payload['amount_paid'];
        if ($amount <= 0) {
            throw new HttpException('Donation amount must be greater than zero.', 422);
        }

        if (!$this->classPaymentRepository->hasRegistrationWithMobileAndAadhaar(
            (string) $payload['mobile'],
            (string) $payload['aadhaar_number']
        )) {
            throw new HttpException('Please complete class registration with this mobile and Aadhaar before donating.', 422);
        }

        $docPaths = $this->fileUpload->processDonationDocs($files);

        $id = $this->donationRepository->create([
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'place' => $payload['place'] ?? null,
            'donation_category' => $payload['donation_category'] ?? null,
            'mobile' => $payload['mobile'],
            'aadhaar_number' => (string) $payload['aadhaar_number'],
            'amount_paid' => $amount,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'aadhaar_front_path' => $docPaths['aadhaar_front_path'],
            'aadhaar_back_path' => $docPaths['aadhaar_back_path'],
            'transaction_rep_path' => $docPaths['transaction_rep_path'],
            'individual_photo_path' => $docPaths['individual_photo_path'],
        ]);

        return [
            'donation_id' => $id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'full_name' => $payload['name'],
            'phone_number' => $payload['mobile'],
            'aadhaar_front_path' => $docPaths['aadhaar_front_path'],
            'aadhaar_back_path' => $docPaths['aadhaar_back_path'],
            'receipt_photo_path' => $docPaths['transaction_rep_path'],
            'transaction_rep_path' => $docPaths['transaction_rep_path'],
            'individual_photo_path' => $docPaths['individual_photo_path'],
        ];
    }

    public function listByMobile(string $mobile): array
    {
        return $this->donationRepository->listByMobile($mobile);
    }

    public function listByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        return $this->donationRepository->listByMobileAndAadhaar($mobile, $aadhaarNumber);
    }

    /**
     * @return array{can_donate: bool, message: ?string}
     */
    public function verifyDonationEligibility(string $mobile, string $aadhaarNumber): array
    {
        $ok = $this->classPaymentRepository->hasRegistrationWithMobileAndAadhaar($mobile, $aadhaarNumber);
        return [
            'can_donate' => $ok,
            'message' => $ok ? null : 'Please complete class registration with this mobile and Aadhaar before donating.',
        ];
    }
}
