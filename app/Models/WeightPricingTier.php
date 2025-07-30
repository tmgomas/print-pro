<?php
// app/Models/WeightPricingTier.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightPricingTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tier_name',
        'min_weight',
        'max_weight',
        'base_price',
        'price_per_kg',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'min_weight' => 'decimal:3',
        'max_weight' => 'decimal:3',
        'base_price' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'status' => 'string',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOrderedByWeight($query)
    {
        return $query->orderBy('min_weight');
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function matches(float $weight): bool
    {
        if ($weight < $this->min_weight) {
            return false;
        }
        
        if ($this->max_weight && $weight > $this->max_weight) {
            return false;
        }
        
        return true;
    }

    public function calculatePrice(float $weight): array
    {
        $basePrice = $this->base_price;
        $additionalPrice = ($weight - $this->min_weight) * $this->price_per_kg;
        $totalPrice = $basePrice + max(0, $additionalPrice);
        
        return [
            'tier_name' => $this->tier_name,
            'base_price' => $basePrice,
            'additional_price' => $additionalPrice,
            'total_price' => $totalPrice,
            'weight' => $weight,
        ];
    }

    public function getWeightRangeAttribute(): string
    {
        if ($this->max_weight) {
            return "{$this->min_weight}kg - {$this->max_weight}kg";
        }
        
        return "{$this->min_weight}kg+";
    }
}
