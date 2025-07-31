<?php
// app/Http/Controllers/ProductCategoryController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Repositories\ProductCategoryRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class ProductCategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ProductCategoryRepository $categoryRepository
    ) {}

    /**
     * Display a listing of product categories
     */
    public function index(Request $request): Response
    {
        $this->authorize('view product categories');

        $user = auth()->user();
        $companyId = $user->company_id;

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'parent_id' => $request->get('parent_id'),
        ];

        $categories = $this->categoryRepository->searchAndPaginate($companyId, $filters, 15);
        $stats = $this->categoryRepository->getStats($companyId);
        $hierarchicalCategories = $this->categoryRepository->getHierarchicalCategories($companyId);

        return Inertia::render('ProductCategories/Index', [
            'categories' => [
                'data' => $categories->items(),
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
            'hierarchicalCategories' => $hierarchicalCategories,
            'filters' => $filters,
            'stats' => $stats,
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
                'parentCategories' => $this->categoryRepository->getForDropdown($companyId),
            ],
            'permissions' => [
                'canCreate' => $user->can('create product categories'),
                'canEdit' => $user->can('edit product categories'),
                'canDelete' => $user->can('delete product categories'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new category
     */
    public function create(): Response
    {
        $this->authorize('create product categories');

        $user = auth()->user();
        $parentCategories = $this->categoryRepository->getForDropdown($user->company_id);

        return Inertia::render('ProductCategories/Create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Store a newly created category
     */
    public function store(CreateProductCategoryRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['company_id'] = auth()->user()->company_id;
            
            // Generate unique code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->categoryRepository->generateUniqueCode($data['name'], $data['company_id']);
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('product-categories', 'public');
            }

            $category = $this->categoryRepository->create($data);

            return redirect()->route('product-categories.show', $category->id)
                ->with('success', 'Product category created successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Category creation failed. Please try again.']);
        }
    }

    /**
     * Display the specified category
     */
    public function show(int $id): Response
    {
        $category = $this->categoryRepository->findOrFail($id);
        $this->authorize('view product categories');

        $user = auth()->user();
        if ($category->company_id !== $user->company_id) {
            abort(403, 'You cannot view this category.');
        }

        $category->load(['parent', 'children.products', 'products']);

        return Inertia::render('ProductCategories/Show', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'status' => $category->status,
                'sort_order' => $category->sort_order,
                'created_at' => $category->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
                'parent' => $category->parent ? [
                    'id' => $category->parent->id,
                    'name' => $category->parent->name,
                ] : null,
                'children' => $category->children->map(fn($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'products_count' => $child->products->count(),
                    'status' => $child->status,
                ]),
                'products' => $category->products->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_code' => $product->product_code,
                    'base_price' => $product->base_price,
                    'status' => $product->status,
                ]),
                'hierarchy' => $category->getHierarchy(),
                'products_count' => $category->products->count(),
            ],
            'permissions' => [
                'canEdit' => $user->can('edit product categories'),
                'canDelete' => $user->can('delete product categories'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified category
     */
    public function edit(int $id): Response
    {
        $category = $this->categoryRepository->findOrFail($id);
        $this->authorize('edit product categories');

        $user = auth()->user();
        if ($category->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this category.');
        }

        $parentCategories = $this->categoryRepository->getForDropdown($user->company_id)
            ->reject(fn($cat) => $cat->id === $category->id); // Exclude self from parent options

        return Inertia::render('ProductCategories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'image_url' => $category->image_url,
                'parent_id' => $category->parent_id,
                'status' => $category->status,
                'sort_order' => $category->sort_order,
            ],
            'parentCategories' => $parentCategories,
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(UpdateProductCategoryRequest $request, int $id): RedirectResponse
    {
        try {
            $category = $this->categoryRepository->findOrFail($id);

            $user = auth()->user();
            if ($category->company_id !== $user->company_id) {
                abort(403, 'You cannot edit this category.');
            }

            $data = $request->validated();

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }
                $data['image'] = $request->file('image')->store('product-categories', 'public');
            }

            $this->categoryRepository->update($id, $data);

            return redirect()->route('product-categories.show', $id)
                ->with('success', 'Product category updated successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Category update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified category
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $category = $this->categoryRepository->findOrFail($id);
            $this->authorize('delete product categories');

            $user = auth()->user();
            if ($category->company_id !== $user->company_id) {
                abort(403, 'You cannot delete this category.');
            }

            // Check if category has products
            if ($category->products()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete category with products. Please move or delete products first.']);
            }

            // Check if category has child categories
            if ($category->children()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete category with subcategories. Please delete subcategories first.']);
            }

            // Delete image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $this->categoryRepository->delete($id);

            return redirect()->route('product-categories.index')
                ->with('success', 'Product category deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Category deletion failed. Please try again.']);
        }
    }

    /**
     * Toggle category status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $category = $this->categoryRepository->findOrFail($id);
            $this->authorize('edit product categories');

            $user = auth()->user();
            if ($category->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $newStatus = $category->status === 'active' ? 'inactive' : 'active';
            $this->categoryRepository->update($id, ['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => 'Category status updated successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Status update failed'], 500);
        }
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(Request $request): JsonResponse
    {
        try {
            $this->authorize('edit product categories');

            $categoryIds = $request->get('category_ids', []);
            $this->categoryRepository->updateSortOrder($categoryIds);

            return response()->json([
                'success' => true,
                'message' => 'Sort order updated successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Sort order update failed'], 500);
        }
    }
}