<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DonationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO donations (name, email, place, donation_category, mobile, aadhaar_number, amount_paid, transaction_id, status, aadhaar_front_path, aadhaar_back_path, transaction_rep_path, individual_photo_path)
             VALUES (:name, :email, :place, :donation_category, :mobile, :aadhaar_number, :amount_paid, :transaction_id, :status, :aadhaar_front_path, :aadhaar_back_path, :transaction_rep_path, :individual_photo_path)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'place' => $data['place'] ?? null,
            'donation_category' => $data['donation_category'] ?? null,
            'mobile' => $data['mobile'],
            'aadhaar_number' => $data['aadhaar_number'],
            'amount_paid' => $data['amount_paid'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'aadhaar_front_path' => $data['aadhaar_front_path'] ?? null,
            'aadhaar_back_path' => $data['aadhaar_back_path'] ?? null,
            'transaction_rep_path' => $data['transaction_rep_path'] ?? null,
            'individual_photo_path' => $data['individual_photo_path'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByMobile(string $mobile): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, place, donation_category, mobile, aadhaar_number, amount_paid, transaction_id, status, aadhaar_front_path, aadhaar_back_path, transaction_rep_path, individual_photo_path, created_at
             FROM donations WHERE mobile = :mobile ORDER BY id DESC'
        );
        $stmt->execute(['mobile' => $mobile]);

        return $stmt->fetchAll();
    }

    public function listByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, place, donation_category, mobile, aadhaar_number, amount_paid, transaction_id, status, aadhaar_front_path, aadhaar_back_path, transaction_rep_path, individual_photo_path, created_at
             FROM donations WHERE mobile = :mobile AND aadhaar_number = :aadhaar_number ORDER BY id DESC'
        );
        $stmt->execute(['mobile' => $mobile, 'aadhaar_number' => $aadhaarNumber]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, place, donation_category, mobile, aadhaar_number, amount_paid, transaction_id, status, aadhaar_front_path, aadhaar_back_path, transaction_rep_path, individual_photo_path, created_at
             FROM donations WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @param array{search?: string, status?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listForAdmin(array $params): array
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $status = isset($params['status']) ? trim((string) $params['status']) : '';
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
        $limit = min(max($limit, 1), 200);
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $offset = max($offset, 0);
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        $where = ['1=1'];
        $bind = [];
        if ($search !== '') {
            $where[] = '(name LIKE :search OR mobile LIKE :search2 OR aadhaar_number LIKE :search3 OR transaction_id LIKE :search4 OR place LIKE :search5 OR donation_category LIKE :search6 OR email LIKE :search7)';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
            $bind['search7'] = '%' . $search . '%';
        }
        if ($status !== '' && in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $where[] = 'status = :status';
            $bind['status'] = $status;
        }
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $sql = 'SELECT id, name, email, place, donation_category, mobile, aadhaar_number, amount_paid, transaction_id, status,
                       aadhaar_front_path, aadhaar_back_path, transaction_rep_path, individual_photo_path, created_at
                FROM donations WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll();
    }

    /**
     * @param array{start_date?: string, end_date?: string} $params
     */
    public function getSummary(array $params = []): array
    {
        $where = ['1=1'];
        $bind = [];
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $base = 'FROM donations WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(amount_paid), 0) AS total_amount ' . $base);
        $stmt->execute($bind);
        $total = (float) ($stmt->fetch()['total_amount'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c ' . $base . ' AND status = \'verified\'');
        $stmt->execute($bind);
        $verified = (int) ($stmt->fetch()['c'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c ' . $base . ' AND status = \'pending\'');
        $stmt->execute($bind);
        $pending = (int) ($stmt->fetch()['c'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c ' . $base . ' AND status = \'rejected\'');
        $stmt->execute($bind);
        $rejected = (int) ($stmt->fetch()['c'] ?? 0);
        return ['total_amount' => $total, 'verified_count' => $verified, 'pending_count' => $pending, 'rejected_count' => $rejected];
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE donations SET status = :status WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array{search?: string, status?: string, start_date?: string, end_date?: string} $params
     */
    public function countForAdmin(array $params): int
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $status = isset($params['status']) ? trim((string) $params['status']) : '';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $where = ['1=1'];
        $bind = [];
        if ($search !== '') {
            $where[] = '(name LIKE :search OR mobile LIKE :search2 OR aadhaar_number LIKE :search3 OR transaction_id LIKE :search4 OR place LIKE :search5 OR donation_category LIKE :search6 OR email LIKE :search7)';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
            $bind['search7'] = '%' . $search . '%';
        }
        if ($status !== '' && in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $where[] = 'status = :status';
            $bind['status'] = $status;
        }
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM donations WHERE ' . implode(' AND ', $where));
        $stmt->execute($bind);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }
}
