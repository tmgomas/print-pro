<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Branch;
use App\Services\ExpenseService;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseService $expenseService,
        private ExpenseCategoryService $categoryService
    ) {}

    /**
     * Display listing of expenses
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // Apply user role-based filtering
        $filters = $request->only([
            'status', 'category_id', 'priority', 'payment_method',
            'date_from', 'date_to', 'amount_min', 'amount_max',
            'search', 'created_by', 'branch_id', 'sort_by', 'sort_order'
        ]);

        $filters['company_id'] = $companyId;

        // Branch filtering based on user role
        if ($user->hasRole(['cashier', 'production_staff', 'delivery_coordinator'])) {
            $filters['branch_id'] = $branchId;
        }

        $expenses = $this->expenseService->getPaginatedExpenses($filters, 15);

        // Get filter options
        $categories = $this->categoryService->getCompanyCategories($companyId);
        
        $branches = $user->hasRole(['super_admin', 'company_admin']) 
                   ? Branch::forCompany($companyId)->active()->get(['id', 'branch_name'])
                   : collect();

        $stats = $this->expenseService->getExpenseStats($companyId, $branchId);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'branches' => $branches,
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => Expense::getStatusOptions(),
            'priorityOptions' => Expense::getPriorityOptions(),
            'paymentMethodOptions' => Expense::getPaymentMethodOptions(),
            'can' => [
                'create' => $user->can('create', Expense::class),
                'approve' => $user->can('approve', Expense::class),
            ]
        ]);
    }

    /**
     * Show form for creating new expense
     */
    public function create(): Response
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $categories = $this->categoryService->getCompanyCategories($companyId);

        $branches = $user->hasRole(['super_admin', 'company_admin'])
                   ? Branch::forCompany($companyId)->active()->get()
                   : collect([$user->branch]);
        
        return Inertia::render('Expenses/Create', [
            'categories' => $categories,
            'branches' => $branches,
            'statusOptions' => Expense::getStatusOptions(),
            'priorityOptions' => Expense::getPriorityOptions(),
            'paymentMethodOptions' => Expense::getPaymentMethodOptions(),
            'recurringPeriodOptions' => ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'],
        ]);
    }

    /**
     * Store new expense
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'category_id' => ['required', 'exists:expense_categories,id'], // මේක expense_category_id වෙන්න ඕනෙ නම් change කරන්න
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:1000'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_address' => ['nullable', 'string', 'max:500'],
            'vendor_phone' => ['nullable', 'string', 'max:20'],
            'vendor_email' => ['nullable', 'email', 'max:255'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::getPaymentMethodOptions()))],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(array_keys(Expense::getPriorityOptions()))],
            'is_recurring' => ['boolean'],
            'recurring_period' => ['nullable', 'required_if:is_recurring,true'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt_files' => ['nullable', 'array', 'max:5'],
            'receipt_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'submit_for_approval' => ['boolean'],
        ]);

        try {
            $expense = $this->expenseService->createExpense($validated, $user);

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense created successfully.');
                           
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])
                        ->withInput();
        }
    }

    /**
     * Display specified expense
     */
    // app/Http/Controllers/ExpenseController.php
// In show method:
public function show(Expense $expense): Response
{
   

    $expense->load(['category', 'branch', 'createdBy', 'approvedBy']);

    return Inertia::render('Expenses/Show', [
        'expense' => $expense,
        'can' => [
            'update' => auth()->user()->can('update', $expense),
            'delete' => auth()->user()->can('delete', $expense),
            'approve' => auth()->user()->can('approve', $expense),
        ]
    ]);
}

    /**
     * Show form for editing expense
     */
    public function edit(Expense $expense): Response
    {
       

        $categories = $this->categoryService->getCompanyCategories($expense->company_id);
        
        $branches = auth()->user()->hasRole(['super_admin', 'company_admin'])
                   ? Branch::forCompany($expense->company_id)->active()->get()
                   : collect([$expense->branch]);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories,
            'branches' => $branches,
            'statusOptions' => Expense::getStatusOptions(),
            'priorityOptions' => Expense::getPriorityOptions(),
            'paymentMethodOptions' => Expense::getPaymentMethodOptions(),
        ]);
    }

    /**
     * Update expense
     */
    public function update(Request $request, Expense $expense): RedirectResponse
    {
      

        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'category_id' => ['required', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:1000'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::getPaymentMethodOptions()))],
            'priority' => ['required', Rule::in(array_keys(Expense::getPriorityOptions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->expenseService->updateExpense($expense, $validated, auth()->user());

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense updated successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])
                        ->withInput();
        }
    }

    /**
     * Submit expense for approval
     */
    public function submitForApproval(Request $request, Expense $expense): RedirectResponse
    {
        

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $this->expenseService->submitForApproval($expense, $validated['notes'] ?? null);

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense submitted for approval.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Approve expense
     */
    public function approve(Request $request, Expense $expense): RedirectResponse
    {
     

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $this->expenseService->approveExpense($expense, auth()->user(), $validated['approval_notes'] ?? null);

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense approved successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject expense
     */
    public function reject(Request $request, Expense $expense): RedirectResponse
    {
     
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000']
        ]);

        try {
            $this->expenseService->rejectExpense($expense, auth()->user(), $validated['rejection_reason']);

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense rejected.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk approve expenses
     */
    public function bulkApprove(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'expense_ids' => ['required', 'array'],
            'expense_ids.*' => ['integer', 'exists:expenses,id'],
            'approval_notes' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $results = $this->expenseService->bulkApproveExpenses(
                $validated['expense_ids'], 
                auth()->user(), 
                $validated['approval_notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "Approved {$results['approved']} expenses. {$results['failed']} failed.",
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
    // app/Http/Controllers/ExpenseController.php
// Add this method to your ExpenseController class:

/**
 * Mark expense as paid
 */
public function markAsPaid(Request $request, Expense $expense): RedirectResponse
{
    

    $validated = $request->validate([
        'payment_notes' => ['nullable', 'string', 'max:1000'],
        'payment_reference' => ['nullable', 'string', 'max:255'],
        'payment_method' => ['nullable', 'string', 'max:50']
    ]);

    try {
        $success = $this->expenseService->markAsPaid(
            $expense, 
            $validated['payment_notes'] ?? null
        );

        if ($success) {
            // Update additional payment details if provided
            if (!empty($validated['payment_reference']) || !empty($validated['payment_method'])) {
                $expense->update([
                    'payment_reference' => $validated['payment_reference'] ?? $expense->payment_reference,
                    'payment_method' => $validated['payment_method'] ?? $expense->payment_method,
                ]);
            }

            return redirect()->route('expenses.show', $expense)
                           ->with('success', 'Expense marked as paid successfully.');
        } else {
            return back()->withErrors(['error' => 'Failed to mark expense as paid.']);
        }

    } catch (\Exception $e) {
        return back()->withErrors(['error' => $e->getMessage()]);
    }
}
}