<?php
// app/Models/ProductCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'image',
        'parent_id',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => 'string',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProductCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
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

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithChildren($query)
    {
        return $query->with('children');
    }

    // Accessors
    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::url($this->image) : null;
    }

    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getProductCountAttribute(): int
    {
        return $this->products()->active()->count();
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    public function getHierarchy(): string
    {
        $hierarchy = collect();
        $category = $this;
        
        while ($category) {
            $hierarchy->prepend($category->name);
            $category = $category->parent;
        }
        
        return $hierarchy->implode(' > ');
    }
}
