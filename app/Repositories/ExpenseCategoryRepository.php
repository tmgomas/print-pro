<?php

namespace App\Repositories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryRepository extends BaseRepository
{
    /**
     * Constructor - Inject ExpenseCategory model
     */
    public function __construct(ExpenseCategory $model)
    {
        parent::__construct($model);
    }

    /**
     * Get categories for company
     */
    public function getCompanyCategories(int $companyId, bool $activeOnly = true): Collection
    {
        $query = $this->model->forCompany($companyId)
                            ->orderBy('sort_order')
                            ->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get parent categories
     */
    public function getParentCategories(int $companyId): Collection
    {
        return $this->model->forCompany($companyId)
                          ->parentCategories()
                          ->active()
                          ->orderBy('sort_order')
                          ->get();
    }

    /**
     * Get categories with hierarchy
     */
    public function getCategoriesWithHierarchy(int $companyId): Collection
    {
        return $this->model->with(['children' => function ($query) {
                              $query->active()->orderBy('sort_order');
                          }])
                          ->forCompany($companyId)
                          ->parentCategories()
                          ->active()
                          ->orderBy('sort_order')
                          ->get();
    }

    /**
     * Find by code
     */
    public function findByCode(string $code, int $companyId): ?ExpenseCategory
    {
        return $this->model->where('code', $code)
                          ->forCompany($companyId)
                          ->first();
    }

    /**
     * Search categories
     */
    public function search(string $search, int $companyId): Collection
    {
        return $this->model->forCompany($companyId)
                          ->where(function ($query) use ($search) {
                              $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('code', 'like', "%{$search}%")
                                    ->orWhere('description', 'like', "%{$search}%");
                          })
                          ->active()
                          ->orderBy('name')
                          ->get();
    }

    /**
     * Get paginated categories with filters
     */
    public function getPaginatedCategories(array $filters = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Apply company filter
        if (isset($filters['company_id'])) {
            $query->forCompany($filters['company_id']);
        }

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        } else {
            // Default to active only if no specific status filter
            $query->active();
        }

        // Apply search filter
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply parent filter
        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null' || $filters['parent_id'] === '') {
                $query->parentCategories();
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'sort_order';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        
        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order', $sortOrder)
                  ->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create system categories for company
     */
    public function createSystemCategories(int $companyId): void
    {
        $systemCategories = [
            ['name' => 'Office Supplies', 'code' => 'OFFICE', 'icon' => 'building', 'color' => '#3B82F6', 'description' => 'Office stationery, equipment, and supplies'],
            ['name' => 'Travel & Transportation', 'code' => 'TRAVEL', 'icon' => 'car', 'color' => '#10B981', 'description' => 'Business travel, fuel, and transportation costs'],
            ['name' => 'Marketing & Advertising', 'code' => 'MARKET', 'icon' => 'megaphone', 'color' => '#F59E0B', 'description' => 'Marketing campaigns, advertising, and promotions'],
            ['name' => 'Utilities', 'code' => 'UTIL', 'icon' => 'zap', 'color' => '#EF4444', 'description' => 'Electricity, water, internet, and utility bills'],
            ['name' => 'Equipment & Maintenance', 'code' => 'EQUIP', 'icon' => 'wrench', 'color' => '#8B5CF6', 'description' => 'Equipment purchases and maintenance costs'],
            ['name' => 'Professional Services', 'code' => 'PROF', 'icon' => 'user-tie', 'color' => '#06B6D4', 'description' => 'Legal, consulting, and professional service fees'],
            ['name' => 'Food & Entertainment', 'code' => 'FOOD', 'icon' => 'utensils', 'color' => '#84CC16', 'description' => 'Business meals, entertainment, and refreshments'],
            ['name' => 'Miscellaneous', 'code' => 'MISC', 'icon' => 'more-horizontal', 'color' => '#6B7280', 'description' => 'Other business expenses not categorized elsewhere'],
        ];

        foreach ($systemCategories as $index => $category) {
            // Check if category already exists
            $existingCategory = $this->findByCode($category['code'], $companyId);
            
            if (!$existingCategory) {
                $this->create([
                    'company_id' => $companyId,
                    'name' => $category['name'],
                    'code' => $category['code'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'is_system_category' => true,
                    'sort_order' => $index + 1,
                    'status' => 'active'
                ]);
            }
        }
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats(int $companyId): array
    {
        $query = $this->model->forCompany($companyId);

        $total = $query->count();
        $active = $query->active()->count();
        $inactive = $query->where('status', 'inactive')->count();
        $systemCategories = $query->where('is_system_category', true)->count();
        $customCategories = $query->where('is_system_category', false)->count();
        
        // Count categories with expenses (if expenses table exists)
        $withExpenses = 0;
        if (\Schema::hasTable('expenses')) {
            $withExpenses = $query->whereHas('expenses')->count();
        }

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'system_categories' => $systemCategories,
            'custom_categories' => $customCategories,
            'with_expenses' => $withExpenses,
        ];
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(array $categoryIds): bool
    {
        try {
            foreach ($categoryIds as $index => $categoryId) {
                $this->update($categoryId, ['sort_order' => $index + 1]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Activate category
     */
    public function activate(int $categoryId): bool
    {
        return $this->update($categoryId, ['status' => 'active']);
    }

    /**
     * Deactivate category
     */
    public function deactivate(int $categoryId): bool
    {
        return $this->update($categoryId, ['status' => 'inactive']);
    }

    /**
     * Check if category can be deleted
     */
    public function canBeDeleted(int $categoryId): bool
    {
        $category = $this->find($categoryId);
        
        if (!$category) {
            return false;
        }

        // System categories cannot be deleted
        if ($category->is_system_category) {
            return false;
        }

        // Categories with children cannot be deleted
        if ($category->children()->exists()) {
            return false;
        }

        // Categories with expenses cannot be deleted (if expenses table exists)
        if (\Schema::hasTable('expenses') && $category->expenses()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Get categories for dropdown/select options
     */
    public function getCategoriesForSelect(int $companyId): Collection
    {
        return $this->model->forCompany($companyId)
                          ->active()
                          ->orderBy('sort_order')
                          ->orderBy('name')
                          ->get(['id', 'name', 'code', 'color', 'parent_id']);
    }

    /**
     * Get category tree structure
     */
    public function getCategoryTree(int $companyId): Collection
    {
        return $this->model->with(['children.children']) // Support 2 levels
                          ->forCompany($companyId)
                          ->parentCategories()
                          ->active()
                          ->orderBy('sort_order')
                          ->get();
    }

    /**
     * Clone category to another company
     */
    public function cloneToCompany(int $categoryId, int $targetCompanyId): ?ExpenseCategory
    {
        $category = $this->find($categoryId);
        
        if (!$category) {
            return null;
        }

        // Check if category already exists in target company
        $existingCategory = $this->findByCode($category->code, $targetCompanyId);
        
        if ($existingCategory) {
            return $existingCategory;
        }

        // Clone the category
        $clonedData = $category->toArray();
        unset($clonedData['id'], $clonedData['created_at'], $clonedData['updated_at'], $clonedData['deleted_at']);
        $clonedData['company_id'] = $targetCompanyId;
        $clonedData['parent_id'] = null; // Reset parent relationship
        $clonedData['is_system_category'] = false; // Make it custom for new company

        return $this->create($clonedData);
    }

    /**
     * Get categories by parent
     */
    public function getCategoriesByParent(int $parentId): Collection
    {
        return $this->model->where('parent_id', $parentId)
                          ->active()
                          ->orderBy('sort_order')
                          ->get();
    }

    /**
     * Update category with validation
     */
    public function updateCategory(int $categoryId, array $data): bool
    {
        $category = $this->find($categoryId);
        
        if (!$category) {
            return false;
        }

        // Prevent circular parent relationships
        if (isset($data['parent_id']) && $data['parent_id']) {
            if ($this->wouldCreateCircularRelation($categoryId, $data['parent_id'])) {
                throw new \InvalidArgumentException('Cannot set parent: would create circular relationship');
            }
        }

        return $this->update($categoryId, $data);
    }

    /**
     * Check if setting parent would create circular relationship
     */
    private function wouldCreateCircularRelation(int $categoryId, int $parentId): bool
    {
        $parent = $this->find($parentId);
        
        while ($parent) {
            if ($parent->id === $categoryId) {
                return true;
            }
            $parent = $parent->parent;
        }
        
        return false;
    }
}