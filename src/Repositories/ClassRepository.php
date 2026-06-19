<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClassRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function allActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, class_name, total_fee FROM classes WHERE is_active = 1 ORDER BY id ASC'
        );

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, class_name, total_fee FROM classes WHERE id = :id AND is_active = 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** Find by id regardless of is_active (for update) */
    public function findByIdAny(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, class_name, total_fee, is_active FROM classes WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(string $class_name, float $total_fee, bool $is_active = true): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO classes (class_name, total_fee, is_active) VALUES (:class_name, :total_fee, :is_active)'
        );
        $stmt->execute([
            'class_name' => $class_name,
            'total_fee' => $total_fee,
            'is_active' => $is_active ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{class_name?: string, total_fee?: float, is_active?: bool} $data
     */
    public function update(int $id, array $data): void
    {
        $updates = [];
        $params = ['id' => $id];

        if (array_key_exists('class_name', $data)) {
            $updates[] = 'class_name = :class_name';
            $params['class_name'] = $data['class_name'];
        }
        if (array_key_exists('total_fee', $data)) {
            $updates[] = 'total_fee = :total_fee';
            $params['total_fee'] = $data['total_fee'];
        }
        if (array_key_exists('is_active', $data)) {
            $updates[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if ($updates === []) {
            return;
        }

        $sql = 'UPDATE classes SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, class_name, total_fee, is_active, created_at FROM classes ORDER BY id ASC'
        );

        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM classes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS c FROM classes WHERE is_active = 1');
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** Admin: course distribution - each class with enrollment count (distinct aadhaar per class). */
    public function courseDistribution(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.id AS class_id, c.class_name,
                    (SELECT COUNT(DISTINCT cp2.aadhaar_number) FROM class_payments cp2 WHERE cp2.class_id = c.id) AS enrollment_count
             FROM classes c
             WHERE c.is_active = 1
             ORDER BY c.id ASC'
        );
        return $stmt->fetchAll();
    }
}
