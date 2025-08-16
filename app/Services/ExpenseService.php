<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Repositories\ExpenseRepository;
use App\Repositories\ExpenseBudgetRepository;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ExpenseService extends BaseService
{
    public function __construct(
        private ExpenseRepository $expenseRepository,
        private ExpenseBudgetRepository $budgetRepository,
       
    ) {
        $this->repository = $expenseRepository;
    }

    // Add these methods to ExpenseService class:

/**
 * Submit expense for approval
 */
public function submitForApproval(Expense $expense, ?string $notes = null): bool
{
    try {
        if ($expense->status !== 'draft') {
            throw new \InvalidArgumentException('Only draft expenses can be submitted for approval');
        }

        return $expense->update([
            'status' => 'pending_approval',
            'notes' => $notes ?? $expense->notes,
        ]);

    } catch (\Exception $e) {
        $this->handleException($e, 'submit for approval', $expense);
        throw $e;
    }
}

/**
 * Approve expense
 */
public function approveExpense(Expense $expense, User $approver, ?string $notes = null): bool
{
    try {
        if ($expense->status !== 'pending_approval') {
            throw new \InvalidArgumentException('Only pending expenses can be approved');
        }

        $updated = $expense->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);

        if ($updated) {
            // Update budget spending if needed
            $this->budgetRepository->updateCategorySpending(
                $expense->category_id,
                $expense->company_id,
                $expense->branch_id
            );
        }

        return $updated;

    } catch (\Exception $e) {
        $this->handleException($e, 'approve expense', $expense);
        throw $e;
    }
}

/**
 * Reject expense
 */
public function rejectExpense(Expense $expense, User $approver, string $reason): bool
{
    try {
        if ($expense->status !== 'pending_approval') {
            throw new \InvalidArgumentException('Only pending expenses can be rejected');
        }

        return $expense->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

    } catch (\Exception $e) {
        $this->handleException($e, 'reject expense', $expense);
        throw $e;
    }
}

/**
 * Update expense
 */
public function updateExpense(Expense $expense, array $data, User $user): bool
{
    try {
        if (!in_array($expense->status, ['draft', 'rejected'])) {
            throw new \InvalidArgumentException('Only draft or rejected expenses can be updated');
        }

        // Reset status to draft if it was rejected
        if ($expense->status === 'rejected') {
            $data['status'] = 'draft';
            $data['rejection_reason'] = null;
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }

        return $this->updateWithTransaction($expense, $data);

    } catch (\Exception $e) {
        $this->handleException($e, 'update expense', $expense);
        throw $e;
    }
}

/**
 * Calculate next due date for recurring expenses
 */
private function calculateNextDueDate(Carbon $currentDate, string $period): Carbon
{
    return match($period) {
        'weekly' => $currentDate->addWeek(),
        'monthly' => $currentDate->addMonth(),
        'quarterly' => $currentDate->addQuarter(),
        'yearly' => $currentDate->addYear(),
        default => $currentDate->addMonth(),
    };
}

/**
 * Generate next recurring expense
 */
private function generateNextRecurringExpense(Expense $expense): ?Expense
{
    if (!$expense->is_recurring || !$expense->next_due_date) {
        return null;
    }

    try {
        $nextExpenseData = [
            'company_id' => $expense->company_id,
            'branch_id' => $expense->branch_id,
            'category_id' => $expense->category_id,
            'created_by' => $expense->created_by,
            'expense_number' => $this->expenseRepository->generateExpenseNumber($expense->branch_id),
            'expense_date' => $expense->next_due_date,
            'amount' => $expense->amount,
            'description' => $expense->description . ' (Recurring)',
            'vendor_name' => $expense->vendor_name,
            'payment_method' => $expense->payment_method,
            'priority' => $expense->priority,
            'is_recurring' => true,
            'recurring_period' => $expense->recurring_period,
            'next_due_date' => $this->calculateNextDueDate(
                Carbon::parse($expense->next_due_date),
                $expense->recurring_period
            ),
            'status' => 'draft',
        ];

        return $this->createWithTransaction($nextExpenseData);

    } catch (\Exception $e) {
        $this->handleException($e, 'generate recurring expense', $expense);
        return null;
    }
}
    /**
     * Create new expense
     */
// app/Services/ExpenseService.php file එකේ createExpense method එක modify කරන්න:

// app/Services/ExpenseService.php
public function createExpense(array $data, User $user): Expense
{
    try {
        // Generate expense number
        $data['expense_number'] = Expense::generateExpenseNumber($user->company_id, $data['branch_id']);
        $data['company_id'] = $user->company_id;
        $data['created_by'] = $user->id;
        
        // Set default status to draft (explicit)
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }

        // Calculate next due date for recurring expenses
        if ($data['is_recurring'] && isset($data['recurring_period'])) {
            $data['next_due_date'] = $this->calculateNextDueDate(
                Carbon::parse($data['expense_date']),
                $data['recurring_period']
            );
        }

        return $this->createWithTransaction($data, function ($expense) use ($data) {
            // Submit for approval if requested
            if (isset($data['submit_for_approval']) && $data['submit_for_approval']) {
                $this->submitForApproval($expense, $data['notes'] ?? null);
            }
        });

    } catch (\Exception $e) {
        $this->handleException($e, 'create expense');
        throw $e;
    }
}


    /**
     * Mark expense as paid
     */
    public function markAsPaid(Expense $expense, string $notes = null): bool
    {
        try {
            $this->validateBusinessRules([
                'expense_approved' => $expense->status === 'approved'
            ]);

            $marked = $expense->markAsPaid($notes);

            if ($marked && $expense->is_recurring) {
                $this->generateNextRecurringExpense($expense);
            }

            return $marked;

        } catch (\Exception $e) {
            $this->handleException($e, 'mark as paid', $expense);
            throw $e;
        }
    }

    /**
     * Get paginated expenses
     */
    public function getPaginatedExpenses(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getPaginatedExpenses($filters, $perPage);
    }

    /**
     * Get expenses for approval
     */
    public function getExpensesForApproval(User $user): Collection
    {
        return $this->expenseRepository->getExpensesForApproval($user);
    }

    /**
     * Get expense statistics
     */
    public function getExpenseStats(int $companyId, int $branchId = null): array
    {
        return $this->expenseRepository->getExpenseStats($companyId, $branchId);
    }

    /**
     * Bulk approve expenses
     */
    public function bulkApproveExpenses(array $expenseIds, User $approver, string $notes = null): array
    {
        $results = ['approved' => 0, 'failed' => 0, 'errors' => []];

        $expenses = Expense::whereIn('id', $expenseIds)
                          ->where('status', 'pending_approval')
                          ->get();

        foreach ($expenses as $expense) {
            try {
                if ($this->approveExpense($expense, $approver, $notes)) {
                    $results['approved']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Expense {$expense->expense_number}: " . $e->getMessage();
            }
        }

        return $results;
    }

    private function updateBudgetSpending(Expense $expense, int $oldCategoryId = null, float $oldAmount = null): void
    {
        if (!in_array($expense->status, ['approved', 'paid'])) {
            return;
        }

        // Update current category budget
        $this->budgetRepository->updateCategorySpending($expense->category_id, $expense->company_id, $expense->branch_id);

        // Update old category budget if category changed
        if ($oldCategoryId && $oldCategoryId !== $expense->category_id) {
            $this->budgetRepository->updateCategorySpending($oldCategoryId, $expense->company_id, $expense->branch_id);
        }
    }

    private function checkBudgetAlerts(Expense $expense): void
    {
        $budgets = $this->budgetRepository->getCurrentPeriodBudgets($expense->company_id, $expense->branch_id);

        foreach ($budgets as $budget) {
            if ($budget->category_id === $expense->category_id) {
                $budget->updateSpentAmount();
                
                if ($budget->spent_percentage >= $budget->alert_threshold) {
                    $this->notificationService->notifyBudgetAlert($budget);
                }
            }
        }
    }

    private function notifyApprovers(Expense $expense): void
    {
        $approvers = User::where('company_id', $expense->company_id)
                        ->whereHas('roles', function ($query) {
                            $query->whereIn('name', ['company_admin', 'branch_manager']);
                        })
                        ->when($expense->branch_id, function ($query, $branchId) {
                            $query->where(function ($q) use ($branchId) {
                                $q->whereHas('roles', function ($roleQuery) {
                                    $roleQuery->where('name', 'company_admin');
                                })->orWhere('branch_id', $branchId);
                            });
                        })
                        ->get();

        foreach ($approvers as $approver) {
            $this->notificationService->notifyExpensePendingApproval($expense, $approver);
        }
    }
// app/Services/ExpenseService.php
// Add this method to ExpenseService class:

/**
 * Validate business rules for operations
 */
private function validateBusinessRules(array $rules): void
{
    foreach ($rules as $rule => $condition) {
        if (!$condition) {
            throw new \InvalidArgumentException("Business rule validation failed: {$rule}");
        }
    }
}
}