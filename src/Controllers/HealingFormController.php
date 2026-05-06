<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\HealingFormService;
use App\Validation\Validator;

final class HealingFormController
{
    public function __construct(
        private readonly HealingFormService $service = new HealingFormService()
    ) {
    }

    public function submit(Request $request): void
    {
        $mergedBody = self::mergeHealingBody($request->body);
        $validated = Validator::validate($mergedBody, [
            'full_name' => 'required',
            'date_of_birth' => 'required',
            'time_of_birth' => 'required',
            'place_of_birth' => 'required',
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
            'address' => 'required',
            'issue_type' => 'required',
            'issue_description' => 'required',
            'amount_paid' => 'required|numeric',
            'email' => 'email_optional',
        ], true);

        $validated['time_of_birth'] = $this->clean($mergedBody['time_of_birth'] ?? null);
        $validated['place_of_birth'] = $this->clean($mergedBody['place_of_birth'] ?? null);
        $validated['current_location'] = $this->clean($mergedBody['current_location'] ?? null);
        $validated['email'] = $this->clean($mergedBody['email'] ?? null);
        $validated['address'] = $this->clean($mergedBody['address'] ?? null);
        $validated['issue_type'] = $this->clean($mergedBody['issue_type'] ?? null);
        $validated['issue_description'] = $this->clean($mergedBody['issue_description'] ?? null);
        $validated['transaction_id'] = $this->clean($mergedBody['transaction_id'] ?? null);
        $validated['star_name'] = $this->clean($mergedBody['star_name'] ?? null);

        $declared = strtolower((string) ($mergedBody['declaration_accepted'] ?? ''));
        $validated['declaration_accepted'] = in_array($declared, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        $validated = Validator::validate($validated, ['declaration_accepted' => 'required']);
        if ((int) $validated['declaration_accepted'] !== 1) {
            throw new HttpException('Declaration must be accepted.', 422);
        }

        $data = $this->service->submit($validated, $request->files);
        Response::json([
            'success' => true,
            'message' => 'Healing form submitted successfully.',
            'data' => $data,
        ], 201);
    }

    public function listByMobile(Request $request): void
    {
        $lookup = self::resolveLookupQuery($request->query);
        $rows = $this->service->listByMobileAndAadhaar($lookup['mobile'], $lookup['aadhaar_number']);

        Response::json([
            'success' => true,
            'data' => self::rowsWithAliases($rows),
        ]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function mergeHealingBody(array $body): array
    {
        $amountPaid = $body['amount_paid'] ?? null;
        if ($amountPaid === null || $amountPaid === '') {
            $amountPaid = $body['fee_amount_paid'] ?? null;
        }

        return array_merge($body, [
            'full_name' => trim((string) ($body['full_name'] ?? $body['your_name'] ?? '')),
            'mobile' => trim((string) ($body['mobile'] ?? $body['phone_number'] ?? $body['phone'] ?? '')),
            'place_of_birth' => trim((string) ($body['place_of_birth'] ?? $body['place_of_birth_city'] ?? '')),
            'star_name' => trim((string) ($body['star_name'] ?? $body['star'] ?? '')),
            'issue_type' => trim((string) ($body['issue_type'] ?? $body['issues'] ?? $body['issue'] ?? '')),
            'issue_description' => trim((string) ($body['issue_description'] ?? $body['problem_description'] ?? '')),
            'amount_paid' => $amountPaid,
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{mobile: string, aadhaar_number: string}
     */
    private static function resolveLookupQuery(array $query): array
    {
        $mobile = trim((string) ($query['mobile'] ?? $query['phone_number'] ?? $query['phone'] ?? ''));
        $aadhaar = trim((string) ($query['aadhaar_number'] ?? $query['aadhaar'] ?? ''));
        $normalized = [
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaar !== '' ? preg_replace('/\D/', '', $aadhaar) : '',
        ];

        return Validator::validate($normalized, [
            'mobile' => 'required|mobile',
            'aadhaar_number' => 'required|aadhaar',
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function rowsWithAliases(array $rows): array
    {
        return array_map(static function (array $row): array {
            return array_merge($row, [
                'your_name' => $row['full_name'] ?? '',
                'phone_number' => $row['mobile'] ?? '',
                'star' => $row['star_name'] ?? null,
                'issues' => $row['issue_type'] ?? null,
                'problem_description' => $row['issue_description'] ?? null,
                'fee_amount_paid' => isset($row['amount_paid']) ? (float) $row['amount_paid'] : null,
                'fee_receipt_path' => $row['transaction_receipt_path'] ?? null,
                'recent_picture_path' => $row['current_picture_path'] ?? null,
            ]);
        }, $rows);
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
