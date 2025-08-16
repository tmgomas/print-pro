<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'description', 'icon', 'color',
        'parent_id', 'status', 'sort_order', 'is_system_category'
    ];

    protected $casts = [
        'status' => 'string',
        'sort_order' => 'integer',
        'is_system_category' => 'boolean',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ExpenseBudget::class, 'category_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeParentCategories(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $names = collect([$this->name]);
        $parent = $this->parent;
        
        while ($parent) {
            $names->prepend($parent->name);
            $parent = $parent->parent;
        }
        
        return $names->implode(' > ');
    }

    // Static methods
    public static function generateCode(string $name, int $companyId): string
    {
        $baseCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
        $code = $baseCode;
        $counter = 1;

        while (static::where('company_id', $companyId)->where('code', $code)->exists()) {
            $code = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}