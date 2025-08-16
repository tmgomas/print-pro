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
     * Get model instance
     */
    protected function getModel(): Model
    {
        return new Expense();
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

        // Apply category filter
        if (!empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply approval status filter
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        // Apply payment status filter
        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
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

        // Apply employee filter
        if (!empty($filters['submitted_by'])) {
            $query->where('submitted_by', $filters['submitted_by']);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'submittedBy:id,name,email',
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
            ->where('expense_category_id', $categoryId);

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        return $query
            ->with(['branch:id,name', 'submittedBy:id,name'])
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    /**
     * Get expenses by employee
     */
    public function getExpensesByEmployee(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->where('submitted_by', $userId);

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply approval status filter
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
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
            ->where('approval_status', 'pending');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'submittedBy:id,name,email'
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
            ->where('approval_status', 'approved')
            ->where('payment_status', 'pending');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'submittedBy:id,name,email',
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'asc')
            ->get();
    }

    /**
     * Get overdue expenses
     */
    public function getOverdueExpenses(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->with([
                'category:id,name,code,color',
                'branch:id,name,code',
                'submittedBy:id,name,email'
            ])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Generate unique expense number
     */
    public function generateExpenseNumber(int $branchId): string
    {
        $branch = \App\Models\Branch::findOrFail($branchId);
        
        $lastExpense = $this->model
            ->where('branch_id', $branchId)
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNumber = $lastExpense ? intval(substr($lastExpense->expense_number, -6)) + 1 : 1;
        
        return 'EXP-' . $branch->code . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
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
        
        // Status-based stats
        $pendingCount = (clone $query)->where('approval_status', 'pending')->count();
        $approvedCount = (clone $query)->where('approval_status', 'approved')->count();
        $rejectedCount = (clone $query)->where('approval_status', 'rejected')->count();
        
        // Payment status stats
        $paidCount = (clone $query)->where('payment_status', 'paid')->count();
        $unpaidCount = (clone $query)->where('payment_status', 'pending')->count();
        
        // This month stats
        $thisMonthAmount = (clone $query)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');
            
        $thisMonthCount = (clone $query)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->count();

        // Overdue count
        $overdueCount = (clone $query)
            ->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid')
            ->count();

        return [
            'total_expenses' => $totalExpenses,
            'total_amount' => $totalAmount,
            'pending_approval' => $pendingCount,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'paid' => $paidCount,
            'unpaid' => $unpaidCount,
            'overdue' => $overdueCount,
            'this_month_amount' => $thisMonthAmount,
            'this_month_count' => $thisMonthCount,
            'formatted_total_amount' => 'Rs. ' . number_format($totalAmount, 2),
            'formatted_this_month_amount' => 'Rs. ' . number_format($thisMonthAmount, 2),
        ];
    }

    /**
     * Get monthly expense report
     */
    public function getMonthlyExpenseReport(int $companyId, int $year, ?int $branchId = null): array
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->whereYear('expense_date', $year);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $results = $query->selectRaw('
                MONTH(expense_date) as month,
                COUNT(*) as expense_count,
                SUM(amount) as total_amount
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyData = array_fill(1, 12, ['expense_count' => 0, 'total_amount' => 0]);
        
        foreach ($results as $result) {
            $monthlyData[$result->month] = [
                'expense_count' => (int) $result->expense_count,
                'total_amount' => (float) $result->total_amount,
                'formatted_amount' => 'Rs. ' . number_format($result->total_amount, 2),
            ];
        }

        return $monthlyData;
    }

    /**
     * Get category-wise expense breakdown
     */
    public function getCategoryWiseExpenses(int $companyId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('
                expense_categories.name as category_name,
                expense_categories.code as category_code,
                expense_categories.color as category_color,
                COUNT(expenses.id) as expense_count,
                SUM(expenses.amount) as total_amount,
                AVG(expenses.amount) as average_amount
            ')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.code', 'expense_categories.color')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) {
                return [
                    'category_name' => $item->category_name,
                    'category_code' => $item->category_code,
                    'category_color' => $item->category_color,
                    'expense_count' => (int) $item->expense_count,
                    'total_amount' => (float) $item->total_amount,
                    'average_amount' => (float) $item->average_amount,
                    'formatted_total' => 'Rs. ' . number_format($item->total_amount, 2),
                    'formatted_average' => 'Rs. ' . number_format($item->average_amount, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get top spenders report
     */
    public function getTopSpendersReport(int $companyId, Carbon $startDate, Carbon $endDate, ?int $branchId = null, int $limit = 10): array
    {
        $query = $this->model
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->join('users', 'expenses.submitted_by', '=', 'users.id')
            ->selectRaw('
                users.name as employee_name,
                users.email as employee_email,
                COUNT(expenses.id) as expense_count,
                SUM(expenses.amount) as total_amount,
                AVG(expenses.amount) as average_amount
            ')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'employee_name' => $item->employee_name,
                    'employee_email' => $item->employee_email,
                    'expense_count' => (int) $item->expense_count,
                    'total_amount' => (float) $item->total_amount,
                    'average_amount' => (float) $item->average_amount,
                    'formatted_total' => 'Rs. ' . number_format($item->total_amount, 2),
                    'formatted_average' => 'Rs. ' . number_format($item->average_amount, 2),
                ];
            })
            ->toArray();
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
                'submittedBy:id,name'
            ])
            ->select('id', 'expense_number', 'description', 'amount', 'expense_date', 'approval_status', 'payment_status', 'expense_category_id', 'branch_id', 'submitted_by');

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
                'submittedBy:id,name'
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
                'submittedBy',
                'approvedBy',
                'attachments'
            ])
            ->find($expenseId);
    }

    /**
     * Bulk update expense status
     */
    public function bulkUpdateStatus(array $expenseIds, string $status, array $additionalData = []): bool
    {
        $updateData = array_merge(['approval_status' => $status], $additionalData);
        
        return $this->model->whereIn('id', $expenseIds)->update($updateData);
    }

    /**
     * Get expenses for export
     */
    public function getExpensesForExport(int $companyId, array $filters = []): Collection
    {
        $query = $this->model->where('company_id', $companyId);

        // Apply filters (same as getCompanyExpenses but without pagination)
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('expense_category_id', $filters['category_id']);
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
                'submittedBy:id,name,email',
                'approvedBy:id,name,email'
            ])
            ->orderBy('expense_date', 'desc')
            ->get();
    }
}