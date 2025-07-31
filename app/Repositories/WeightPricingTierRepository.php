<?php
// app/Repositories/WeightPricingTierRepository.php

namespace App\Repositories;

use App\Models\WeightPricingTier;
use Illuminate\Database\Eloquent\Collection;

class WeightPricingTierRepository extends BaseRepository
{
    public function __construct(WeightPricingTier $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all tiers for a company ordered by weight
     */
    public function getForCompany(int $companyId): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->active()
            ->orderedByWeight()
            ->get();
    }

    /**
     * Find appropriate tier for given weight
     */
    public function findTierForWeight(int $companyId, float $weight): ?WeightPricingTier
    {
        return $this->model
            ->forCompany($companyId)
            ->active()
            ->where('min_weight', '<=', $weight)
            ->where(function ($query) use ($weight) {
                $query->whereNull('max_weight')
                      ->orWhere('max_weight', '>=', $weight);
            })
            ->orderedByWeight()
            ->first();
    }

    /**
     * Calculate delivery price for weight
     */
    public function calculateDeliveryPrice(int $companyId, float $weight): array
    {
        $tier = $this->findTierForWeight($companyId, $weight);
        
        if (!$tier) {
            return [
                'tier' => null,
                'price' => 0,
                'breakdown' => [],
            ];
        }
        
        $calculation = $tier->calculatePrice($weight);
        
        return [
            'tier' => $tier,
            'price' => $calculation['total_price'],
            'breakdown' => $calculation,
        ];
    }

    /**
     * Update sort order
     */
    public function updateSortOrder(array $tierIds): void
    {
        foreach ($tierIds as $index => $tierId) {
            $this->model->where('id', $tierId)->update(['sort_order' => $index + 1]);
        }
    }
}
