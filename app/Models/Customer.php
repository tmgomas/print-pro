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
        'age',
        'company_name',
        'company_registration',
        'contact_person',
        'contact_person_phone',
        'contact_person_email',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'notes',
        'preferences',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'status' => 'string',
        'customer_type' => 'string',
        'date_of_birth' => 'date',
        'age' => 'integer',
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

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
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
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company_name', 'like', "%{$search}%");
        });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    public function scopeIndividual($query)
    {
        return $query->where('customer_type', 'individual');
    }

    public function scopeBusiness($query)
    {
        return $query->where('customer_type', 'business');
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeByProvince($query, $province)
    {
        return $query->where('province', $province);
    }

    public function scopeWithCreditLimit($query)
    {
        return $query->where('credit_limit', '>', 0);
    }

    public function scopeWithOutstandingBalance($query)
    {
        return $query->where('current_balance', '>', 0);
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

    public function getCalculatedAgeAttribute(): ?int
    {
        return $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null;
    }

    public function getFullAddressAttribute(): string
    {
        $address = $this->billing_address;
        if ($this->city) {
            $address .= ', ' . $this->city;
        }
        if ($this->district) {
            $address .= ', ' . $this->district;
        }
        if ($this->province) {
            $address .= ', ' . $this->province;
        }
        if ($this->postal_code) {
            $address .= ' ' . $this->postal_code;
        }
        return $address;
    }

    public function getPrimaryContactAttribute(): array
    {
        if ($this->customer_type === 'business' && $this->contact_person) {
            return [
                'name' => $this->contact_person,
                'phone' => $this->contact_person_phone ?: $this->phone,
                'email' => $this->contact_person_email ?: $this->email,
            ];
        }

        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }

    public function getEmergencyContactAttribute(): ?array
    {
        if (!$this->emergency_contact_name) {
            return null;
        }

        return [
            'name' => $this->emergency_contact_name,
            'phone' => $this->emergency_contact_phone,
            'relationship' => $this->emergency_contact_relationship,
        ];
    }

    // Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isIndividual(): bool
    {
        return $this->customer_type === 'individual';
    }

    public function isBusiness(): bool
    {
        return $this->customer_type === 'business';
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

    public function updateCurrentBalance(): void
    {
        $this->current_balance = $this->getOutstandingBalance();
        $this->save();
    }

    // Preference management methods
    public function getPreference(string $key): mixed
    {
        return $this->preferences[$key] ?? null;
    }

    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        $this->save();
    }

    public function removePreference(string $key): void
    {
        $preferences = $this->preferences ?? [];
        unset($preferences[$key]);
        $this->preferences = $preferences;
        $this->save();
    }

    // Auto-update age when date_of_birth changes
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($customer) {
            // Auto-calculate age when date_of_birth is set
            if ($customer->date_of_birth && $customer->isDirty('date_of_birth')) {
                $customer->age = Carbon::parse($customer->date_of_birth)->age;
            }

            // Set shipping address same as billing if empty
            if (empty($customer->shipping_address)) {
                $customer->shipping_address = $customer->billing_address;
            }
        });
    }
}