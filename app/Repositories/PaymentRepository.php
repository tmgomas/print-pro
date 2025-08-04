<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Models\PaymentVerification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class PaymentRepository extends BaseRepository
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    /**
     * Get payments with filters and pagination
     */
    public function getFilteredPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['invoice', 'customer', 'branch', 'receivedBy', 'verifiedBy']);

        // Apply branch filter
        if (isset($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Apply status filter
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply verification status filter
        if (isset($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        // Apply payment method filter
        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Apply date range filter
        if (isset($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        // Apply search filter
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('payment_reference', 'like', "%{$search}%")
                  ->orWhere('transaction_id', 'like', "%{$search}%")
                  ->orWhere('gateway_reference', 'like', "%{$search}%")
                  ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                      $invoiceQuery->where('invoice_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('customer_code', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('payment_date', 'desc')->paginate($perPage);
    }

    /**
     * Get payments by invoice ID
     */
    public function getByInvoiceId(int $invoiceId): Collection
    {
        return $this->model->with(['receivedBy', 'verifiedBy'])
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get pending verification payments
     */
    public function getPendingVerificationPayments(int $branchId = null): Collection
    {
        $query = $this->model->with(['invoice', 'customer', 'receivedBy'])
            ->where('verification_status', 'pending');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('payment_date', 'desc')->get();
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStatistics(int $branchId = null, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $query = $this->model->query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->where('status', 'completed')->sum('amount'),
            'pending_verification' => $payments->where('verification_status', 'pending')->count(),
            'verified_payments' => $payments->where('verification_status', 'verified')->count(),
            'rejected_payments' => $payments->where('verification_status', 'rejected')->count(),
            'pending_amount' => $payments->where('status', 'pending')->sum('amount'),
            'completed_amount' => $payments->where('status', 'completed')->sum('amount'),
            'by_method' => $payments->groupBy('payment_method')->map->count(),
            'by_status' => $payments->groupBy('status')->map->count(),
            'by_verification_status' => $payments->groupBy('verification_status')->map->count(),
        ];
    }

    /**
     * Get total paid amount for invoice
     */
    public function getTotalPaidForInvoice(int $invoiceId): float
    {
        return $this->model->where('invoice_id', $invoiceId)
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Get payment by reference
     */
    public function getByReference(string $paymentReference): ?Payment
    {
        return $this->model->with(['invoice', 'customer', 'branch'])
            ->where('payment_reference', $paymentReference)
            ->first();
    }

    /**
     * Get recent payments for dashboard
     */
    public function getRecentPayments(int $branchId = null, int $limit = 10): Collection
    {
        $query = $this->model->with(['invoice', 'customer'])
            ->orderBy('created_at', 'desc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Update payment status and verification
     */
    public function updateVerificationStatus(int $paymentId, string $status, int $verifiedBy, string $notes = null): bool
    {
        return $this->model->where('id', $paymentId)->update([
            'verification_status' => $status,
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'notes' => $notes,
            'status' => $status === 'verified' ? 'completed' : ($status === 'rejected' ? 'failed' : 'pending'),
        ]);
    }

    /**
     * Get payments by customer ID
     */
    public function getByCustomerId(int $customerId): Collection
    {
        return $this->model->with(['invoice'])
            ->where('customer_id', $customerId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get overdue payments
     */
    public function getOverduePayments(int $branchId = null): Collection
    {
        $query = $this->model->with(['invoice', 'customer'])
            ->whereHas('invoice', function ($invoiceQuery) {
                $invoiceQuery->where('due_date', '<', now())
                           ->where('payment_status', '!=', 'paid');
            })
            ->where('status', 'pending');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('payment_date', 'asc')->get();
    }

    /**
     * Get daily payment summary
     */
    public function getDailyPaymentSummary(Carbon $date, int $branchId = null): array
    {
        $query = $this->model->whereDate('payment_date', $date);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $payments = $query->get();

        return [
            'date' => $date->format('Y-m-d'),
            'total_transactions' => $payments->count(),
            'total_amount' => $payments->where('status', 'completed')->sum('amount'),
            'cash_amount' => $payments->where('payment_method', 'cash')->where('status', 'completed')->sum('amount'),
            'bank_transfer_amount' => $payments->where('payment_method', 'bank_transfer')->where('status', 'completed')->sum('amount'),
            'online_amount' => $payments->where('payment_method', 'online')->where('status', 'completed')->sum('amount'),
            'pending_verification' => $payments->where('verification_status', 'pending')->count(),
            'verified_count' => $payments->where('verification_status', 'verified')->count(),
        ];
    }
}