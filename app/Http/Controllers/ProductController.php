<?php
// app/Http/Controllers/ProductController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Repositories\ProductRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\WeightPricingTierRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ProductRepository $productRepository,
        private ProductCategoryRepository $categoryRepository,
        private WeightPricingTierRepository $pricingTierRepository
    ) {}

    /**
     * Display a listing of products
     */
    public function index(Request $request): Response
    {
        $this->authorize('view products');

        $user = auth()->user();
        $companyId = $user->company_id;

        $filters = [
            'search' => $request->get('search'),
            'category_id' => $request->get('category_id'),
            'status' => $request->get('status'),
            'min_price' => $request->get('min_price'),
            'max_price' => $request->get('max_price'),
            'requires_customization' => $request->get('requires_customization'),
        ];

        $products = $this->productRepository->searchAndPaginate($companyId, $filters, 15);
        $stats = $this->productRepository->getStats($companyId);
        $categories = $this->categoryRepository->getForDropdown($companyId);

        return Inertia::render('Products/Index', [
            'products' => [
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'filters' => $filters,
            'stats' => $stats,
            'filterOptions' => [
                'categories' => $categories,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
                'customizationOptions' => [
                    ['value' => 'true', 'label' => 'Requires Customization'],
                    ['value' => 'false', 'label' => 'No Customization'],
                ],
            ],
            'permissions' => [
                'canCreate' => $user->can('create products'),
                'canEdit' => $user->can('edit products'),
                'canDelete' => $user->can('delete products'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new product
     */
    public function create(): Response
    {
        $this->authorize('create products');

        $user = auth()->user();
        $categories = $this->categoryRepository->getForDropdown($user->company_id);

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'unitTypes' => [
                ['value' => 'piece', 'label' => 'Piece'],
                ['value' => 'sheet', 'label' => 'Sheet'],
                ['value' => 'roll', 'label' => 'Roll'],
                ['value' => 'meter', 'label' => 'Meter'],
                ['value' => 'kilogram', 'label' => 'Kilogram'],
            ],
            'weightUnits' => [
                ['value' => 'kg', 'label' => 'Kilogram'],
                ['value' => 'g', 'label' => 'Gram'],
                ['value' => 'lb', 'label' => 'Pound'],
            ],
        ]);
    }

    /**
     * Store a newly created product
     */
public function store(CreateProductRequest $request): RedirectResponse
{
    try {
        $data = $request->validated();
        $data['company_id'] = auth()->user()->company_id;

        // Generate unique product code if not provided
        if (empty($data['product_code'])) {
            $data['product_code'] = $this->productRepository->generateUniqueCode($data['category_id']);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        // Handle specifications JSON
        if ($request->has('specifications') && is_array($request->specifications)) {
            $data['specifications'] = $request->specifications;
        }

        $product = $this->productRepository->create($data);

        return redirect()->route('products.show', $product->id)
            ->with('success', 'Product created successfully.');

    } catch (\Exception $e) {
        return back()
            ->withInput()
            ->withErrors(['error' => 'Product creation failed. Please try again.']);
    }
}

    /**
     * Display the specified product
     */
    public function show(int $id): Response
    {
        $product = $this->productRepository->findOrFail($id);
        $this->authorize('view products');

        $user = auth()->user();
        if ($product->company_id !== $user->company_id) {
            abort(403, 'You cannot view this product.');
        }

        $product->load(['category.parent']);

        return Inertia::render('Products/Show', [
            'product' => [
                'id' => $product->id,
                'product_code' => $product->product_code,
                'name' => $product->name,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'formatted_price' => $product->formatted_price,
                'unit_type' => $product->unit_type,
                'weight_per_unit' => $product->weight_per_unit,
                'weight_unit' => $product->weight_unit,
                'formatted_weight' => $product->formatted_weight,
                'tax_rate' => $product->tax_rate,
                'image_url' => $product->image_url,
                'specifications' => $product->specifications,
                'status' => $product->status,
                'minimum_quantity' => $product->minimum_quantity,
                'maximum_quantity' => $product->maximum_quantity,
                'requires_customization' => $product->requires_customization,
                'customization_options' => $product->customization_options,
                'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
                'category' => [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'hierarchy' => $product->category_hierarchy,
                ],
            ],
            'permissions' => [
                'canEdit' => $user->can('edit products'),
                'canDelete' => $user->can('delete products'),
            ],
        ]);
    }
/**
 * Show the form for editing the specified product
 */
// app/Http/Controllers/ProductController.php
public function edit(int $id): Response
{
    $product = $this->productRepository->findOrFail($id);
    $this->authorize('edit products');

    $user = auth()->user();
    if ($product->company_id !== $user->company_id) {
        abort(403, 'You cannot edit this product.');
    }

    $categories = $this->categoryRepository->getForDropdown($user->company_id);
    
    return Inertia::render('Products/Edit', [
        'product' => [
            'id' => $product->id,
            'name' => $product->name,
            'product_code' => $product->product_code,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'base_price' => $product->base_price,
            'unit_type' => $product->unit_type ?? 'piece', // Add this line
            'weight_per_unit' => $product->weight_per_unit,
            'weight_unit' => $product->weight_unit,
            'tax_rate' => $product->tax_rate,
            'status' => $product->status,
            'image_url' => $product->image_url,
            'specifications' => $product->specifications,
            'requires_customization' => $product->requires_customization,
            'customization_options' => $product->customization_options,
            'minimum_quantity' => $product->minimum_quantity,
            'stock_quantity' => $product->stock_quantity,
            'reorder_level' => $product->reorder_level,
            'is_featured' => $product->is_featured,
            'is_digital' => $product->is_digital,
            'production_time_days' => $product->production_time_days,
            'keywords' => $product->keywords,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ],
        'categories' => $categories,
    ]);
}
/**
 * Update the specified product
 */
public function update(UpdateProductRequest $request, int $id): RedirectResponse
{
    try {
        $product = $this->productRepository->findOrFail($id);
        
        $user = auth()->user();
        if ($product->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this product.');
        }

        $data = $request->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $this->productRepository->update($id, $data);

        return redirect()->route('products.show', $id)
            ->with('success', 'Product updated successfully.');

    } catch (\Exception $e) {
        return back()
            ->withInput()
            ->withErrors(['error' => 'Product update failed.']);
    }
}
    /**
     * Calculate product pricing
     */
    public function calculatePricing(Request $request): JsonResponse
    {
        try {
            $productId = $request->get('product_id');
            $quantity = (int) $request->get('quantity', 1);

            $product = $this->productRepository->findOrFail($productId);
            
            $user = auth()->user();
            if ($product->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $calculation = $product->calculatePrice($quantity);
            
            // Calculate delivery charge
            $deliveryCalculation = $this->pricingTierRepository->calculateDeliveryPrice(
                $user->company_id, 
                $calculation['total_weight']
            );

            return response()->json([
                'success' => true,
                'calculation' => [
                    'base_price' => $calculation['base_price'],
                    'total_weight' => $calculation['total_weight'],
                    'tax_amount' => $calculation['tax_amount'],
                    'delivery_charge' => $deliveryCalculation['price'],
                    'delivery_tier' => $deliveryCalculation['tier']?->tier_name,
                    'subtotal' => $calculation['total_price'],
                    'total_amount' => $calculation['total_price'] + $deliveryCalculation['price'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Calculation failed'], 500);
        }
    }
}