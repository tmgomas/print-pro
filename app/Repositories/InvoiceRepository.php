<?php

namespace App\Repositories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class InvoiceRepository extends BaseRepository
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    /**
     * Search and paginate invoices
     */
    public function searchAndPaginate(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->forCompany($companyId)
            ->with(['customer', 'branch', 'creator', 'items.product']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('invoice_number', 'like', "%{$filters['search']}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($filters) {
                      $customerQuery->where('name', 'like', "%{$filters['search']}%")
                                  ->orWhere('customer_code', 'like', "%{$filters['search']}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('due_date', '<', now())
                  ->where('payment_status', '!=', 'paid');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get invoices for dropdown
     */
    public function getForDropdown(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->forCompany($companyId)
            ->select('id', 'invoice_number', 'total_amount', 'payment_status')
            ->with('customer:id,name');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Generate unique invoice number
     */
    public function generateInvoiceNumber(int $branchId): string
    {
        $branch = \App\Models\Branch::findOrFail($branchId);
        
        $lastInvoice = $this->model
            ->where('branch_id', $branchId)
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -6)) + 1 : 1;
        
        return $branch->code . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->forCompany($companyId)
            ->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid')
            ->with(['customer', 'branch']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('due_date', 'asc')->get();
    }

    /**
     * Get pending invoices
     */
    public function getPendingInvoices(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->forCompany($companyId)
            ->where('payment_status', 'pending')
            ->with(['customer', 'branch']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get invoice statistics
     */
    public function getStats(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->forCompany($companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $totalInvoices = $query->count();
        $totalAmount = $query->sum('total_amount');
        $paidAmount = $query->where('payment_status', 'paid')->sum('total_amount');
        $pendingAmount = $query->where('payment_status', 'pending')->sum('total_amount');
        $overdueCount = $query->where('due_date', '<', now())
                             ->where('payment_status', '!=', 'paid')
                             ->count();

        return [
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'overdue_count' => $overdueCount,
            'pending_count' => $query->where('payment_status', 'pending')->count(),
            'paid_count' => $query->where('payment_status', 'paid')->count(),
        ];
    }

    /**
     * Get monthly revenue
     */
    public function getMonthlyRevenue(int $companyId, int $year, ?int $branchId = null): array
    {
        $query = $this->model
            ->forCompany($companyId)
            ->where('payment_status', 'paid')
            ->whereYear('invoice_date', $year);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $results = $query->selectRaw('MONTH(invoice_date) as month, SUM(total_amount) as total')
                        ->groupBy('month')
                        ->orderBy('month')
                        ->get();

        $monthlyData = array_fill(1, 12, 0);
        
        foreach ($results as $result) {
            $monthlyData[$result->month] = (float) $result->total;
        }

        return $monthlyData;
    }

    /**
     * Get recent invoices
     */
    public function getRecentInvoices(int $companyId, int $limit = 10, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->forCompany($companyId)
            ->with(['customer:id,name', 'branch:id,name'])
            ->select('id', 'invoice_number', 'customer_id', 'branch_id', 'total_amount', 'payment_status', 'created_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }


    /**
     * Get customer invoice history
     */
    public function getCustomerInvoices(int $customerId, int $limit = 20): LengthAwarePaginator
    {
        return $this->model
            ->where('customer_id', $customerId)
            ->with(['branch:id,name', 'items.product:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /**
     * Check if invoice can be deleted
     */
    public function canBeDeleted(int $id): bool
    {
        $invoice = $this->find($id);
        
        if (!$invoice) {
            return false;
        }

        // Cannot delete if has payments or is not in draft status
        return $invoice->status === 'draft' && $invoice->payments()->count() === 0;
    }

    /**
     * Check if invoice can be modified
     */
    public function canBeModified(int $id): bool
    {
        $invoice = $this->find($id);
        
        if (!$invoice) {
            return false;
        }

        // Can modify if in draft or pending status and has no payments
        return in_array($invoice->status, ['draft', 'pending']) && $invoice->payments()->count() === 0;
    }

    /**
     * Get invoices by date range
     */
    public function getByDateRange(int $companyId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->forCompany($companyId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->with(['customer', 'branch']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('invoice_date', 'desc')->get();
    }

    public function getPaidInvoicesWithoutPrintJobs(int $companyId, ?int $branchId = null): Collection
{
    $query = $this->model->newQuery()
        ->with(['customer', 'invoiceItems.product'])
        ->where('company_id', $companyId)
        ->where('payment_status', 'paid')
        ->whereDoesntHave('printJobs'); // Assuming you have this relationship

    if ($branchId) {
        $query->where('branch_id', $branchId);
    }

    return $query->orderBy('invoice_date', 'desc')
                ->limit(50) // Limit to recent 50 invoices
                ->get();
}

/**
 * Get invoices ready for print job creation
 */
public function getInvoicesReadyForProduction(int $companyId, ?int $branchId = null): Collection
{
    $query = $this->model->newQuery()
        ->with(['customer', 'invoiceItems.product', 'payments'])
        ->where('company_id', $companyId)
        ->where('payment_status', 'paid')
        ->where('status', 'confirmed')
        ->whereDoesntHave('printJobs');

    if ($branchId) {
        $query->where('branch_id', $branchId);
    }

    return $query->orderBy('due_date', 'asc')
                ->orderBy('total_amount', 'desc')
                ->get();
}

/**
 * Find invoice by invoice number
 */
public function findByInvoiceNumber(string $invoiceNumber): ?\App\Models\Invoice
{
    return $this->model->where('invoice_number', $invoiceNumber)->first();
}

/**
 * Get invoices by customer
 */
public function getByCustomer(int $customerId, int $limit = 10): Collection
{
    return $this->model->newQuery()
        ->with(['invoiceItems.product'])
        ->where('customer_id', $customerId)
        ->orderBy('invoice_date', 'desc')
        ->limit($limit)
        ->get();
}

/**
 * Get invoices requiring print jobs (dashboard widget)
 */
public function getInvoicesRequiringPrintJobs(int $companyId): array
{
    $invoices = $this->model->newQuery()
        ->with(['customer'])
        ->where('company_id', $companyId)
        ->where('payment_status', 'paid')
        ->whereDoesntHave('printJobs')
        ->orderBy('due_date', 'asc')
        ->get();

    return [
        'total' => $invoices->count(),
        'urgent' => $invoices->where('due_date', '<=', now()->addDays(2))->count(),
        'high_value' => $invoices->where('total_amount', '>', 25000)->count(),
        'recent' => $invoices->take(5)->toArray(),
    ];
}
public function findWithDetails(int $id): ?Invoice
{
    return $this->model
        ->with([
            'company',
            'branch',
            'customer',
            'creator',
            'items.product', // Make sure items are loaded with products
            'payments.receivedBy',
            'deliveries',
            'printJobs'
        ])
        ->find($id);
}
}