<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ExpenseCategoryController extends Controller
{

//  use AuthorizesRequests;
    public function __construct(
       private ExpenseCategoryService $categoryService
    ) {}    
    /**
     * Display listing of expense categories
     */
    public function index(Request $request): Response
    {
        
        $user = Auth::user();
        $companyId = $user->company_id;

        $categories = $this->categoryService->getCategoriesWithHierarchy($companyId);

        return Inertia::render('ExpenseCategories/Index', [
            'categories' => $categories,
            'stats' => $stats ?? [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'system_categories' => 0,
        'custom_categories' => 0,
        'with_expenses' => 0,
    ],
            'can' => [
                'create' => $user->can('create expense_categories', ExpenseCategory::class),
                'update' => $user->can('update expense_categories', ExpenseCategory::class),
                'delete' => $user->can('delete expense_categories', ExpenseCategory::class),
            ]
        ]);
    }

    /**
     * Show form for creating new category
     */
    public function create(): Response
    {
        $user = Auth::user();
        $parentCategories = $this->categoryService->getCompanyCategories($user->company_id)
                                                 ->where('parent_id', null);

        return Inertia::render('ExpenseCategories/Create', [
            'parentCategories' => $parentCategories
        ]);
    }

    /**
     * Store new expense category
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('expense_categories')->where('company_id', $user->company_id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $category = $this->categoryService->createCategory($validated, $user->company_id);

            return redirect()->route('expense-categories.index')
                           ->with('success', 'Expense category created successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])
                        ->withInput();
        }
    }

    /**
     * Display specified category
     */
    public function show(ExpenseCategory $expenseCategory): Response
    {
        $this->authorize('view', $expenseCategory);

        $expenseCategory->load(['parent', 'children', 'expenses' => function ($query) {
            $query->limit(10)->latest();
        }]);

        return Inertia::render('ExpenseCategories/Show', [
            'category' => $expenseCategory
        ]);
    }

    /**
     * Show form for editing category
     */
    public function edit(ExpenseCategory $expenseCategory): Response
    {
        $this->authorize('update', $expenseCategory);

        $parentCategories = $this->categoryService->getCompanyCategories($expenseCategory->company_id)
                                                 ->where('parent_id', null)
                                                 ->where('id', '!=', $expenseCategory->id);

        return Inertia::render('ExpenseCategories/Edit', [
            'category' => $expenseCategory,
            'parentCategories' => $parentCategories
        ]);
    }

    /**
     * Update expense category
     */
    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 
                Rule::unique('expense_categories')
                    ->where('company_id', $expenseCategory->company_id)
                    ->ignore($expenseCategory->id)
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:7'],
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $this->categoryService->updateCategory($expenseCategory, $validated);

            return redirect()->route('expense-categories.index')
                           ->with('success', 'Expense category updated successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])
                        ->withInput();
        }
    }

    /**
     * Delete expense category
     */
    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        try {
            $this->categoryService->deleteCategory($expenseCategory);

            return redirect()->route('expense-categories.index')
                           ->with('success', 'Expense category deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Search categories
     */
    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:255']
        ]);

        $user = Auth::user();
        $categories = $this->categoryService->searchCategories($request->search, $user->company_id);

        return response()->json($categories);
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:expense_categories,id']
        ]);

        try {
            $this->categoryService->reorderCategories($request->category_ids);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
