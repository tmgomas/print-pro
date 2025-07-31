<?php
// app/Repositories/ProductCategoryRepository.php

namespace App\Repositories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductCategoryRepository extends BaseRepository
{
    public function __construct(ProductCategory $model)
    {
        parent::__construct($model);
    }

    /**
     * Get hierarchical categories for a company
     */
    public function getHierarchicalCategories(int $companyId): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->active()
            ->topLevel()
            ->with(['children' => function ($query) {
                $query->active()->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all categories for dropdown
     */
    public function getForDropdown(int $companyId): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }

    /**
     * Search and paginate categories
     */
    public function searchAndPaginate(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->forCompany($companyId)
            ->with(['parent', 'children'])
            ->withCount('products');

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('code', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $filters['parent_id']);
            }
        }

        return $query->orderBy('sort_order')->paginate($perPage);
    }

    /**
     * Generate unique category code
     */
    public function generateUniqueCode(string $name, int $companyId): string
    {
        $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $counter = 1;
        
        do {
            $code = $baseCode . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $exists = $this->model->where('code', $code)->exists();
            $counter++;
        } while ($exists);
        
        return $code;
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(array $categoryIds): void
    {
        foreach ($categoryIds as $index => $categoryId) {
            $this->model->where('id', $categoryId)->update(['sort_order' => $index + 1]);
        }
    }

    /**
     * Get category statistics
     */
    public function getStats(int $companyId): array
    {
        $totalCategories = $this->model->forCompany($companyId)->count();
        $activeCategories = $this->model->forCompany($companyId)->active()->count();
        $topLevelCategories = $this->model->forCompany($companyId)->topLevel()->count();
        $categoriesWithProducts = $this->model->forCompany($companyId)
            ->has('products')
            ->count();

        return [
            'total' => $totalCategories,
            'active' => $activeCategories,
            'inactive' => $totalCategories - $activeCategories,
            'top_level' => $topLevelCategories,
            'with_products' => $categoriesWithProducts,
        ];
    }
}