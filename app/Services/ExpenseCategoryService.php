<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Repositories\ExpenseCategoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseCategoryService extends BaseService
{
    public function __construct(
        private ExpenseCategoryRepository $categoryRepository
    ) {
        $this->repository = $categoryRepository;
    }

    /**
     * Create new expense category
     */
    public function createCategory(array $data, int $companyId): ExpenseCategory
    {
        try {
            // Generate code if not provided
            if (!isset($data['code'])) {
                $data['code'] = ExpenseCategory::generateCode($data['name'], $companyId);
            }

            $data['company_id'] = $companyId;

            // Use the parent's create method which already includes transaction
            return $this->create($data);

        } catch (\Exception $e) {
            $this->handleException($e, 'create category');
            throw $e;
        }
    }

    /**
     * Update expense category
     */
    public function updateCategory(ExpenseCategory $category, array $data): bool
    {
        try {
            $this->validateBusinessRules([
                'category_not_system' => !$category->is_system_category || auth()->user()->hasRole('super_admin'),
                'category_not_in_use' => !isset($data['status']) || $data['status'] === 'active' || !$category->has_expenses
            ]);

            // Use the parent's update method which already includes transaction
            return $this->update($category->id, $data);

        } catch (\Exception $e) {
            $this->handleException($e, 'update category', $category);
            throw $e;
        }
    }

    /**
     * Delete expense category
     */
    public function deleteCategory(ExpenseCategory $category): bool
    {
        try {
            $this->validateBusinessRules([
                'category_not_system' => !$category->is_system_category,
                'category_not_in_use' => !$category->has_expenses,
                'category_no_children' => !$category->is_parent
            ]);

            // Use the parent's delete method which already includes transaction
            return $this->delete($category->id);

        } catch (\Exception $e) {
            $this->handleException($e, 'delete category', $category);
            throw $e;
        }
    }

    /**
     * Get categories for company
     */
    public function getCompanyCategories(int $companyId, bool $activeOnly = true): Collection
    {
        return $this->categoryRepository->getCompanyCategories($companyId, $activeOnly);
    }

    /**
     * Get categories with hierarchy
     */
    public function getCategoriesWithHierarchy(int $companyId): Collection
    {
        return $this->categoryRepository->getCategoriesWithHierarchy($companyId);
    }

    /**
     * Setup default categories for company
     */
    public function setupDefaultCategories(int $companyId): void
    {
        try {
            $this->categoryRepository->createSystemCategories($companyId);
            $this->logAction('setup_default_categories', new ExpenseCategory(['company_id' => $companyId]));

        } catch (\Exception $e) {
            $this->handleException($e, 'setup default categories');
            throw $e;
        }
    }

    /**
     * Search categories
     */
    public function searchCategories(string $search, int $companyId): Collection
    {
        return $this->categoryRepository->search($search, $companyId);
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(array $categoryIds): bool
    {
        try {
            DB::transaction(function () use ($categoryIds) {
                foreach ($categoryIds as $index => $categoryId) {
                    $category = $this->categoryRepository->find($categoryId);
                    if ($category) {
                        $category->updateSortOrder($index + 1);
                    }
                }
            });

            return true;

        } catch (\Exception $e) {
            $this->handleException($e, 'reorder categories');
            throw $e;
        }
    }

    /**
     * Get category statistics for a company
     */
    public function getCategoryStats(int $companyId): array
    {
        try {
            return [
                'total' => $this->categoryRepository->getTotalCount($companyId),
                'active' => $this->categoryRepository->getActiveCount($companyId),
                'inactive' => $this->categoryRepository->getInactiveCount($companyId),
                'system_categories' => $this->categoryRepository->getSystemCategoriesCount($companyId),
                'custom_categories' => $this->categoryRepository->getCustomCategoriesCount($companyId),
                'with_expenses' => $this->categoryRepository->getCategoriesWithExpensesCount($companyId),
            ];
        } catch (\Exception $e) {
            $this->handleException($e, 'get category stats');
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'system_categories' => 0,
                'custom_categories' => 0,
                'with_expenses' => 0,
            ];
        }
    }

    /**
     * Validate business rules for operations
     */
    private function validateBusinessRules(array $rules): void
    {
        foreach ($rules as $rule => $condition) {
            if (!$condition) {
                throw new \Exception("Business rule validation failed: {$rule}");
            }
        }
    }

    /**
     * Log action for audit purposes
     */
    private function logAction(string $action, ExpenseCategory $category): void
    {
        // You can implement logging logic here if needed
        // For example, using Laravel's Log facade or a custom audit system
    }
}