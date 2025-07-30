<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'product_code',
        'name',
        'description',
        'base_price',
        'unit_type',
        'weight_per_unit',
        'weight_unit',
        'tax_rate',
        'image',
        'specifications',
        'pricing_tiers',
        'status',
        'minimum_quantity',
        'maximum_quantity',
        'requires_customization',
        'customization_options',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'weight_per_unit' => 'decimal:3',
        'tax_rate' => 'decimal:2',
        'specifications' => 'json',
        'pricing_tiers' => 'json',
        'status' => 'string',
        'minimum_quantity' => 'integer',
        'maximum_quantity' => 'integer',
        'requires_customization' => 'boolean',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
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

    public function scopeInCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearchByName($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
    }

    // Accessors
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rs. ' . number_format($this->base_price, 2);
    }

    public function getFormattedWeightAttribute(): string
    {
        return $this->weight_per_unit . ' ' . $this->weight_unit;
    }

    public function getCategoryHierarchyAttribute(): string
    {
        return $this->category ? $this->category->getHierarchy() : '';
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function calculatePrice(int $quantity): array
    {
        $basePrice = $this->base_price * $quantity;
        $totalWeight = $this->weight_per_unit * $quantity;
        $taxAmount = $basePrice * ($this->tax_rate / 100);
        
        return [
            'base_price' => $basePrice,
            'total_weight' => $totalWeight,
            'tax_amount' => $taxAmount,
            'total_price' => $basePrice + $taxAmount,
        ];
    }

    public function getSpecification(string $key): mixed
    {
        return $this->specifications[$key] ?? null;
    }

    public function hasSpecification(string $key): bool
    {
        return isset($this->specifications[$key]);
    }
}