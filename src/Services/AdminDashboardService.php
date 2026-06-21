<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ClassPaymentRepository;
use App\Repositories\ClassRepository;
use App\Repositories\DonationRepository;
use App\Repositories\HealingFormRepository;

final class AdminDashboardService
{
    public function __construct(
        private readonly ClassRepository $classRepository = new ClassRepository(),
        private readonly ClassPaymentRepository $paymentRepository = new ClassPaymentRepository(),
        private readonly DonationRepository $donationRepository = new DonationRepository(),
        private readonly HealingFormRepository $healingFormRepository = new HealingFormRepository()
    ) {
    }

    /**
     * Dashboard overview + payment summary. Optional start_date, end_date (Y-m-d).
     * @param array{start_date?: string, end_date?: string} $params
     */
    public function getDashboard(array $params = []): array
    {
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        $registrationsCount = $this->paymentRepository->countRegistrations($startDate, $endDate);
        $donationSummary = $this->donationRepository->getSummary($params);
        $donationsRecordCount = $donationSummary['verified_count'] + $donationSummary['pending_count'] + $donationSummary['rejected_count'];

        return [
            'overview' => [
                'registrations_count' => $registrationsCount,
                'donations_count' => $donationsRecordCount,
                'active_classes_count' => $this->classRepository->countActive(),
                'completed_registrations_count' => $this->paymentRepository->countCompletedRegistrations($startDate, $endDate),
            ],
            'payment_summary' => [
                'total_donations' => (float) $donationSummary['total_amount'],
                'total_collected' => $this->paymentRepository->totalCollected($startDate, $endDate),
                'pending_amount' => $this->paymentRepository->totalPendingAmount($startDate, $endDate),
                'verified_donations_count' => $donationSummary['verified_count'],
            ],
        ];
    }

    public function getCourseDistribution(): array
    {
        return $this->classRepository->courseDistribution();
    }

    /**
     * Recent activity: last N donations and last M registrations, merged and sorted by date.
     */
    public function getRecentActivity(int $limit = 20): array
    {
        $donations = $this->donationRepository->listForAdmin(['limit' => $limit, 'offset' => 0]);
        $registrations = $this->paymentRepository->recentRegistrationActivities($limit);
        $activities = [];
        foreach ($donations as $d) {
            $activities[] = [
                'type' => 'donation',
                'id' => (int) $d['id'],
                'name' => $d['name'],
                'mobile' => $d['mobile'],
                'detail' => '₹' . number_format((float) $d['amount_paid'], 0),
                'status' => $d['status'] ?? 'pending',
                'created_at' => $d['created_at'],
            ];
        }
        foreach ($registrations as $r) {
            $status = ((float) ($r['payment_status'] ?? '') === 0) ? 'pending' : (($r['payment_status'] === 'paid') ? 'completed' : 'partial');
            $activities[] = [
                'type' => 'registration',
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'mobile' => $r['mobile'],
                'detail' => $r['class_name'] ?? '',
                'status' => $r['payment_status'] === 'paid' ? 'completed' : ($r['payment_status'] === 'partial' ? 'partial' : 'pending'),
                'created_at' => $r['created_at'],
                'aadhaar_doc_path' => $r['aadhaar_doc_path'] ?? null,
                'aadhaar_doc_back_path' => $r['aadhaar_doc_back_path'] ?? null,
                'transaction_receipt_path' => $r['transaction_receipt_path'] ?? null,
                'aadhaar_front_path' => $r['aadhaar_doc_path'] ?? null,
                'aadhaar_back_path' => $r['aadhaar_doc_back_path'] ?? null,
                'fee_receipt_path' => $r['transaction_receipt_path'] ?? null,
            ];
        }
        usort($activities, static fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($activities, 0, $limit);
    }

    /**
     * Admin list registrations with search and status filter. Returns [list, total].
     * @param array{search?: string, status?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listRegistrations(array $params = []): array
    {
        $rows = $this->paymentRepository->listRegistrationsForAdmin($params);
        $total = $this->paymentRepository->countRegistrationsForAdmin($params);
        $out = [];
        foreach ($rows as $r) {
            $agreed = (float) ($r['agreed_fee'] ?? 0);
            $paid = (float) ($r['paid_amount'] ?? 0);
            $remaining = (float) ($r['remaining_amount'] ?? 0);
            $status = $remaining <= 0 ? 'completed' : ($paid > 0 ? 'partial' : 'pending');
            $pct = $agreed > 0 ? round($paid / $agreed * 100, 1) : 0;
            $out[] = [
                'id' => $r['id'] ?? null,
                'aadhaar_number' => $r['aadhaar_number'],
                'class_id' => (int) $r['class_id'],
                'class_name' => $r['class_name'],
                'name' => $r['name'],
                'mobile' => $r['mobile'],
                'email' => $r['email'] ?? null,
                'location' => $r['location'] ?? null,
                'preferred_time' => $r['preferred_time'] ?? null,
                'siblings_name' => $r['siblings_name'] ?? null,
                'age_or_birth' => $r['age_or_birth'] ?? null,
                'qualification' => $r['qualification'] ?? null,
                'father_name' => $r['father_name'] ?? null,
                'father_phone' => $r['father_phone'] ?? null,
                'mother_name' => $r['mother_name'] ?? null,
                'mother_phone' => $r['mother_phone'] ?? null,
                'message' => $r['message'] ?? null,
                'why_attend_course' => $r['why_attend_course'] ?? null,
                'additional_message' => $r['additional_message'] ?? null,
                'created_at' => $r['created_at'] ?? null,
                'agreed_fee' => $agreed,
                'amount_paid' => $paid,
                'amount_remaining' => $remaining,
                'payment_status' => $status,
                'payment_percentage_complete' => $pct,
                'aadhaar_doc_path' => $r['aadhaar_doc_path'] ?? null,
                'aadhaar_doc_back_path' => $r['aadhaar_doc_back_path'] ?? null,
                'transaction_receipt_path' => $r['transaction_receipt_path'] ?? null,
                'aadhaar_front_path' => $r['aadhaar_doc_path'] ?? null,
                'aadhaar_back_path' => $r['aadhaar_doc_back_path'] ?? null,
                'fee_receipt_path' => $r['transaction_receipt_path'] ?? null,
            ];
        }
        return ['list' => $out, 'total' => $total];
    }

    /**
     * Admin list donations with search and status filter. Returns [list, total].
     * @param array{search?: string, status?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listDonations(array $params = []): array
    {
        $list = $this->donationRepository->listForAdmin($params);
        $total = $this->donationRepository->countForAdmin($params);
        return ['list' => $list, 'total' => $total];
    }

    /**
     * Admin list healing form submissions with search, issue_type filter, and pagination.
     * @param array{search?: string, issue_type?: string, limit?: int, offset?: int, start_date?: string, end_date?: string} $params
     */
    public function listHealingSubmissions(array $params = []): array
    {
        $list = $this->healingFormRepository->listForAdmin($params);
        $total = $this->healingFormRepository->countForAdmin($params);
        return ['list' => $list, 'total' => $total];
    }

    /** Donations summary (total amount, verified/pending/rejected counts). */
    public function getDonationsSummary(array $params = []): array
    {
        return $this->donationRepository->getSummary($params);
    }

    /** Update donation status. */
    public function updateDonationStatus(int $id, string $status): array
    {
        $donation = $this->donationRepository->findById($id);
        if ($donation === null) {
            throw new HttpException('Donation not found.', 404);
        }
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            throw new HttpException('Invalid status. Use pending, verified, or rejected.', 422);
        }
        $this->donationRepository->updateStatus($id, $status);
        $donation['status'] = $status;
        return $donation;
    }
}
