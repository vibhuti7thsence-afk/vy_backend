<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClassPaymentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Distinct (mobile, aadhaar_number) pairs already used for this class where either identifier matches.
     *
     * @return list<array{mobile: string, aadhaar_number: string}>
     */
    public function distinctIdentitiesForClassByMobileOrAadhaar(int $classId, string $mobile, string $aadhaarNumber): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT mobile, aadhaar_number FROM class_payments
             WHERE class_id = :class_id AND (mobile = :mobile OR aadhaar_number = :aadhaar_number)'
        );
        $stmt->execute([
            'class_id' => $classId,
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarNumber,
        ]);

        return $stmt->fetchAll();
    }

    public function hasRegistrationWithMobileAndAadhaar(string $mobile, string $aadhaarNumber): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM class_payments WHERE mobile = :mobile AND aadhaar_number = :aadhaar_number LIMIT 1'
        );
        $stmt->execute([
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarNumber,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function findLatestRegistrantNameByMobileAndAadhaar(string $mobile, string $aadhaarNumber): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT name FROM class_payments
             WHERE mobile = :mobile AND aadhaar_number = :aadhaar_number
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarNumber,
        ]);

        $name = $stmt->fetchColumn();
        return $name === false ? null : (string) $name;
    }

    public function totalPaidByMobileAndClass(string $mobile, int $classId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM class_payments WHERE mobile = :mobile AND class_id = :class_id'
        );
        $stmt->execute([
            'mobile' => $mobile,
            'class_id' => $classId,
        ]);

        return (float) ($stmt->fetch()['total_paid'] ?? 0);
    }

    public function totalPaidByAadhaarAndClass(string $aadhaarNumber, int $classId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM class_payments WHERE aadhaar_number = :aadhaar_number AND class_id = :class_id'
        );
        $stmt->execute([
            'aadhaar_number' => $aadhaarNumber,
            'class_id' => $classId,
        ]);

        return (float) ($stmt->fetch()['total_paid'] ?? 0);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO class_payments (mobile, aadhaar_number, name, email, class_id, preferred_time, location, siblings_name, age_or_birth, qualification, father_name, father_phone, mother_name, mother_phone, message, why_attend_course, additional_message, amount_paid, transaction_id, transaction_msg, aadhaar_doc_path, aadhaar_doc_back_path, transaction_receipt_path, payment_status)
             VALUES (:mobile, :aadhaar_number, :name, :email, :class_id, :preferred_time, :location, :siblings_name, :age_or_birth, :qualification, :father_name, :father_phone, :mother_name, :mother_phone, :message, :why_attend_course, :additional_message, :amount_paid, :transaction_id, :transaction_msg, :aadhaar_doc_path, :aadhaar_doc_back_path, :transaction_receipt_path, :payment_status)'
        );
        $stmt->execute([
            'mobile' => $data['mobile'],
            'aadhaar_number' => $data['aadhaar_number'],
            'name' => $data['name'],
            'email' => $data['email'],
            'class_id' => $data['class_id'],
            'preferred_time' => $data['preferred_time'] ?? null,
            'location' => $data['location'] ?? null,
            'siblings_name' => $data['siblings_name'] ?? null,
            'age_or_birth' => $data['age_or_birth'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'father_phone' => $data['father_phone'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'mother_phone' => $data['mother_phone'] ?? null,
            'message' => $data['message'] ?? null,
            'why_attend_course' => $data['why_attend_course'] ?? null,
            'additional_message' => $data['additional_message'] ?? null,
            'amount_paid' => $data['amount_paid'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'transaction_msg' => $data['transaction_msg'] ?? null,
            'aadhaar_doc_path' => $data['aadhaar_doc_path'] ?? null,
            'aadhaar_doc_back_path' => $data['aadhaar_doc_back_path'] ?? null,
            'transaction_receipt_path' => $data['transaction_receipt_path'] ?? null,
            'payment_status' => $data['payment_status'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Each row is one fee payment submission (same user may have many over time).
     * Ordered newest first by timestamp and id.
     *
     * @return list<array<string, mixed>>
     */
    public function listTransactionsByMobileAndAadhaar(string $mobile, string $aadhaarNumber, ?int $classId = null): array
    {
        $sql = 'SELECT cp.id, cp.mobile, cp.aadhaar_number, cp.name, cp.email, cp.class_id, c.class_name,
                       cp.amount_paid, cp.transaction_id, cp.payment_status, cp.location, cp.created_at,
                       cp.aadhaar_doc_path, cp.aadhaar_doc_back_path, cp.transaction_receipt_path
                FROM class_payments cp
                INNER JOIN classes c ON c.id = cp.class_id
                WHERE cp.mobile = :mobile AND cp.aadhaar_number = :aadhaar_number';
        $bind = [
            'mobile' => $mobile,
            'aadhaar_number' => $aadhaarNumber,
        ];
        if ($classId !== null && $classId > 0) {
            $sql .= ' AND cp.class_id = :class_id';
            $bind['class_id'] = $classId;
        }
        $sql .= ' ORDER BY cp.created_at DESC, cp.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);

        return $stmt->fetchAll();
    }

    public function summaryByMobile(string $mobile): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                MAX(cp.mobile) AS mobile,
                cp.aadhaar_number,
                c.id AS class_id,
                c.class_name,
                c.total_fee,
                COALESCE(cuf.agreed_fee, c.total_fee) AS agreed_fee,
                COALESCE(SUM(cp.amount_paid), 0) AS paid_amount,
                (COALESCE(cuf.agreed_fee, c.total_fee) - COALESCE(SUM(cp.amount_paid), 0)) AS remaining_amount,
                (SELECT cp2.aadhaar_doc_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_path,
                (SELECT cp2.aadhaar_doc_back_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_back_path,
                (SELECT cp2.transaction_receipt_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS transaction_receipt_path
             FROM class_payments cp
             JOIN classes c ON c.id = cp.class_id
             LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
             WHERE cp.mobile = :mobile AND c.is_active = 1
             GROUP BY cp.aadhaar_number, cp.class_id, c.id, c.class_name, c.total_fee, cuf.agreed_fee
             ORDER BY c.id ASC, cp.aadhaar_number'
        );
        $stmt->execute(['mobile' => $mobile]);

        return $stmt->fetchAll();
    }

    public function summaryByMobileAndAadhaar(string $mobile, string $aadhaarNumber): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                MAX(cp.mobile) AS mobile,
                cp.aadhaar_number,
                c.id AS class_id,
                c.class_name,
                c.total_fee,
                COALESCE(cuf.agreed_fee, c.total_fee) AS agreed_fee,
                COALESCE(SUM(cp.amount_paid), 0) AS paid_amount,
                (COALESCE(cuf.agreed_fee, c.total_fee) - COALESCE(SUM(cp.amount_paid), 0)) AS remaining_amount,
                (SELECT cp2.aadhaar_doc_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_path,
                (SELECT cp2.aadhaar_doc_back_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_back_path,
                (SELECT cp2.transaction_receipt_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS transaction_receipt_path
             FROM class_payments cp
             JOIN classes c ON c.id = cp.class_id
             LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
             WHERE cp.mobile = :mobile AND cp.aadhaar_number = :aadhaar_number AND c.is_active = 1
             GROUP BY cp.aadhaar_number, cp.class_id, c.id, c.class_name, c.total_fee, cuf.agreed_fee
             ORDER BY c.id ASC'
        );
        $stmt->execute(['mobile' => $mobile, 'aadhaar_number' => $aadhaarNumber]);

        return $stmt->fetchAll();
    }

    /** Admin: total amount collected from class payments. Optional date filter on created_at. */
    public function totalCollected(?string $startDate = null, ?string $endDate = null): float
    {
        $where = ['1=1'];
        $bind = [];
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $sql = 'SELECT COALESCE(SUM(amount_paid), 0) AS total FROM class_payments WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return (float) ($stmt->fetch()['total'] ?? 0);
    }

    /** Admin: total pending amount (agreed_fee - paid) per (aadhaar, class), summed. */
    public function totalPendingAmount(?string $startDate = null, ?string $endDate = null): float
    {
        $dateFilter = '';
        $bind = [];
        if ($startDate !== null && $startDate !== '') {
            $dateFilter .= ' AND cp.created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $dateFilter .= ' AND cp.created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $sql = 'SELECT SUM(agreed_fee - paid) AS total FROM (
            SELECT COALESCE(cuf.agreed_fee, c.total_fee) AS agreed_fee,
                   COALESCE(SUM(cp.amount_paid), 0) AS paid
            FROM class_payments cp
            JOIN classes c ON c.id = cp.class_id
            LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
            WHERE 1=1 ' . $dateFilter . '
            GROUP BY cp.aadhaar_number, cp.class_id, c.id, cuf.agreed_fee, c.total_fee
        ) t WHERE (agreed_fee - paid) > 0';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return (float) ($stmt->fetch()['total'] ?? 0);
    }

    /** Admin: count distinct registrations (aadhaar, class_id) from class_payments. */
    public function countRegistrations(?string $startDate = null, ?string $endDate = null): int
    {
        $where = ['1=1'];
        $bind = [];
        if ($startDate !== null && $startDate !== '') {
            $where[] = 'created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $where[] = 'created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $sql = 'SELECT COUNT(*) AS c FROM (SELECT 1 FROM class_payments WHERE ' . implode(' AND ', $where) . ' GROUP BY aadhaar_number, class_id) t';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** Admin: count (aadhaar, class) where remaining <= 0 (fully paid). */
    public function countCompletedRegistrations(?string $startDate = null, ?string $endDate = null): int
    {
        $dateFilter = '';
        $bind = [];
        if ($startDate !== null && $startDate !== '') {
            $dateFilter .= ' AND cp.created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $dateFilter .= ' AND cp.created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $sql = "SELECT COUNT(*) AS c FROM (
            SELECT cp.aadhaar_number, cp.class_id,
                   COALESCE(cuf.agreed_fee, c.total_fee) - COALESCE(SUM(cp.amount_paid), 0) AS rem
            FROM class_payments cp
            JOIN classes c ON c.id = cp.class_id
            LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
            WHERE 1=1 " . $dateFilter . "
            GROUP BY cp.aadhaar_number, cp.class_id, c.id, cuf.agreed_fee, c.total_fee
        ) t WHERE rem <= 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Admin: list registrations with search and status filter.
     * @param array{search?: string, status?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listRegistrationsForAdmin(array $params): array
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $status = isset($params['status']) ? trim((string) $params['status']) : '';
        $limit = min(max((int) ($params['limit'] ?? 50), 1), 200);
        $offset = max((int) ($params['offset'] ?? 0), 0);
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        $bind = [];
        $having = '';
        if ($status === 'completed') {
            $having = ' HAVING remaining <= 0';
        } elseif ($status === 'partial') {
            $having = ' HAVING remaining > 0 AND paid_amount > 0';
        } elseif ($status === 'pending') {
            $having = ' HAVING paid_amount = 0';
        }
        $dateFilter = '';
        if ($startDate !== null && $startDate !== '') {
            $dateFilter .= ' AND cp.created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $dateFilter .= ' AND cp.created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $searchFilter = '';
        if ($search !== '') {
            $searchFilter = ' AND (
                cp.name LIKE :search OR cp.mobile LIKE :search2 OR cp.location LIKE :search3 OR c.class_name LIKE :search4
                OR cp.qualification LIKE :search5 OR cp.father_name LIKE :search6 OR cp.mother_name LIKE :search7
                OR cp.father_phone LIKE :search8 OR cp.mother_phone LIKE :search9 OR cp.age_or_birth LIKE :search10
                OR cp.why_attend_course LIKE :search11 OR cp.additional_message LIKE :search12 OR cp.aadhaar_number LIKE :search13
            )';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
            $bind['search7'] = '%' . $search . '%';
            $bind['search8'] = '%' . $search . '%';
            $bind['search9'] = '%' . $search . '%';
            $bind['search10'] = '%' . $search . '%';
            $bind['search11'] = '%' . $search . '%';
            $bind['search12'] = '%' . $search . '%';
            $bind['search13'] = '%' . $search . '%';
        }
        $sql = "SELECT cp.aadhaar_number, cp.class_id, MAX(cp.name) AS name, MAX(cp.mobile) AS mobile,
                MAX(cp.location) AS location, MAX(cp.preferred_time) AS preferred_time,
                c.class_name, COALESCE(cuf.agreed_fee, c.total_fee) AS agreed_fee,
                COALESCE(SUM(cp.amount_paid), 0) AS paid_amount,
                (COALESCE(cuf.agreed_fee, c.total_fee) - COALESCE(SUM(cp.amount_paid), 0)) AS remaining_amount,
                (SELECT cp2.aadhaar_doc_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_path,
                (SELECT cp2.aadhaar_doc_back_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS aadhaar_doc_back_path,
                (SELECT cp2.transaction_receipt_path FROM class_payments cp2
                 WHERE cp2.aadhaar_number = cp.aadhaar_number AND cp2.class_id = cp.class_id
                 ORDER BY cp2.id DESC LIMIT 1) AS transaction_receipt_path
                FROM class_payments cp
                JOIN classes c ON c.id = cp.class_id
                LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
                WHERE c.is_active = 1 " . $dateFilter . $searchFilter . "
                GROUP BY cp.aadhaar_number, cp.class_id, c.id, c.class_name, cuf.agreed_fee, c.total_fee" . $having . "
                ORDER BY MAX(cp.created_at) DESC LIMIT " . $limit . " OFFSET " . $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll();
    }

    /**
     * Admin: total count of registrations with same filters as listRegistrationsForAdmin.
     * @param array{search?: string, status?: string, start_date?: string, end_date?: string} $params
     */
    public function countRegistrationsForAdmin(array $params): int
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $status = isset($params['status']) ? trim((string) $params['status']) : '';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;
        $bind = [];
        $having = '';
        if ($status === 'completed') {
            $having = ' HAVING remaining <= 0';
        } elseif ($status === 'partial') {
            $having = ' HAVING remaining > 0 AND paid_amount > 0';
        } elseif ($status === 'pending') {
            $having = ' HAVING paid_amount = 0';
        }
        $dateFilter = '';
        if ($startDate !== null && $startDate !== '') {
            $dateFilter .= ' AND cp.created_at >= :start_date';
            $bind['start_date'] = $startDate;
        }
        if ($endDate !== null && $endDate !== '') {
            $dateFilter .= ' AND cp.created_at <= :end_date';
            $bind['end_date'] = $endDate . ' 23:59:59';
        }
        $searchFilter = '';
        if ($search !== '') {
            $searchFilter = ' AND (
                cp.name LIKE :search OR cp.mobile LIKE :search2 OR cp.location LIKE :search3 OR c.class_name LIKE :search4
                OR cp.qualification LIKE :search5 OR cp.father_name LIKE :search6 OR cp.mother_name LIKE :search7
                OR cp.father_phone LIKE :search8 OR cp.mother_phone LIKE :search9 OR cp.age_or_birth LIKE :search10
                OR cp.why_attend_course LIKE :search11 OR cp.additional_message LIKE :search12 OR cp.aadhaar_number LIKE :search13
            )';
            $bind['search'] = '%' . $search . '%';
            $bind['search2'] = '%' . $search . '%';
            $bind['search3'] = '%' . $search . '%';
            $bind['search4'] = '%' . $search . '%';
            $bind['search5'] = '%' . $search . '%';
            $bind['search6'] = '%' . $search . '%';
            $bind['search7'] = '%' . $search . '%';
            $bind['search8'] = '%' . $search . '%';
            $bind['search9'] = '%' . $search . '%';
            $bind['search10'] = '%' . $search . '%';
            $bind['search11'] = '%' . $search . '%';
            $bind['search12'] = '%' . $search . '%';
            $bind['search13'] = '%' . $search . '%';
        }
        $sql = "SELECT COUNT(*) AS c FROM (
            SELECT cp.aadhaar_number, cp.class_id,
                   COALESCE(cuf.agreed_fee, c.total_fee) - COALESCE(SUM(cp.amount_paid), 0) AS remaining,
                   COALESCE(SUM(cp.amount_paid), 0) AS paid_amount
            FROM class_payments cp
            JOIN classes c ON c.id = cp.class_id
            LEFT JOIN class_user_fees cuf ON cuf.aadhaar_number = cp.aadhaar_number AND cuf.class_id = cp.class_id
            WHERE c.is_active = 1 " . $dateFilter . $searchFilter . "
            GROUP BY cp.aadhaar_number, cp.class_id, c.id, cuf.agreed_fee, c.total_fee" . $having . "
        ) t";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** Admin: recent registration payments (last N). */
    public function recentRegistrationActivities(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cp.id, cp.name, cp.mobile, cp.aadhaar_number, cp.class_id, cp.amount_paid, cp.payment_status, cp.created_at,
                    c.class_name,
                    cp.aadhaar_doc_path, cp.aadhaar_doc_back_path, cp.transaction_receipt_path
             FROM class_payments cp
             JOIN classes c ON c.id = cp.class_id
             ORDER BY cp.created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
