<?php

namespace App\Repositories;

use App\Models\ExpenseBudget;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class ExpenseBudgetRepository extends BaseRepository
{
    /**
     * Constructor - Inject ExpenseBudget model
     */
    public function __construct(ExpenseBudget $model)
    {
        parent::__construct($model);
    }

    /**
     * Get company budgets
     */
    public function getCompanyBudgets(int $companyId, array $filters = []): Collection
    {
        $query = $this->model->newQuery()
                     ->with(['branch', 'category'])
                     ->where('company_id', $companyId);

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Get current period budgets
     */
    public function getCurrentPeriodBudgets(int $companyId, int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
                     ->with(['category', 'branch'])
                     ->where('company_id', $companyId)
                     ->where('status', 'active')
                     ->where('budget_year', now()->year);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query->get();
    }

    /**
     * Find specific budget
     */
    public function findBudget(int $companyId, int $categoryId, string $period, int $year, int $branchId = null): ?ExpenseBudget
    {
        $query = $this->model->newQuery()
                     ->where('company_id', $companyId)
                     ->where('category_id', $categoryId)
                     ->where('budget_period', $period)
                     ->where('budget_year', $year);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $query->whereNull('branch_id');
        }

        return $query->first();
    }

    /**
     * Update budget spent amount
     */
    public function updateBudgetSpentAmount(ExpenseBudget $budget): bool
    {
        $spentAmount = Expense::where('expense_category_id', $budget->category_id)
                             ->where('company_id', $budget->company_id)
                             ->when($budget->branch_id, fn($q) => $q->where('branch_id', $budget->branch_id))
                             ->whereIn('approval_status', ['approved'])
                             ->whereIn('payment_status', ['paid'])
                             ->whereBetween('expense_date', [$budget->start_date, $budget->end_date])
                             ->sum('amount');

        return $budget->update([
            'spent_amount' => $spentAmount,
            'remaining_amount' => max(0, $budget->budget_amount - $spentAmount),
        ]);
    }

    /**
     * Update category spending
     */
    public function updateCategorySpending(int $categoryId, int $companyId, int $branchId = null): void
    {
        $budgets = $this->model->newQuery()
                       ->where('category_id', $categoryId)
                       ->where('company_id', $companyId)
                       ->where(function ($query) use ($branchId) {
                           $query->whereNull('branch_id');
                           if ($branchId) {
                               $query->orWhere('branch_id', $branchId);
                           }
                       })
                       ->where('budget_year', now()->year)
                       ->where('status', 'active')
                       ->get();

        foreach ($budgets as $budget) {
            $this->updateBudgetSpentAmount($budget);
        }
    }

    /**
     * Apply specific filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                $this->applyFilter($query, $key, $value);
            }
        }
    }

    /**
     * Apply specific filter
     */
    protected function applyFilter(Builder $query, string $key, $value): void
    {
        match($key) {
            'company_id' => $query->where('company_id', $value),
            'branch_id' => $query->where('branch_id', $value),
            'category_id' => $query->where('category_id', $value),
            'status' => $query->where('status', $value),
            'budget_period' => $query->where('budget_period', $value),
            'budget_year' => $query->where('budget_year', $value),
            default => null
        };
    }
}