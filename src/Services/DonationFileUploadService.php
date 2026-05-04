<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use RuntimeException;

final class DonationFileUploadService
{
    /**
     * Each storage key accepts the first present file from its form field aliases
     * (verify-your-donation form names first, then legacy keys).
     *
     * @var array<string, list<string>>
     */
    private const UPLOAD_SLOTS = [
        'aadhaar_front_path' => ['aadhaar_front', 'aadhaar_front_doc'],
        'aadhaar_back_path' => ['aadhaar_back', 'aadhaar_back_doc'],
        'transaction_rep_path' => ['receipt_photo', 'transaction_rep_doc'],
        'individual_photo_path' => ['individual_photo'],
    ];

    public function __construct(
        private readonly string $donationsDir,
        private readonly int $maxSizeBytes,
        private readonly array $allowedMimes
    ) {
    }

    public static function fromConfig(): self
    {
        $config = require __DIR__ . '/../../config/config.php';
        $uploadConfig = $config['uploads'] ?? [];
        return new self(
            (string) ($uploadConfig['donations_dir'] ?? (__DIR__ . '/../../storage/uploads/donations')),
            (int) ($uploadConfig['max_size_bytes'] ?? 5 * 1024 * 1024),
            (array) ($uploadConfig['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
        );
    }

    /**
     * Validate and store donation docs. Returns array of path keys => relative path.
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     * @return array{aadhaar_front_path: string|null, aadhaar_back_path: string|null, transaction_rep_path: string|null, individual_photo_path: string|null}
     */
    public function processDonationDocs(array $files): array
    {
        $baseDir = $this->donationsDir;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            throw new RuntimeException('Could not create uploads directory.');
        }

        $result = [
            'aadhaar_front_path' => null,
            'aadhaar_back_path' => null,
            'transaction_rep_path' => null,
            'individual_photo_path' => null,
        ];

        foreach (self::UPLOAD_SLOTS as $pathKey => $formKeys) {
            $file = null;
            $usedFormKey = '';
            foreach ($formKeys as $formKey) {
                $candidate = $files[$formKey] ?? null;
                if ($candidate !== null && $candidate['error'] !== UPLOAD_ERR_NO_FILE && $candidate['tmp_name'] !== '') {
                    $file = $candidate;
                    $usedFormKey = $formKey;
                    break;
                }
            }
            if ($file === null) {
                throw new HttpException('Missing required document (one of: ' . implode(', ', $formKeys) . ')', 422);
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new HttpException('Upload failed for ' . $usedFormKey . ': ' . $this->uploadErrorMessage($file['error']), 422);
            }
            if ($file['size'] > $this->maxSizeBytes) {
                throw new HttpException('File too large for ' . $usedFormKey . '. Max ' . round($this->maxSizeBytes / 1024 / 1024, 1) . ' MB.', 422);
            }

            $mime = $this->detectMime($file['tmp_name'], $file['type']);
            if (!in_array($mime, $this->allowedMimes, true)) {
                throw new HttpException('Invalid file type for ' . $usedFormKey . '. Allowed: JPEG, PNG, WebP, PDF.', 422);
            }

            $ext = $this->mimeToExt($mime);
            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $safeName = substr($safeName, 0, 100) ?: 'file';
            $filename = date('Y-m-d_His') . '_' . $usedFormKey . '_' . $safeName;
            if (strlen(pathinfo($filename, PATHINFO_EXTENSION)) < 2) {
                $filename .= $ext;
            }
            $destPath = $baseDir . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new RuntimeException('Failed to save uploaded file: ' . $usedFormKey);
            }

            $result[$pathKey] = 'donations/' . $filename;
        }

        return $result;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error',
        };
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
