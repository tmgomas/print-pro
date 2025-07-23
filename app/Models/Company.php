<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'registration_number',
        'address',
        'phone',
        'email',
        'logo',
        'settings',
        'status',
        'tax_rate',
        'tax_number',
        'bank_details',
    ];

    protected $casts = [
        'settings' => 'array',
        'tax_rate' => 'decimal:2',
    ];

    protected $attributes = [
        'status' => 'active',
        'tax_rate' => 0.00,
    ];

    /**
     * Get the company's branches
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Get the company's users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the main branch of the company
     */
    public function mainBranch()
    {
        return $this->branches()->where('is_main_branch', true)->first();
    }

    /**
     * Get active branches
     */
    public function activeBranches(): HasMany
    {
        return $this->branches()->where('status', 'active');
    }

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::url($this->logo) : null;
    }

    /**
     * Check if company is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get company setting by key
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set company setting
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Scope for active companies
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}