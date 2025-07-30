<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_code',
        'name',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'city',
        'postal_code',
        'district',
        'province',
        'tax_number',
        'credit_limit',
        'current_balance',
        'status',
        'customer_type',
        'date_of_birth',
        'company_name',
        'contact_person',
        'notes',
        'preferences',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'status' => 'string',
        'customer_type' => 'string',
        'date_of_birth' => 'date',
        'preferences' => 'json',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeSearchByName($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('customer_code', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        if ($this->customer_type === 'business' && $this->company_name) {
            return $this->company_name . ' (' . $this->name . ')';
        }
        
        return $this->name;
    }

    public function getFormattedCreditLimitAttribute(): string
    {
        return 'Rs. ' . number_format($this->credit_limit, 2);
    }

    public function getFormattedBalanceAttribute(): string
    {
        return 'Rs. ' . number_format($this->current_balance, 2);
    }

    public function getAvailableCreditAttribute(): float
    {
        return $this->credit_limit - $this->current_balance;
    }

    public function getFormattedAvailableCreditAttribute(): string
    {
        return 'Rs. ' . number_format($this->getAvailableCreditAttribute(), 2);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null;
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasAvailableCredit(float $amount = 0): bool
    {
        return $this->getAvailableCreditAttribute() >= $amount;
    }

    public function getTotalInvoiceAmount(): float
    {
        return $this->invoices()->sum('total_amount');
    }

    public function getTotalPaidAmount(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getOutstandingBalance(): float
    {
        return $this->getTotalInvoiceAmount() - $this->getTotalPaidAmount();
    }

    public function getPreference(string $key): mixed
    {
        return $this->preferences[$key] ?? null;
    }

    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
    }
}