<?php

declare(strict_types=1);

namespace App\Validation;

use App\Core\HttpException;

final class Validator
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $rules
     */
    public static function validate(array $payload, array $rules, bool $allowMissingRequired = false): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleSet) {
            $rulesArray = explode('|', $ruleSet);
            $value = $payload[$field] ?? null;

            foreach ($rulesArray as $rule) {
                if ($rule === 'required' && $allowMissingRequired === false && ($value === null || $value === '')) {
                    $errors[$field][] = 'This field is required.';
                }

                if ($rule === 'numeric' && $value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[$field][] = 'Must be numeric.';
                }

                if ($rule === 'mobile' && $value !== null && $value !== '' && !preg_match('/^[0-9]{10,15}$/', (string) $value)) {
                    $errors[$field][] = 'Mobile number must be 10-15 digits.';
                }

                if ($rule === 'email_optional' && $value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'Invalid email.';
                }

                if ($rule === 'aadhaar' && $value !== null && $value !== '') {
                    $digits = preg_replace('/\D/', '', (string) $value);
                    if (strlen($digits) !== 12) {
                        $errors[$field][] = 'Aadhaar number must be 12 digits.';
                    }
                }
            }

            if (in_array('aadhaar', $rulesArray, true) && is_string($value) && $value !== '') {
                $value = preg_replace('/\D/', '', trim($value));
            }
            $clean[$field] = is_string($value) ? trim($value) : $value;
        }

        if ($errors !== []) {
            throw new HttpException('Validation failed: ' . json_encode($errors), 422);
        }

        return $clean;
    }
}
