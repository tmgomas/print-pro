<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_main_branch',
        'status',
        'settings',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_main_branch' => 'boolean',
        'settings' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $attributes = [
        'is_main_branch' => false,
        'status' => 'active',
    ];

    /**
     * Get the company that owns the branch
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch's users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get active users for this branch
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->where('status', 'active');
    }

    /**
     * Check if branch is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if this is the main branch
     */
    public function isMainBranch(): bool
    {
        return $this->is_main_branch;
    }

    /**
     * Get branch setting by key
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set branch setting
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Generate invoice number with branch prefix
     */
    public function generateInvoiceNumber(): string
    {
        $lastInvoice = $this->company->invoices()
            ->where('branch_id', $this->id)
            ->latest('id')
            ->first();
            
        $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -6)) + 1 : 1;
        
        return $this->code . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for active branches
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Boot method to ensure only one main branch per company
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($branch) {
            if ($branch->is_main_branch) {
                static::where('company_id', $branch->company_id)
                    ->update(['is_main_branch' => false]);
            }
        });

        static::updating(function ($branch) {
            if ($branch->is_main_branch && $branch->isDirty('is_main_branch')) {
                static::where('company_id', $branch->company_id)
                    ->where('id', '!=', $branch->id)
                    ->update(['is_main_branch' => false]);
            }
        });
    }
}