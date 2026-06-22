<?php

declare(strict_types=1);

namespace App\Controllers;

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

    /**
     * Accepts verify-your-donation form field names (full_name, phone_number, amount, …)
     * and legacy keys (name, mobile, amount_paid, …).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function mergeDonationFormBody(array $body): array
    {
        $name = trim((string) ($body['full_name'] ?? $body['name'] ?? ''));
        $mobile = trim((string) ($body['phone_number'] ?? $body['mobile'] ?? ''));
        $amountPaid = $body['amount_paid'] ?? null;
        if ($amountPaid === null || $amountPaid === '') {
            $amountPaid = $body['amount'] ?? null;
        }

        return array_merge($body, [
            'name' => $name,
            'mobile' => $mobile,
            'amount_paid' => $amountPaid,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{mobile: string, aadhaar_number: string}
     */
    private static function resolveDonationLookupQuery(array $query): array
    {
        $mobile = '';
        foreach (['phone_number', 'mobile', 'phone'] as $key) {
            $mobile = trim((string) ($query[$key] ?? ''));
            if ($mobile !== '') {
                break;
            }
        }

        $aadhaarRaw = '';
        foreach (['aadhaar_number', 'aadhaar'] as $key) {
            $aadhaarRaw = trim((string) ($query[$key] ?? ''));
            if ($aadhaarRaw !== '') {
                break;
            }
        }

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
    private static function donationHistoryWithAliases(array $rows): array
    {
        return array_map(static function (array $row): array {
            return array_merge($row, [
                'full_name' => $row['name'] ?? '',
                'phone_number' => $row['mobile'] ?? '',
                'amount' => isset($row['amount_paid']) ? (float) $row['amount_paid'] : null,
                'receipt_photo_path' => $row['transaction_rep_path'] ?? null,
            ]);
        }, $rows);
    }

    public function store(Request $request): void
    {
        $merged = self::mergeDonationFormBody($request->body);
        $validated = Validator::validate($merged, [
            'name' => 'required',
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'amount_paid' => 'required|numeric',
            'place' => 'required',
            'donation_category' => 'required',
            'email' => 'email_optional',
        ]);
        $validated['transaction_id'] = isset($request->body['transaction_id']) ? trim((string) $request->body['transaction_id']) : null;
        if ($validated['transaction_id'] === '') {
            $validated['transaction_id'] = null;
        }
        $email = $validated['email'] ?? null;
        $validated['email'] = ($email === null || $email === '') ? null : (string) $email;

        $result = $this->service->store($validated, $request->files);

        Response::json([
            'success' => true,
            'message' => 'Donation saved successfully.',
            'data' => $result,
        ], 201);
    }

    public function listByMobile(Request $request): void
    {
        $validated = self::resolveDonationLookupQuery($request->query);

        $rows = $this->service->listByMobileAndAadhaar(
            (string) $validated['mobile'],
            (string) $validated['aadhaar_number']
        );

        Response::json([
            'success' => true,
            'data' => self::donationHistoryWithAliases($rows),
        ]);
    }

    public function verifyDonationEligibility(Request $request): void
    {
        $validated = self::resolveDonationLookupQuery($request->query);

        Response::json([
            'success' => true,
            'data' => $this->service->verifyDonationEligibility(
                (string) $validated['mobile'],
                (string) $validated['aadhaar_number']
            ),
        ]);
    }
}
