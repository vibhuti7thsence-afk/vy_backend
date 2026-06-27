<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\HealingFormRepository;
use RuntimeException;

final class HealingFormService
{
    private readonly string $baseDir;
    private readonly int $maxSizeBytes;
    /** @var array<int, string> */
    private readonly array $allowedMimes;
    private readonly ?S3StorageService $s3;

    public function __construct(
        private readonly HealingFormRepository $repository = new HealingFormRepository()
    ) {
        $config = require __DIR__ . '/../../config/config.php';
        $uploadConfig = $config['uploads'] ?? [];
        $this->baseDir = (string) ($uploadConfig['registrations_dir'] ?? (__DIR__ . '/../../storage/uploads/registrations'));
        $this->maxSizeBytes = (int) ($uploadConfig['max_size_bytes'] ?? 5 * 1024 * 1024);
        $this->allowedMimes = (array) ($uploadConfig['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']);
        $this->s3 = S3StorageService::fromConfig();
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     * @return array<string, mixed>
     */
    public function submit(array $payload, array $files): array
    {
        $amountPaid = (float) ($payload['amount_paid'] ?? 0);
        if ($amountPaid <= 0) {
            throw new HttpException('Amount paid must be greater than zero.', 422);
        }

        $aadhaarFrontPath = $this->storeFileFromAliases($files, ['aadhaar_front', 'aadhar_card_front'], false);
        $aadhaarBackPath = $this->storeFileFromAliases($files, ['aadhaar_back', 'aadhar_card_back'], false);
        $receiptPath = $this->storeFileFromAliases($files, ['fee_receipt', 'transaction_receipt_image'], true);
        $currentPicturePath = $this->storeFileFromAliases($files, ['recent_picture', 'current_picture'], false);

        $id = $this->repository->create([
            'full_name' => $payload['full_name'],
            'date_of_birth' => $payload['date_of_birth'],
            'time_of_birth' => $payload['time_of_birth'] ?? null,
            'place_of_birth' => $payload['place_of_birth'] ?? null,
            'current_location' => $payload['current_location'] ?? null,
            'mobile' => $payload['mobile'],
            'email' => $payload['email'] ?? null,
            'address' => $payload['address'] ?? null,
            'aadhaar_number' => $payload['aadhaar_number'],
            'aadhaar_front_path' => $aadhaarFrontPath,
            'aadhaar_back_path' => $aadhaarBackPath,
            'star_name' => $payload['star_name'] ?? null,
            'issue_type' => $payload['issue_type'] ?? null,
            'issue_description' => $payload['issue_description'] ?? null,
            'current_picture_path' => $currentPicturePath,
            'declaration_accepted' => $payload['declaration_accepted'],
            'amount_paid' => $amountPaid,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'transaction_receipt_path' => $receiptPath,
        ]);

        return [
            'id' => $id,
            'full_name' => $payload['full_name'],
            'your_name' => $payload['full_name'],
            'mobile' => $payload['mobile'],
            'phone_number' => $payload['mobile'],
            'aadhaar_number' => $payload['aadhaar_number'],
            'amount_paid' => $amountPaid,
            'fee_amount_paid' => $amountPaid,
        ];
    }

    public function listByMobile(string $mobile): array
    {
        return $this->repository->listByMobile($mobile);
    }

    public function listByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        return $this->repository->listByMobileAndAadhaar($mobile, $aadhaarNumber);
    }

    /**
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    private function storeFileFromAliases(array $files, array $keys, bool $required): ?string
    {
        $file = null;
        $usedKey = null;
        foreach ($keys as $key) {
            $candidate = $files[$key] ?? null;
            if ($candidate !== null && $candidate['error'] !== UPLOAD_ERR_NO_FILE && $candidate['tmp_name'] !== '') {
                $file = $candidate;
                $usedKey = $key;
                break;
            }
        }

        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE || $file['tmp_name'] === '') {
            if ($required) {
                throw new HttpException('Missing required file (one of: ' . implode(', ', $keys) . ')', 422);
            }
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new HttpException('Upload failed for ' . ($usedKey ?? $keys[0]), 422);
        }
        if ($file['size'] > $this->maxSizeBytes) {
            throw new HttpException('File too large for ' . ($usedKey ?? $keys[0]), 422);
        }

        $mime = $this->detectMime($file['tmp_name'], $file['type']);
        if (!in_array($mime, $this->allowedMimes, true)) {
            throw new HttpException('Invalid file type for ' . ($usedKey ?? $keys[0]) . '. Allowed: JPEG, PNG, WebP, PDF.', 422);
        }

        if (!is_dir($this->baseDir) && !mkdir($concurrentDirectory = $this->baseDir, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Could not create uploads directory.');
        }

        $ext = $this->mimeToExt($mime);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $safeName = substr($safeName, 0, 100) ?: 'file';
        $filename = date('Y-m-d_His') . '_' . ($usedKey ?? $keys[0]) . '_' . $safeName;
        if (strlen(pathinfo($filename, PATHINFO_EXTENSION)) < 2) {
            $filename .= $ext;
        }
        $destPath = $this->baseDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Failed to save uploaded file: ' . ($usedKey ?? $keys[0]));
        }

        if ($this->s3 !== null) {
            $url = $this->s3->upload($destPath, 'registrations/' . $filename, $mime);
            @unlink($destPath);
            return $url;
        }

        return 'registrations/' . $filename;
    }

    private function detectMime(string $path, string $reportedType): string
    {
        if (is_file($path) && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }
        return $reportedType;
    }

    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
            default => '.bin',
        };
    }
}
