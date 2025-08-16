<?php

namespace App\Services;

use App\Models\ExpenseBudget;
use App\Repositories\ExpenseBudgetRepository;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class ExpenseBudgetService extends BaseService
{
    public function __construct(
        private ExpenseBudgetRepository $budgetRepository
    ) {
        $this->repository = $budgetRepository;
    }

    /**
     * Create new budget
     */
    public function createBudget(array $data, int $companyId): ExpenseBudget
    {
        try {
            $data['company_id'] = $companyId;
            $data['created_by'] = auth()->id();

            // Check for existing budget
            $existing = $this->budgetRepository->findBudget(
                $companyId,
                $data['category_id'],
                $data['budget_period'],
                $data['budget_year'],
                $data['branch_id'] ?? null
            );

            $this->validateBusinessRules([
                'budget_not_exists' => !$existing
            ]);

            return $this->createWithTransaction($data, function ($budget) {
                $this->budgetRepository->updateBudgetSpentAmount($budget);
            });

        } catch (\Exception $e) {
            $this->handleException($e, 'create budget');
            throw $e;
        }
    }

    /**
     * Update budget
     */
    public function updateBudget(ExpenseBudget $budget, array $data): bool
    {
        try {
            return $this->updateWithTransaction($budget, $data, function ($budget) {
                $this->budgetRepository->updateBudgetSpentAmount($budget);
            });

        } catch (\Exception $e) {
            $this->handleException($e, 'update budget', $budget);
            throw $e;
        }
    }

    /**
     * Get company budgets
     */
    public function getCompanyBudgets(int $companyId, array $filters = []): Collection
    {
        return $this->budgetRepository->getCompanyBudgets($companyId, $filters);
    }

    /**
     * Get current period budgets
     */
    public function getCurrentPeriodBudgets(int $companyId, int $branchId = null): Collection
    {
        return $this->budgetRepository->getCurrentPeriodBudgets($companyId, $branchId);
    }

    /**
     * Update all budgets spending amounts
     */
    public function updateAllBudgetsSpending(int $companyId): void
    {
        $budgets = $this->budgetRepository->getCompanyBudgets($companyId, ['status' => 'active']);
        
        foreach ($budgets as $budget) {
            $this->budgetRepository->updateBudgetSpentAmount($budget);
        }
    }

    /**
     * Extend budget amount
     */
    public function extendBudget(ExpenseBudget $budget, float $additionalAmount, string $reason = null): bool
    {
        try {
            $newBudgetAmount = $budget->budget_amount + $additionalAmount;
            $newRemainingAmount = $newBudgetAmount - $budget->spent_amount;
            
            $metadata = $budget->metadata ?? [];
            $metadata['extensions'] = $metadata['extensions'] ?? [];
            $metadata['extensions'][] = [
                'amount' => $additionalAmount,
                'reason' => $reason,
                'extended_at' => now()->toISOString(),
                'extended_by' => auth()->id(),
            ];
            
            return $this->updateBudget($budget, [
                'budget_amount' => $newBudgetAmount,
                'remaining_amount' => $newRemainingAmount,
                'metadata' => $metadata,
                'status' => $budget->spent_amount <= $newBudgetAmount ? 'active' : 'exceeded',
            ]);

        } catch (\Exception $e) {
            $this->handleException($e, 'extend budget', $budget);
            throw $e;
        }
    }
}