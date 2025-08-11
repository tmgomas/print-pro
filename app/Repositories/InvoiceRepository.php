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

        // Handle per_page filter
        if (!empty($filters['per_page'])) {
            $perPage = min((int) $filters['per_page'], 100); // Max 100 per page
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
     * Get enhanced invoice statistics with daily/weekly/monthly tracking
     */
    public function getStats(int $companyId, ?int $branchId = null): array
    {
        // Create fresh base query for each calculation to avoid filter accumulation
        $createBaseQuery = function() use ($companyId, $branchId) {
            $query = $this->model->where('company_id', $companyId);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            return $query;
        };

        // Basic stats (existing functionality)
        $baseQuery = $createBaseQuery();
        $totalInvoices = $baseQuery->count();
        // dd  ($totalInvoices);
        $baseQuery = $createBaseQuery();
        $totalAmount = $baseQuery->sum('total_amount');
        
        $baseQuery = $createBaseQuery();
        $paidAmount = $baseQuery->where('payment_status', 'paid')->sum('total_amount');
        
        $baseQuery = $createBaseQuery();
        $outstandingAmount = $baseQuery->whereIn('payment_status', ['pending', 'partially_paid'])->sum('total_amount');
        
        $baseQuery = $createBaseQuery();
        $overdueCount = $baseQuery->where('due_date', '<', now())
                                 ->whereIn('payment_status', ['pending', 'partially_paid'])
                                 ->count();
        
        $baseQuery = $createBaseQuery();
        $overdueAmount = $baseQuery->where('due_date', '<', now())
                                  ->whereIn('payment_status', ['pending', 'partially_paid'])
                                  ->sum('total_amount');

        $baseQuery = $createBaseQuery();
        $paidCount = $baseQuery->where('payment_status', 'paid')->count();
        
        $baseQuery = $createBaseQuery();
        $pendingCount = $baseQuery->where('payment_status', 'pending')->count();

        // Enhanced daily/time-based stats  
        $today = now();
        $todayDateString = $today->format('Y-m-d'); // e.g., '2025-08-11'
        
        \Log::info('Daily Stats Debug', [
            'today' => $today,
            'todayDateString' => $todayDateString,
            'timezone' => config('app.timezone'),
            'server_time' => now(),
            'companyId' => $companyId,
            'branchId' => $branchId,
        ]);

        // Today's stats - using fresh queries
        $todayInvoicesQuery = $createBaseQuery()->whereDate('invoice_date', $todayDateString);
        $todayInvoicesCount = $todayInvoicesQuery->count();
        
        \Log::info('Today Invoices Query', [
            'sql' => $todayInvoicesQuery->toSql(),
            'bindings' => $todayInvoicesQuery->getBindings(),
            'count' => $todayInvoicesCount,
        ]);

        $todayIncomeQuery = $createBaseQuery()
            ->whereDate('invoice_date', $todayDateString)
            ->where('payment_status', 'paid');
            
        $todayIncome = $todayIncomeQuery->sum('total_amount');
        $todayInvoices = $todayInvoicesCount;

        \Log::info('Today Income Query', [
            'sql' => $todayIncomeQuery->toSql(),
            'bindings' => $todayIncomeQuery->getBindings(),
            'income' => $todayIncome,
        ]);

        \Log::info('Today Results', [
            'todayIncome' => $todayIncome,
            'todayInvoices' => $todayInvoices,
        ]);

        // Yesterday's stats for comparison
        $yesterdayDateString = now()->subDay()->format('Y-m-d');
        
        $yesterdayIncome = $createBaseQuery()
            ->whereDate('invoice_date', $yesterdayDateString)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $yesterdayInvoices = $createBaseQuery()
            ->whereDate('invoice_date', $yesterdayDateString)
            ->count();

        // Weekly stats (last 7 days including today)
        $weekStart = now()->subDays(6)->startOfDay(); // Last 7 days
        
        $weeklyIncome = $createBaseQuery()
            ->where('invoice_date', '>=', $weekStart)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $weeklyInvoices = $createBaseQuery()
            ->where('invoice_date', '>=', $weekStart)
            ->count();

        // Monthly stats (current month)
        $monthStart = now()->startOfMonth();
        
        $monthlyIncome = $createBaseQuery()
            ->where('invoice_date', '>=', $monthStart)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $monthlyInvoices = $createBaseQuery()
            ->where('invoice_date', '>=', $monthStart)
            ->count();

        // Status breakdown
        $statusBreakdown = [
            'draft' => $baseQuery->clone()->where('status', 'draft')->count(),
            'pending' => $baseQuery->clone()->where('status', 'pending')->count(),
            'processing' => $baseQuery->clone()->where('status', 'processing')->count(),
            'completed' => $baseQuery->clone()->where('status', 'completed')->count(),
            'cancelled' => $baseQuery->clone()->where('status', 'cancelled')->count(),
        ];

        // Calculate growth percentages
        $dailyGrowth = $yesterdayIncome > 0 
            ? (($todayIncome - $yesterdayIncome) / $yesterdayIncome) * 100 
            : ($todayIncome > 0 ? 100 : 0);

        // Recent activity (last 7 days)
        $recentActivity = $baseQuery->clone()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as amount')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return [
            // Basic stats (for backward compatibility)
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'overdue_count' => $overdueCount,
            'overdue_amount' => $overdueAmount,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount,

            // Enhanced daily stats
            'today_income' => $todayIncome,
            'today_invoices' => $todayInvoices,
            'yesterday_income' => $yesterdayIncome,
            'yesterday_invoices' => $yesterdayInvoices,
            'daily_growth_percentage' => round($dailyGrowth, 2),

            // Weekly stats
            'weekly_income' => $weeklyIncome,
            'weekly_invoices' => $weeklyInvoices,

            // Monthly stats
            'monthly_income' => $monthlyIncome,
            'monthly_invoices' => $monthlyInvoices,

            // Status breakdown
            'status_breakdown' => $statusBreakdown,

            // Additional metrics
            'average_invoice_amount' => $totalInvoices > 0 ? $totalAmount / $totalInvoices : 0,
            'collection_rate' => $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0,
            'overdue_rate' => $totalInvoices > 0 ? ($overdueCount / $totalInvoices) * 100 : 0,

            // Recent activity
            'recent_activity' => $recentActivity,

            // Formatted values for display
            'formatted' => [
                'total_amount' => 'Rs. ' . number_format($totalAmount, 2),
                'paid_amount' => 'Rs. ' . number_format($paidAmount, 2),
                'outstanding_amount' => 'Rs. ' . number_format($outstandingAmount, 2),
                'overdue_amount' => 'Rs. ' . number_format($overdueAmount, 2),
                'today_income' => 'Rs. ' . number_format($todayIncome, 2),
                'yesterday_income' => 'Rs. ' . number_format($yesterdayIncome, 2),
                'weekly_income' => 'Rs. ' . number_format($weeklyIncome, 2),
                'monthly_income' => 'Rs. ' . number_format($monthlyIncome, 2),
                'average_invoice_amount' => 'Rs. ' . number_format($totalInvoices > 0 ? $totalAmount / $totalInvoices : 0, 2),
            ],
        ];
    }

    /**
     * Get daily income report for a date range
     */
    public function getDailyIncomeReport(int $companyId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $query = $this->model->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->selectRaw('
                DATE(invoice_date) as date,
                COUNT(*) as invoice_count,
                SUM(total_amount) as total_income,
                AVG(total_amount) as average_amount,
                MIN(total_amount) as min_amount,
                MAX(total_amount) as max_amount
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'invoice_count' => (int) $item->invoice_count,
                    'total_income' => (float) $item->total_income,
                    'average_amount' => (float) $item->average_amount,
                    'min_amount' => (float) $item->min_amount,
                    'max_amount' => (float) $item->max_amount,
                    'formatted_income' => 'Rs. ' . number_format($item->total_income, 2),
                    'formatted_average' => 'Rs. ' . number_format($item->average_amount, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get weekly income summary
     */
    public function getWeeklyIncomeSummary(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->where('company_id', $companyId)
            ->where('payment_status', 'paid')
            ->where('invoice_date', '>=', now()->subWeeks(8)); // Last 8 weeks

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->selectRaw('
                YEAR(invoice_date) as year,
                WEEK(invoice_date) as week,
                COUNT(*) as invoice_count,
                SUM(total_amount) as total_income
            ')
            ->groupBy('year', 'week')
            ->orderBy('year', 'desc')
            ->orderBy('week', 'desc')
            ->limit(8)
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'week' => $item->week,
                    'invoice_count' => (int) $item->invoice_count,
                    'total_income' => (float) $item->total_income,
                    'formatted_income' => 'Rs. ' . number_format($item->total_income, 2),
                ];
            })
            ->toArray();
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

    /**
     * Get paid invoices without print jobs
     */
    public function getPaidInvoicesWithoutPrintJobs(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['customer', 'items.product'])
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
            ->with(['customer', 'items.product', 'payments'])
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
            ->with(['items.product'])
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

    /**
     * Find invoice with detailed relationships
     */
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