<?php
// app/Services/WeightPricingService.php

namespace App\Services;

use App\Repositories\WeightPricingTierRepository;
use App\Models\WeightPricingTier;
use Illuminate\Support\Collection;

class WeightPricingService
{
    public function __construct(
        private WeightPricingTierRepository $pricingTierRepository
    ) {}

    /**
     * Calculate delivery pricing for weight
     */
    public function calculateDeliveryPrice(float $weight, int $companyId): array
    {
        return $this->pricingTierRepository->calculateDeliveryPrice($companyId, $weight);
    }

    /**
     * Get all pricing tiers for company
     */
    public function getPricingTiers(int $companyId): Collection
    {
        return $this->pricingTierRepository->getForCompany($companyId);
    }

    /**
     * Create pricing tier with validation
     */
    public function createPricingTier(array $data, int $companyId): WeightPricingTier
    {
        $data['company_id'] = $companyId;
        
        // Validate weight ranges don't overlap
        $this->validateWeightRange($data['min_weight'], $data['max_weight'], $companyId);
        
        return $this->pricingTierRepository->create($data);
    }

    /**
     * Update pricing tier with validation
     */
    public function updatePricingTier(int $tierId, array $data, int $companyId): bool
    {
        $tier = $this->pricingTierRepository->findOrFail($tierId);
        
        if ($tier->company_id !== $companyId) {
            throw new \Exception('Pricing tier not found in company');
        }
        
        // Validate weight ranges don't overlap (excluding current tier)
        $this->validateWeightRange($data['min_weight'], $data['max_weight'], $companyId, $tierId);
        
        return $this->pricingTierRepository->update($tierId, $data);
    }

    /**
     * Validate weight range doesn't overlap with existing tiers
     */
    private function validateWeightRange(float $minWeight, ?float $maxWeight, int $companyId, ?int $excludeTierId = null): void
    {
        $existingTiers = $this->pricingTierRepository->getForCompany($companyId);
        
        if ($excludeTierId) {
            $existingTiers = $existingTiers->where('id', '!=', $excludeTierId);
        }
        
        foreach ($existingTiers as $tier) {
            // Check for overlap
            $tierMaxWeight = $tier->max_weight ?? PHP_FLOAT_MAX;
            $newMaxWeight = $maxWeight ?? PHP_FLOAT_MAX;
            
            if ($minWeight < $tierMaxWeight && $newMaxWeight > $tier->min_weight) {
                throw new \Exception("Weight range overlaps with existing tier: {$tier->tier_name}");
            }
        }
    }

    /**
     * Get pricing breakdown for multiple weights
     */
    public function getPricingBreakdown(array $weights, int $companyId): array
    {
        $breakdown = [];
        
        foreach ($weights as $weight) {
            $calculation = $this->calculateDeliveryPrice($weight, $companyId);
            $breakdown[] = [
                'weight' => $weight,
                'tier' => $calculation['tier']?->tier_name,
                'price' => $calculation['price'],
                'breakdown' => $calculation['breakdown'],
            ];
        }
        
        return $breakdown;
    }

    /**
     * Optimize pricing tiers
     */
    public function optimizePricingTiers(int $companyId): array
    {
        $tiers = $this->pricingTierRepository->getForCompany($companyId);
        $suggestions = [];
        
        // Check for gaps in weight ranges
        $sortedTiers = $tiers->sortBy('min_weight');
        $previousMaxWeight = 0;
        
        foreach ($sortedTiers as $tier) {
            if ($tier->min_weight > $previousMaxWeight) {
                $suggestions[] = [
                    'type' => 'gap',
                    'message' => "Gap in weight range from {$previousMaxWeight}kg to {$tier->min_weight}kg",
                    'recommendation' => 'Consider adding a tier to cover this weight range',
                ];
            }
            
            $previousMaxWeight = $tier->max_weight ?? PHP_FLOAT_MAX;
        }
        
        // Check for pricing inconsistencies
        foreach ($sortedTiers as $index => $tier) {
            $nextTier = $sortedTiers->get($index + 1);
            
            if ($nextTier && $tier->base_price > $nextTier->base_price) {
                $suggestions[] = [
                    'type' => 'pricing_inconsistency',
                    'message' => "Tier '{$tier->tier_name}' has higher base price than '{$nextTier->tier_name}'",
                    'recommendation' => 'Consider adjusting pricing to maintain logical progression',
                ];
            }
        }
        
        return [
            'total_tiers' => $tiers->count(),
            'active_tiers' => $tiers->where('status', 'active')->count(),
            'suggestions' => $suggestions,
            'coverage_analysis' => $this->analyzeCoverage($tiers),
        ];
    }

    /**
     * Analyze weight coverage
     */
    private function analyzeCoverage(Collection $tiers): array
    {
        if ($tiers->isEmpty()) {
            return [
                'min_covered_weight' => 0,
                'max_covered_weight' => 0,
                'total_coverage' => '0kg - 0kg',
                'gaps' => ['No pricing tiers defined'],
            ];
        }
        
        $sortedTiers = $tiers->sortBy('min_weight');
        $minWeight = $sortedTiers->first()->min_weight;
        $maxWeight = $sortedTiers->last()->max_weight ?? 'unlimited';
        
        return [
            'min_covered_weight' => $minWeight,
            'max_covered_weight' => $maxWeight,
            'total_coverage' => $minWeight . 'kg - ' . ($maxWeight === null ? 'unlimited' : $maxWeight . 'kg'),
            'tiers_count' => $tiers->count(),
        ];
    }

    /**
     * Generate sample pricing table
     */
    public function generateSamplePricingTable(int $companyId): array
    {
        $sampleWeights = [0.5, 1, 2, 3, 5, 10, 15, 20, 25, 50];
        $pricingTable = [];
        
        foreach ($sampleWeights as $weight) {
            $calculation = $this->calculateDeliveryPrice($weight, $companyId);
            
            $pricingTable[] = [
                'weight' => $weight . 'kg',
                'tier' => $calculation['tier']?->tier_name ?? 'No tier',
                'price' => 'Rs. ' . number_format($calculation['price'], 2),
                'base_price' => $calculation['breakdown']['base_price'] ?? 0,
                'additional_price' => $calculation['breakdown']['additional_price'] ?? 0,
            ];
        }
        
        return $pricingTable;
    }
}