<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ClassRepository;

final class ClassService
{
    public function __construct(
        private readonly ClassRepository $classRepository = new ClassRepository()
    ) {
    }

    /** @param array{class_name: string, total_fee: mixed, is_active?: mixed} $payload */
    public function createClass(array $payload): array
    {
        $name = trim((string) ($payload['class_name'] ?? ''));
        if ($name === '') {
            throw new HttpException('class_name is required and cannot be empty.', 422);
        }

        $fee = is_numeric($payload['total_fee'] ?? '') ? (float) $payload['total_fee'] : null;
        if ($fee === null || $fee <= 0) {
            throw new HttpException('total_fee is required and must be a positive number.', 422);
        }

        $isActive = !isset($payload['is_active']) || (bool) $payload['is_active'];

        $id = $this->classRepository->create($name, $fee, $isActive);

        return [
            'id' => $id,
            'class_name' => $name,
            'total_fee' => $fee,
            'is_active' => $isActive,
        ];
    }

    public function listAllClasses(): array
    {
        return array_map(static function (array $row): array {
            return [
                'id'         => (int) $row['id'],
                'class_name' => $row['class_name'],
                'total_fee'  => (float) $row['total_fee'],
                'is_active'  => (bool) $row['is_active'],
                'created_at' => $row['created_at'],
            ];
        }, $this->classRepository->all());
    }

    /** @param array{id: mixed} $payload */
    public function deleteClass(array $payload): void
    {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException('id is required and must be a positive integer.', 422);
        }

        $existing = $this->classRepository->findByIdAny($id);
        if ($existing === null) {
            throw new HttpException('Class not found.', 404);
        }

        $this->classRepository->delete($id);
    }

    /** @param array{id: mixed, class_name?: string, total_fee?: mixed, is_active?: mixed} $payload */
    public function updateClass(array $payload): array
    {
        error_log('[ClassService::updateClass] raw payload: ' . json_encode($payload));

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException('id is required and must be a positive integer.', 422);
        }

        $existing = $this->classRepository->findByIdAny($id);
        if ($existing === null) {
            throw new HttpException('Class not found.', 404);
        }

        error_log('[ClassService::updateClass] existing class before update: ' . json_encode($existing));

        $data = [];

        if (array_key_exists('class_name', $payload)) {
            $name = trim((string) $payload['class_name']);
            if ($name === '') {
                throw new HttpException('class_name cannot be empty.', 422);
            }
            $data['class_name'] = $name;
        }

        if (array_key_exists('total_fee', $payload)) {
            $fee = is_numeric($payload['total_fee']) ? (float) $payload['total_fee'] : null;
            if ($fee === null || $fee <= 0) {
                throw new HttpException('total_fee must be a positive number.', 422);
            }
            $data['total_fee'] = $fee;
        }

        if (array_key_exists('is_active', $payload) && $payload['is_active'] !== null) {
            $rawIsActive = $payload['is_active'];
            $data['is_active'] = (bool) $rawIsActive;
            error_log(sprintf(
                '[ClassService::updateClass] is_active in payload — raw value: %s, type: %s, cast to bool: %s',
                json_encode($rawIsActive),
                gettype($rawIsActive),
                $data['is_active'] ? 'true' : 'false'
            ));
        } else {
            error_log('[ClassService::updateClass] is_active not in payload or null — keeping existing value: '
                . ($existing['is_active'] ? 'true' : 'false'));
        }

        if ($data === []) {
            throw new HttpException('Provide at least one of: class_name, total_fee, is_active.', 422);
        }

        error_log('[ClassService::updateClass] fields being written to DB: ' . json_encode($data));

        $this->classRepository->update($id, $data);

        $updated = $this->classRepository->findByIdAny($id);

        error_log('[ClassService::updateClass] class after update: ' . json_encode($updated));

        return [
            'id' => $id,
            'class_name' => $updated['class_name'],
            'total_fee' => (float) $updated['total_fee'],
            'is_active' => (bool) $updated['is_active'],
        ];
    }
}
