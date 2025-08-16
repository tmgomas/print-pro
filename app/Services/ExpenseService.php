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
        private NotificationService $notificationService
    ) {
        $this->repository = $expenseRepository;
    }

    /**
     * Create new expense
     */
    public function createExpense(array $data, User $user): Expense
    {
        try {
            // Generate expense number
            $data['expense_number'] = Expense::generateExpenseNumber($user->company_id, $data['branch_id']);
            $data['company_id'] = $user->company_id;
            $data['created_by'] = $user->id;

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

                // Update budget if expense is approved
                if (in_array($expense->status, ['approved', 'paid'])) {
                    $this->updateBudgetSpending($expense);
                }
            });

        } catch (\Exception $e) {
            $this->handleException($e, 'create expense');
            throw $e;
        }
    }

    /**
     * Update expense
     */
    public function updateExpense(Expense $expense, array $data, User $user): bool
    {
        try {
            $this->validateBusinessRules([
                'expense_editable' => $expense->can_edit,
                'user_can_edit' => $user->can('update', $expense)
            ]);

            $oldCategoryId = $expense->category_id;
            $oldAmount = $expense->amount;
            $oldStatus = $expense->status;

            return $this->updateWithTransaction($expense, $data, function ($expense) use ($oldCategoryId, $oldAmount, $oldStatus) {
                // Update budget spending if relevant fields changed
                if ($oldCategoryId !== $expense->category_id || 
                    $oldAmount !== $expense->amount || 
                    $oldStatus !== $expense->status) {
                    
                    $this->updateBudgetSpending($expense, $oldCategoryId, $oldAmount);
                }
            });

        } catch (\Exception $e) {
            $this->handleException($e, 'update expense', $expense);
            throw $e;
        }
    }

    /**
     * Submit expense for approval
     */
    public function submitForApproval(Expense $expense, string $notes = null): bool
    {
        try {
            $this->validateBusinessRules([
                'expense_is_draft' => $expense->status === 'draft'
            ]);

            $submitted = $expense->submitForApproval($notes);

            if ($submitted) {
                $this->notifyApprovers($expense);
            }

            return $submitted;

        } catch (\Exception $e) {
            $this->handleException($e, 'submit for approval', $expense);
            throw $e;
        }
    }

    /**
     * Approve expense
     */
    public function approveExpense(Expense $expense, User $approver, string $notes = null): bool
    {
        try {
            $this->validateBusinessRules([
                'expense_pending' => $expense->status === 'pending_approval',
                'user_can_approve' => $approver->can('approve', $expense)
            ]);

            return $this->updateWithTransaction($expense, [], function ($expense) use ($approver, $notes) {
                $expense->approve($approver, $notes);
                $this->updateBudgetSpending($expense);
                $this->checkBudgetAlerts($expense);
                $this->notificationService->notifyExpenseApproved($expense);
            });

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
            $this->validateBusinessRules([
                'expense_pending' => $expense->status === 'pending_approval',
                'user_can_approve' => $approver->can('approve', $expense),
                'reason_provided' => !empty($reason)
            ]);

            $rejected = $expense->reject($approver, $reason);

            if ($rejected) {
                $this->notificationService->notifyExpenseRejected($expense);
            }

            return $rejected;

        } catch (\Exception $e) {
            $this->handleException($e, 'reject expense', $expense);
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

    /**
     * Private helper methods
     */
    private function calculateNextDueDate(Carbon $currentDate, string $period): Carbon
    {
        return match($period) {
            'weekly' => $currentDate->copy()->addWeek(),
            'monthly' => $currentDate->copy()->addMonth(),
            'quarterly' => $currentDate->copy()->addQuarter(),
            'yearly' => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth()
        };
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

    private function generateNextRecurringExpense(Expense $expense): ?Expense
    {
        if (!$expense->is_recurring || !$expense->next_due_date) {
            return null;
        }

        try {
            $nextExpense = $expense->replicate([
                'expense_number', 'approved_by', 'approved_at', 'paid_at',
                'approval_notes', 'rejection_reason'
            ]);

            $nextExpense->status = 'draft';
            $nextExpense->expense_date = $expense->next_due_date;
            $nextExpense->expense_number = Expense::generateExpenseNumber($expense->company_id, $expense->branch_id);
            $nextExpense->next_due_date = $this->calculateNextDueDate($expense->next_due_date, $expense->recurring_period);
            
            $nextExpense->save();

            return $nextExpense;

        } catch (\Exception $e) {
            $this->handleException($e, 'generate recurring expense', $expense);
            return null;
        }
    }
}