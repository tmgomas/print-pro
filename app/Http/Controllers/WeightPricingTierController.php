<?php
// app/Http/Controllers/WeightPricingTierController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWeightPricingTierRequest;
use App\Http\Requests\UpdateWeightPricingTierRequest;
use App\Repositories\WeightPricingTierRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WeightPricingTierController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WeightPricingTierRepository $pricingTierRepository
    ) {}

    /**
     * Display a listing of weight pricing tiers
     */
    public function index(): Response
    {
        $this->authorize('view weight pricing');

        $user = auth()->user();
        $companyId = $user->company_id;

        $tiers = $this->pricingTierRepository->getForCompany($companyId);

        return Inertia::render('WeightPricing/Index', [
            'tiers' => $tiers->map(fn($tier) => [
                'id' => $tier->id,
                'tier_name' => $tier->tier_name,
                'min_weight' => $tier->min_weight,
                'max_weight' => $tier->max_weight,
                'weight_range' => $tier->weight_range,
                'base_price' => $tier->base_price,
                'price_per_kg' => $tier->price_per_kg,
                'status' => $tier->status,
                'sort_order' => $tier->sort_order,
                'created_at' => $tier->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $tier->updated_at->format('Y-m-d H:i:s'),
            ]),
            'permissions' => [
                'canCreate' => $user->can('create weight pricing'),
                'canEdit' => $user->can('edit weight pricing'),
                'canDelete' => $user->can('delete weight pricing'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new tier
     */
    public function create(): Response
    {
        $this->authorize('create weight pricing');

        return Inertia::render('WeightPricing/Create');
    }

    /**
     * Store a newly created tier
     */
    public function store(CreateWeightPricingTierRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['company_id'] = auth()->user()->company_id;

            $tier = $this->pricingTierRepository->create($data);

            return redirect()->route('weight-pricing.index')
                ->with('success', 'Weight pricing tier created successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Tier creation failed. Please try again.']);
        }
    }

    /**
     * Display the specified tier
     */
    public function show(int $id): Response
    {
        $tier = $this->pricingTierRepository->findOrFail($id);
        $this->authorize('view weight pricing');

        $user = auth()->user();
        if ($tier->company_id !== $user->company_id) {
            abort(403, 'You cannot view this pricing tier.');
        }

        return Inertia::render('WeightPricing/Show', [
            'tier' => [
                'id' => $tier->id,
                'tier_name' => $tier->tier_name,
                'min_weight' => $tier->min_weight,
                'max_weight' => $tier->max_weight,
                'weight_range' => $tier->weight_range,
                'base_price' => $tier->base_price,
                'price_per_kg' => $tier->price_per_kg,
                'status' => $tier->status,
                'sort_order' => $tier->sort_order,
                'created_at' => $tier->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $tier->updated_at->format('Y-m-d H:i:s'),
            ],
            'permissions' => [
                'canEdit' => $user->can('edit weight pricing'),
                'canDelete' => $user->can('delete weight pricing'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified tier
     */
    public function edit(int $id): Response
    {
        $tier = $this->pricingTierRepository->findOrFail($id);
        $this->authorize('edit weight pricing');

        $user = auth()->user();
        if ($tier->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this pricing tier.');
        }

        return Inertia::render('WeightPricing/Edit', [
            'tier' => [
                'id' => $tier->id,
                'tier_name' => $tier->tier_name,
                'min_weight' => $tier->min_weight,
                'max_weight' => $tier->max_weight,
                'base_price' => $tier->base_price,
                'price_per_kg' => $tier->price_per_kg,
                'status' => $tier->status,
                'sort_order' => $tier->sort_order,
            ],
        ]);
    }

    /**
     * Update the specified tier
     */
    public function update(UpdateWeightPricingTierRequest $request, int $id): RedirectResponse
    {
        try {
            $tier = $this->pricingTierRepository->findOrFail($id);

            $user = auth()->user();
            if ($tier->company_id !== $user->company_id) {
                abort(403, 'You cannot edit this pricing tier.');
            }

            $data = $request->validated();
            $this->pricingTierRepository->update($id, $data);

            return redirect()->route('weight-pricing.show', $id)
                ->with('success', 'Weight pricing tier updated successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Tier update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified tier
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $tier = $this->pricingTierRepository->findOrFail($id);
            $this->authorize('delete weight pricing');

            $user = auth()->user();
            if ($tier->company_id !== $user->company_id) {
                abort(403, 'You cannot delete this pricing tier.');
            }

            $this->pricingTierRepository->delete($id);

            return redirect()->route('weight-pricing.index')
                ->with('success', 'Weight pricing tier deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Tier deletion failed. Please try again.']);
        }
    }

    /**
     * Toggle tier status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $tier = $this->pricingTierRepository->findOrFail($id);
            $this->authorize('edit weight pricing');

            $user = auth()->user();
            if ($tier->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $newStatus = $tier->status === 'active' ? 'inactive' : 'active';
            $this->pricingTierRepository->update($id, ['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => 'Tier status updated successfully.'
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
            $this->authorize('edit weight pricing');

            $tierIds = $request->get('tier_ids', []);
            $this->pricingTierRepository->updateSortOrder($tierIds);

            return response()->json([
                'success' => true,
                'message' => 'Sort order updated successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Sort order update failed'], 500);
        }
    }

    /**
     * Calculate delivery price for weight
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'weight' => 'required|numeric|min:0|max:99999.999',
            ]);

            $user = auth()->user();
            $weight = $request->get('weight');

            $calculation = $this->pricingTierRepository->calculateDeliveryPrice($user->company_id, $weight);

            return response()->json([
                'success' => true,
                'calculation' => $calculation,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Price calculation failed'], 500);
        }
    }
}