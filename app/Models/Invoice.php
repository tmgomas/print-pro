<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'created_by',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'weight_charge',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'total_weight',
        'status',
        'payment_status',
        'notes',
        'terms_conditions',
        'metadata',
    ];

    protected $casts = [
        'line_weight' => 'decimal:3',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'weight_charge' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_weight' => 'decimal:3',
        'metadata' => 'json',
        

    ];

    protected $attributes = [
        'status' => 'draft',
        'payment_status' => 'pending',
        'subtotal' => 0.00,
        'weight_charge' => 0.00,
        'tax_amount' => 0.00,
        'discount_amount' => 0.00,
        'total_amount' => 0.00,
        'total_weight' => 0.000,
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Optional relationships - create these models later if needed
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function invoiceTracking(): HasMany
    {
        return $this->hasMany(InvoiceTracking::class);
    }

    public function paymentReminders(): HasMany
    {
        return $this->hasMany(PaymentReminder::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }

    public function paymentNotifications(): HasMany
    {
        return $this->hasMany(PaymentNotification::class);
    }

    // Scopes
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('payment_status', '!=', 'paid');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors & Mutators
    public function getFormattedTotalAttribute(): string
    {
        return 'Rs. ' . number_format($this->total_amount, 2);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rs. ' . number_format($this->subtotal, 2);
    }

    public function getFormattedWeightChargeAttribute(): string
    {
        return 'Rs. ' . number_format($this->weight_charge, 2);
    }

    public function getFormattedTaxAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->tax_amount, 2);
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->discount_amount, 2);
    }

    public function getFormattedWeightAttribute(): string
    {
        return number_format($this->total_weight, 2) . ' kg';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date < now() && $this->payment_status !== 'paid';
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }
        return $this->due_date->diffInDays(now());
    }

    public function getRemainingAmountAttribute(): float
    {
        $totalPaid = $this->payments()->where('status', 'completed')->sum('amount');
        return max(0, $this->total_amount - $totalPaid);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'Pending Payment',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'refunded' => 'Refunded',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'red',
            'partially_paid' => 'yellow',
            'paid' => 'green',
            'refunded' => 'gray',
            default => 'gray'
        };
    }

    // Methods
    public function calculateTotals(): void
    {
        $this->load('items');
        
        $this->subtotal = $this->items->sum('line_total');
        $this->total_weight = $this->items->sum('line_weight');
        
        // Calculate weight-based delivery charge
        $this->weight_charge = $this->calculateWeightCharge();
        
        // Calculate tax (default 12% or company's tax rate)
        $taxRate = $this->company->tax_rate ?? 0.12;
        $taxableAmount = $this->subtotal + $this->weight_charge - $this->discount_amount;
        $this->tax_amount = $taxableAmount * $taxRate;
        
        // Calculate final total
        $this->total_amount = $this->subtotal + $this->weight_charge + $this->tax_amount - $this->discount_amount;
    }

    private function calculateWeightCharge(): float
    {
        $weight = $this->total_weight;
        
        // Check if company has custom weight pricing tiers
        $weightTier = WeightPricingTier::where('company_id', $this->company_id)
            ->where('status', 'active')
            ->where('min_weight', '<=', $weight)
            ->where(function($query) use ($weight) {
                $query->where('max_weight', '>=', $weight)
                      ->orWhereNull('max_weight');
            })
            ->orderBy('min_weight', 'desc')
            ->first();

        if ($weightTier) {
            $basePrice = $weightTier->base_price;
            $extraWeight = max(0, $weight - $weightTier->min_weight);
            return $basePrice + ($extraWeight * $weightTier->price_per_kg);
        }

        // Default weight-based pricing if no custom tiers
        if ($weight <= 1) {
            return 200; // Light (0-1kg): Rs. 200 flat rate
        } elseif ($weight <= 3) {
            return 300; // Medium (1-3kg): Rs. 300 flat rate
        } elseif ($weight <= 5) {
            return 400; // Heavy (3-5kg): Rs. 400 flat rate
        } elseif ($weight <= 10) {
            return 500 + (($weight - 5) * 50); // Extra Heavy (5-10kg): Rs. 500 + additional charges
        } else {
            return 750 + (($weight - 10) * 75); // Bulk (10kg+): Rs. 750 + per kg charges
        }
    }

    public function markAsPaid(): bool
    {
        return $this->update([
            'payment_status' => 'paid',
            'status' => $this->status === 'draft' ? 'completed' : $this->status
        ]);
    }

    public function markAsPartiallyPaid(): bool
    {
        return $this->update(['payment_status' => 'partially_paid']);
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->total_paid;
        
        if ($totalPaid <= 0) {
            $this->payment_status = 'pending';
        } elseif ($totalPaid >= $this->total_amount) {
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'partially_paid';
        }
        
        $this->saveQuietly();
    }

    public function canBeDeleted(): bool
    {
        return $this->status === 'draft' && $this->payments()->count() === 0;
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, ['draft', 'pending']) && $this->payments()->count() === 0;
    }

    public function generateInvoiceNumber(): string
    {
        if ($this->branch) {
            return $this->branch->generateInvoiceNumber();
        }
        
        // Fallback if branch relationship not loaded
        $branch = Branch::find($this->branch_id);
        return $branch ? $branch->generateInvoiceNumber() : 'INV-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    // Boot method for auto-generation and calculations
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            // Generate invoice number if not provided
            if (empty($invoice->invoice_number)) {
                $branch = Branch::find($invoice->branch_id);
                if ($branch) {
                    $invoice->invoice_number = $branch->generateInvoiceNumber();
                }
            }
            
            // Set invoice date if not provided
            if (empty($invoice->invoice_date)) {
                $invoice->invoice_date = now()->toDateString();
            }
            
            // Set due date if not provided (30 days from invoice date)
            if (empty($invoice->due_date)) {
                $invoiceDate = Carbon::parse($invoice->invoice_date);
                $invoice->due_date = $invoiceDate->addDays(30)->toDateString();
            }
        });

        static::saved(function ($invoice) {
            // Auto-calculate totals when invoice is saved
            if ($invoice->wasChanged(['discount_amount']) || $invoice->wasRecentlyCreated) {
                $invoice->calculateTotals();
                if ($invoice->wasChanged(['subtotal', 'weight_charge', 'tax_amount', 'total_amount'])) {
                    $invoice->saveQuietly(); // Prevent infinite loop
                }
            }
        });
    }
}