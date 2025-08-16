<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ExpenseRepository extends BaseRepository
{
    /**
     * Constructor
     */
    public function __construct(Expense $model)
    {
        parent::__construct($model);
    }

    /**
     * Get paginated expenses with filters
     */
    public function getPaginatedExpenses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Apply company filter
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        // Apply branch filter
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Apply category filter - FIXED: use 'category_id' not 'expense_category_id'
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply priority filter
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        // Apply payment method filter
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        // Apply amount range filter
        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (!empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('expense_number', 'like', "%{$search}%")
                  ->orWhere('vendor_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Apply employee filter - FIXED: use 'created_by' not 'submitted_by'
        if (!empty($filters['submitted_by']) || !empty($filters['created_by'])) {
            $userId = $filters['submitted_by'] ?? $filters['created_by'];
            $query->where('created_by', $userId);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'expense_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $allowedSortFields = ['expense_date', 'amount', 'created_at', 'status', 'expense_number'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'expense_date';
        }
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Secondary sort by created_at for consistency
        if ($sortBy !== 'created_at') {
            $query->orderBy('created_at', 'desc');
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'createdBy:id,name,email',  // FIXED: use 'createdBy' not 'submittedBy'
                'approvedBy:id,name,email'
            ])
            ->paginate($perPage);
    }

    /**
     * Get expenses for a specific company
     */
    public function getCompanyExpenses(int $companyId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('company_id', $companyId);

        // Apply branch filter
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        // Apply category filter - FIXED
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        // Apply amount range filter
        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (!empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('expense_number', 'like', "%{$search}%")
                  ->orWhere('vendor_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Apply employee filter - FIXED
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'createdBy:id,name,email',  // FIXED
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get expenses by category
     */
    public function getExpensesByCategory(int $companyId, int $categoryId, array $filters = []): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->where('category_id', $categoryId);  // FIXED

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        return $query
            ->with(['branch:id,name', 'createdBy:id,name'])  // FIXED
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    /**
     * Get expenses by employee
     */
    public function getExpensesByEmployee(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('created_by', $userId);  // FIXED

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get pending approval expenses
     */
    public function getPendingApprovalExpenses(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->where('status', 'pending_approval');  // FIXED: use correct status value

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'createdBy:id,name,email'  // FIXED
            ])
            ->orderBy('expense_date', 'asc')
            ->get();
    }

    /**
     * Get expenses requiring payment
     */
    public function getExpensesRequiringPayment(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->where('status', 'approved');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'createdBy:id,name,email',  // FIXED
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'asc')
            ->get();
    }

    /**
     * Get expense statistics for company
     */
    public function getExpenseStats(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->where('company_id', $companyId);
        
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Base stats
        $totalExpenses = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');
        
        // Status-based stats - FIXED: use correct status values
        $pendingCount = (clone $query)->where('status', 'pending_approval')->count();
        $approvedCount = (clone $query)->where('status', 'approved')->count();
        $rejectedCount = (clone $query)->where('status', 'rejected')->count();
        $paidCount = (clone $query)->where('status', 'paid')->count();
        
        // This month stats
        $thisMonthAmount = (clone $query)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');
            
        $thisMonthCount = (clone $query)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->count();

        return [
            'total_expenses' => $totalExpenses,
            'total_amount' => $totalAmount,
            'pending_approval' => $pendingCount,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'paid' => $paidCount,
            'this_month_amount' => $thisMonthAmount,
            'this_month_count' => $thisMonthCount,
            'formatted_total_amount' => 'Rs. ' . number_format($totalAmount, 2),
            'formatted_this_month_amount' => 'Rs. ' . number_format($thisMonthAmount, 2),
        ];
    }

    /**
     * Get recent expenses
     */
    public function getRecentExpenses(int $companyId, int $limit = 10, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->with([
                'category:id,name,code,color',
                'branch:id,name',
                'createdBy:id,name'  // FIXED
            ])
            ->select('id', 'expense_number', 'description', 'amount', 'expense_date', 'status', 'category_id', 'branch_id', 'created_by');  // FIXED

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Search expenses
     */
    public function searchExpenses(string $search, int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('vendor_name', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name',
                'createdBy:id,name'  // FIXED
            ])
            ->orderBy('expense_date', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Get expense details with relations
     */
    public function getExpenseDetails(int $expenseId): ?Expense
    {
        return $this->model
            ->with([
                'category',
                'branch',
                'createdBy',    // FIXED
                'approvedBy'
            ])
            ->find($expenseId);
    }

    /**
     * Bulk update expense status
     */
    public function bulkUpdateStatus(array $expenseIds, string $status, array $additionalData = []): bool
    {
        $updateData = array_merge(['status' => $status], $additionalData);
        
        return $this->model->whereIn('id', $expenseIds)->update($updateData);
    }

    /**
     * Get expenses for export
     */
    public function getExpensesForExport(int $companyId, array $filters = []): Collection
    {
        $query = $this->model->where('company_id', $companyId);

        // Apply filters
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);  // FIXED
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        return $query
            ->with([
                'category:id,name,code',
                'branch:id,name,code',
                'createdBy:id,name,email',  // FIXED
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'desc')
            ->get();
    }
}