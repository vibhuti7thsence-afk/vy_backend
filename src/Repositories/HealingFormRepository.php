<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class HealingFormRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO healing_form_submissions (
                full_name, date_of_birth, time_of_birth, place_of_birth, current_location, mobile, email, address,
                aadhaar_number, aadhaar_front_path, aadhaar_back_path, star_name, issue_type, issue_description, current_picture_path,
                declaration_accepted, amount_paid, transaction_id, transaction_receipt_path
            ) VALUES (
                :full_name, :date_of_birth, :time_of_birth, :place_of_birth, :current_location, :mobile, :email, :address,
                :aadhaar_number, :aadhaar_front_path, :aadhaar_back_path, :star_name, :issue_type, :issue_description, :current_picture_path,
                :declaration_accepted, :amount_paid, :transaction_id, :transaction_receipt_path
            )'
        );

        $stmt->execute([
            'full_name' => $data['full_name'],
            'date_of_birth' => $data['date_of_birth'],
            'time_of_birth' => $data['time_of_birth'] ?? null,
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'current_location' => $data['current_location'] ?? null,
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'aadhaar_number' => $data['aadhaar_number'],
            'aadhaar_front_path' => $data['aadhaar_front_path'],
            'aadhaar_back_path' => $data['aadhaar_back_path'],
            'star_name' => $data['star_name'] ?? null,
            'issue_type' => $data['issue_type'] ?? null,
            'issue_description' => $data['issue_description'] ?? null,
            'current_picture_path' => $data['current_picture_path'] ?? null,
            'declaration_accepted' => $data['declaration_accepted'],
            'amount_paid' => $data['amount_paid'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'transaction_receipt_path' => $data['transaction_receipt_path'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByMobile(string $mobile): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, date_of_birth, time_of_birth, place_of_birth, current_location, mobile, email, address,
                    aadhaar_number, aadhaar_front_path, aadhaar_back_path, star_name, issue_type, issue_description, current_picture_path,
                    declaration_accepted, amount_paid, transaction_id, transaction_receipt_path, created_at
             FROM healing_form_submissions
             WHERE mobile = :mobile
             ORDER BY created_at DESC'
        );
        $stmt->execute(['mobile' => $mobile]);
        return $stmt->fetchAll();
    }

    public function listByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, date_of_birth, time_of_birth, place_of_birth, current_location, mobile, email, address,
                    aadhaar_number, aadhaar_front_path, aadhaar_back_path, star_name, issue_type, issue_description, current_picture_path,
                    declaration_accepted, amount_paid, transaction_id, transaction_receipt_path, created_at
             FROM healing_form_submissions
             WHERE mobile = :mobile AND aadhaar_number = :aadhaar_number
             ORDER BY created_at DESC'
        );
        $stmt->execute([
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarNumber,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * @param array{search?: string, issue_type?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listForAdmin(array $params): array
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $issueType = isset($params['issue_type']) ? trim((string) $params['issue_type']) : '';
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
        $limit = min(max($limit, 1), 200);
        $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
        $offset = max($offset, 0);
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        $where = ['1=1'];
        $bind = [];
        if ($search !== '') {
            $where[] = '(full_name LIKE :search OR mobile LIKE :search2 OR aadhaar_number LIKE :search3 OR transaction_id LIKE :search4 OR issue_description LIKE :search5 OR star_name LIKE :search6)';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
        }
        if ($issueType !== '') {
            $where[] = 'issue_type = :issue_type';
            $bind['issue_type'] = $issueType;
        }
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }

        $sql = 'SELECT id, full_name, date_of_birth, time_of_birth, place_of_birth, current_location, mobile, email, address,
                       aadhaar_number, star_name, issue_type, issue_description, declaration_accepted, amount_paid, transaction_id, created_at
                FROM healing_form_submissions
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll();
    }

    /**
     * @param array{search?: string, issue_type?: string, start_date?: string, end_date?: string} $params
     */
    public function countForAdmin(array $params): int
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $issueType = isset($params['issue_type']) ? trim((string) $params['issue_type']) : '';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        $where = ['1=1'];
        $bind = [];
        if ($search !== '') {
            $where[] = '(full_name LIKE :search OR mobile LIKE :search2 OR aadhaar_number LIKE :search3 OR transaction_id LIKE :search4 OR issue_description LIKE :search5 OR star_name LIKE :search6)';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
        }
        if ($issueType !== '') {
            $where[] = 'issue_type = :issue_type';
            $bind['issue_type'] = $issueType;
        }
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM healing_form_submissions WHERE ' . implode(' AND ', $where));
        $stmt->execute($bind);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }
}
