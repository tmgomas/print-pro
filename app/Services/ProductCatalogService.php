<?php
// app/Services/ProductCatalogService.php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\WeightPricingTierRepository;
use Illuminate\Support\Collection;

class ProductCatalogService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductCategoryRepository $categoryRepository,
        private WeightPricingTierRepository $pricingTierRepository
    ) {}

    /**
     * Get complete product catalog with categories
     */
    public function getProductCatalog(int $companyId, array $filters = []): array
    {
        $categories = $this->categoryRepository->getHierarchicalCategories($companyId);
        $products = $this->productRepository->searchAndPaginate($companyId, $filters, 50);
        $stats = $this->getProductCatalogStats($companyId);

        return [
            'categories' => $categories,
            'products' => $products,
            'stats' => $stats,
        ];
    }

    /**
     * Calculate product pricing with delivery
     */
    public function calculateProductPricing(int $productId, int $quantity, int $companyId): array
    {
        $product = $this->productRepository->findOrFail($productId);
        
        if ($product->company_id !== $companyId) {
            throw new \Exception('Product not found in company catalog');
        }

        // Base product calculation
        $baseCalculation = $product->calculatePrice($quantity);
        
        // Delivery calculation
        $deliveryCalculation = $this->pricingTierRepository->calculateDeliveryPrice(
            $companyId, 
            $baseCalculation['total_weight']
        );

        // Combined calculation
        $subtotal = $baseCalculation['total_price'];
        $deliveryCharge = $deliveryCalculation['price'];
        $totalAmount = $subtotal + $deliveryCharge;

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => $product->base_price,
                'quantity' => $quantity,
                'unit_weight' => $product->weight_per_unit,
            ],
            'pricing' => [
                'base_price' => $baseCalculation['base_price'],
                'total_weight' => $baseCalculation['total_weight'],
                'tax_amount' => $baseCalculation['tax_amount'],
                'subtotal' => $subtotal,
                'delivery_charge' => $deliveryCharge,
                'delivery_tier' => $deliveryCalculation['tier']?->tier_name,
                'total_amount' => $totalAmount,
            ],
            'breakdown' => [
                'product_total' => $baseCalculation['base_price'],
                'tax_total' => $baseCalculation['tax_amount'],
                'delivery_total' => $deliveryCharge,
                'final_total' => $totalAmount,
            ],
        ];
    }

    /**
     * Get products by category with pricing
     */
    public function getProductsByCategory(int $categoryId, int $companyId): Collection
    {
        $products = $this->productRepository->getByCategory($categoryId);
        
        return $products->map(function ($product) use ($companyId) {
            $sampleCalculation = $this->calculateProductPricing($product->id, 1, $companyId);
            
            return [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'formatted_price' => $product->formatted_price,
                'weight_per_unit' => $product->weight_per_unit,
                'image_url' => $product->image_url,
                'specifications' => $product->specifications,
                'requires_customization' => $product->requires_customization,
                'sample_pricing' => $sampleCalculation['pricing'],
            ];
        });
    }

    /**
     * Search products across all categories
     */
    public function searchProducts(string $query, int $companyId, int $limit = 20): Collection
    {
        $filters = ['search' => $query];
        $products = $this->productRepository->searchAndPaginate($companyId, $filters, $limit);
        
        return collect($products->items())->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'category_name' => $product->category->name,
                'base_price' => $product->base_price,
                'formatted_price' => $product->formatted_price,
                'image_url' => $product->image_url,
                'weight_per_unit' => $product->weight_per_unit,
            ];
        });
    }

    /**
     * Get product recommendations
     */
    public function getProductRecommendations(int $productId, int $companyId, int $limit = 5): Collection
    {
        $product = $this->productRepository->findOrFail($productId);
        
        // Get products from same category
        $recommendations = $this->productRepository->getByCategory($product->category_id)
            ->where('id', '!=', $productId)
            ->take($limit);

        return $recommendations->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'product_code' => $product->product_code,
                'base_price' => $product->base_price,
                'formatted_price' => $product->formatted_price,
                'image_url' => $product->image_url,
                'category_name' => $product->category->name,
            ];
        });
    }

    /**
     * Get product catalog statistics
     */
    private function getProductCatalogStats(int $companyId): array
    {
        $productStats = $this->productRepository->getStats($companyId);
        $categoryStats = $this->categoryRepository->getStats($companyId);
        
        return [
            'products' => $productStats,
            'categories' => $categoryStats,
            'summary' => [
                'total_products' => $productStats['total'],
                'active_products' => $productStats['active'],
                'total_categories' => $categoryStats['total'],
                'active_categories' => $categoryStats['active'],
                'average_product_price' => $productStats['average_price'],
            ],
        ];
    }

    /**
     * Bulk import products from CSV
     */
    public function importProductsFromCsv(string $filePath, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $csvData = array_map('str_getcsv', file($filePath));
        $headers = array_shift($csvData);
        
        foreach ($csvData as $index => $row) {
            try {
                $data = array_combine($headers, $row);
                $data['company_id'] = $companyId;
                
                // Generate product code if not provided
                if (empty($data['product_code'])) {
                    $data['product_code'] = $this->productRepository->generateUniqueCode($data['category_id']);
                }
                
                $this->productRepository->create($data);
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($csvData),
        ];
    }

    /**
     * Export products to CSV
     */
    public function exportProductsToCsv(int $companyId, array $filters = []): string
    {
        $products = $this->productRepository->searchAndPaginate($companyId, $filters, 10000);
        
        $fileName = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filePath = storage_path('app/exports/' . $fileName);
        
        // Ensure directory exists
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        $file = fopen($filePath, 'w');
        
        // Write headers
        fputcsv($file, [
            'Product Code',
            'Name',
            'Description',
            'Category',
            'Base Price',
            'Weight Per Unit',
            'Weight Unit',
            'Tax Rate',
            'Status',
            'Created At',
        ]);
        
        // Write data
        foreach ($products as $product) {
            fputcsv($file, [
                $product->product_code,
                $product->name,
                $product->description,
                $product->category->name,
                $product->base_price,
                $product->weight_per_unit,
                $product->weight_unit,
                $product->tax_rate,
                $product->status,
                $product->created_at->format('Y-m-d H:i:s'),
            ]);
        }
        
        fclose($file);
        
        return $filePath;
    }

    /**
     * Get category tree with product counts
     */
    public function getCategoryTreeWithCounts(int $companyId): Collection
    {
        $categories = $this->categoryRepository->getHierarchicalCategories($companyId);
        
        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $category->products_count,
                'children' => $category->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'product_count' => $child->products_count,
                    ];
                }),
            ];
        });
    }
}
