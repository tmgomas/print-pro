<?php

namespace App\Repositories;

use App\Models\PaymentVerification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentVerificationRepository extends BaseRepository
{
    public function __construct(PaymentVerification $model)
    {
        parent::__construct($model);
    }

    /**
     * Get pending verifications with pagination
     */
    public function getPendingVerifications(int $branchId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['invoice', 'customer', 'payment'])
            ->where('verification_status', 'pending');

        if ($branchId) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($branchId) {
                $invoiceQuery->where('branch_id', $branchId);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get verifications by invoice ID
     */
    public function getByInvoiceId(int $invoiceId): Collection
    {
        return $this->model->with(['customer', 'verifiedBy'])
            ->where('invoice_id', $invoiceId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get verifications by customer ID
     */
    public function getByCustomerId(int $customerId): Collection
    {
        return $this->model->with(['invoice', 'verifiedBy'])
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get verification statistics
     */
    public function getVerificationStatistics(int $branchId = null): array
    {
        $query = $this->model->query();

        if ($branchId) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($branchId) {
                $invoiceQuery->where('branch_id', $branchId);
            });
        }

        $verifications = $query->get();

        return [
            'total_verifications' => $verifications->count(),
            'pending_count' => $verifications->where('verification_status', 'pending')->count(),
            'verified_count' => $verifications->where('verification_status', 'verified')->count(),
            'rejected_count' => $verifications->where('verification_status', 'rejected')->count(),
            'total_claimed_amount' => $verifications->sum('claimed_amount'),
            'verified_amount' => $verifications->where('verification_status', 'verified')->sum('claimed_amount'),
        ];
    }

    /**
     * Update verification status
     */
    public function updateVerificationStatus(int $verificationId, string $status, int $verifiedBy, string $reason = null): bool
    {
        $updateData = [
            'verification_status' => $status,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ];

        if ($reason) {
            $updateData['rejection_reason'] = $reason;
        }

        return $this->model->where('id', $verificationId)->update($updateData);
    }

    /**
     * Get recent verifications for dashboard
     */
    public function getRecentVerifications(int $branchId = null, int $limit = 10): Collection
    {
        $query = $this->model->with(['invoice', 'customer'])
            ->orderBy('created_at', 'desc');

        if ($branchId) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($branchId) {
                $invoiceQuery->where('branch_id', $branchId);
            });
        }

        return $query->limit($limit)->get();
    }
}