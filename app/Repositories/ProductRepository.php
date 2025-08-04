<?php
// app/Repositories/ProductRepository.php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    /**
     * Search and paginate products
     */
    public function searchAndPaginate(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->forCompany($companyId)
            ->with(['category', 'category.parent']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->searchByName($filters['search']);
        }

        if (!empty($filters['category_id'])) {
            $query->inCategory($filters['category_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['min_price'])) {
            $query->where('base_price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('base_price', '<=', $filters['max_price']);
        }

        if (!empty($filters['requires_customization'])) {
            $query->where('requires_customization', $filters['requires_customization'] === 'true');
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get products for dropdown
     */
    // app/Repositories/ProductRepository.php - getForDropdown method
public function getForDropdown(int $companyId, ?int $categoryId = null): Collection
{
    $query = $this->model
        ->where('company_id', $companyId)
        ->where('status', 'active')
        ->select([
            'id', 
            'name', 
            'product_code', 
            'base_price', 
            'weight_per_unit',
            'weight_unit', 
            'tax_rate', 
            'unit_type'
        ]);

    if ($categoryId) {
        $query->where('category_id', $categoryId);
    }

    return $query->orderBy('name')->get();
}


    /**
     * Generate unique product code
     */
    public function generateUniqueCode(int $categoryId): string
    {
        $category = \App\Models\ProductCategory::find($categoryId);
        $categoryCode = $category ? strtoupper(substr($category->code, 0, 3)) : 'PRD';
        
        $counter = 1;
        
        do {
            $code = $categoryCode . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
            $exists = $this->model->where('product_code', $code)->exists();
            $counter++;
        } while ($exists);
        
        return $code;
    }

    /**
     * Get product statistics
     */
    public function getStats(int $companyId): array
    {
        $totalProducts = $this->model->forCompany($companyId)->count();
        $activeProducts = $this->model->forCompany($companyId)->active()->count();
        $productsWithCustomization = $this->model->forCompany($companyId)
            ->where('requires_customization', true)
            ->count();
        $averagePrice = $this->model->forCompany($companyId)->avg('base_price');

        return [
            'total' => $totalProducts,
            'active' => $activeProducts,
            'inactive' => $totalProducts - $activeProducts,
            'with_customization' => $productsWithCustomization,
            'average_price' => round($averagePrice, 2),
        ];
    }

    /**
     * Get products by category
     */
    public function getByCategory(int $categoryId, bool $activeOnly = true): Collection
    {
        $query = $this->model->inCategory($categoryId);
        
        if ($activeOnly) {
            $query->active();
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * Bulk update product status
     */
    public function bulkUpdateStatus(array $productIds, string $status): int
    {
        return $this->model->whereIn('id', $productIds)->update(['status' => $status]);
    }

    /**
     * Get featured products
     */
    public function getFeaturedProducts(int $companyId, int $limit = 10): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->active()
            ->whereNotNull('image')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}