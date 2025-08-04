<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'item_description',
        'quantity',
        'unit_price',
        'unit_weight',
        'line_total',
        'line_weight',
        'tax_amount',
        'specifications',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'unit_weight' => 'decimal:3',
        'line_total' => 'decimal:2',
        'line_weight' => 'decimal:3',
        'tax_amount' => 'decimal:2',
        'specifications' => 'json',
    ];

    protected $attributes = [
        'quantity' => 1.00,
        'unit_price' => 0.00,
        'unit_weight' => 0.000,
        'line_total' => 0.00,
        'line_weight' => 0.000,
        'tax_amount' => 0.00,
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors & Mutators
    public function getFormattedTotalAttribute(): string
    {
        return 'Rs. ' . number_format($this->line_total, 2);
    }

    public function getFormattedUnitPriceAttribute(): string
    {
        return 'Rs. ' . number_format($this->unit_price, 2);
    }

    public function getFormattedWeightAttribute(): string
    {
        return number_format($this->line_weight, 2) . ' kg';
    }

    public function getFormattedUnitWeightAttribute(): string
    {
        return number_format($this->unit_weight, 3) . ' kg';
    }

    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity, 2);
    }

    public function getFormattedTaxAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->tax_amount, 2);
    }

    public function getTotalWithTaxAttribute(): float
    {
        return $this->line_total + $this->tax_amount;
    }

    public function getFormattedTotalWithTaxAttribute(): string
    {
        return 'Rs. ' . number_format($this->total_with_tax, 2);
    }

    public function getUnitPriceWithTaxAttribute(): float
    {
        if ($this->quantity > 0) {
            $taxPerUnit = $this->tax_amount / $this->quantity;
            return $this->unit_price + $taxPerUnit;
        }
        return $this->unit_price;
    }

    public function getFormattedUnitPriceWithTaxAttribute(): string
    {
        return 'Rs. ' . number_format($this->unit_price_with_tax, 2);
    }

    // Methods
    public function calculateLineTotal(): void
    {
        // Calculate basic line total
        $this->line_total = $this->quantity * $this->unit_price;
        $this->line_weight = $this->quantity * $this->unit_weight;
        
        // Calculate tax amount if product has tax rate
        if ($this->product && $this->product->tax_rate > 0) {
            $this->tax_amount = $this->line_total * ($this->product->tax_rate / 100);
        } else {
            $this->tax_amount = 0;
        }
    }

    public function updateFromProduct(): void
{
    if ($this->product) {
        // Update description if not manually set
        if (empty($this->item_description)) {
            $this->item_description = $this->product->name;
        }
        
        // Update unit price if not manually set
        if ($this->unit_price == 0) {
            $this->unit_price = $this->product->base_price;
        }
        
        // 🔥 FIX: Convert weight to kg based on weight_unit
        if ($this->unit_weight == 0) {
            $weightInKg = $this->product->weight_per_unit;
            
            // Convert to kg based on weight_unit
            switch ($this->product->weight_unit) {
                case 'grams':
                case 'g':
                    $weightInKg = $this->product->weight_per_unit / 1000;
                    break;
                case 'lb':
                    $weightInKg = $this->product->weight_per_unit * 0.453592;
                    break;
                case 'oz':
                    $weightInKg = $this->product->weight_per_unit * 0.0283495;
                    break;
                case 'kg':
                default:
                    // Already in kg, no conversion needed
                    $weightInKg = $this->product->weight_per_unit;
                    break;
            }
            
            $this->unit_weight = $weightInKg;
        }
    }
}
    public function setCustomSpecification(string $key, $value): void
    {
        $specs = $this->specifications ?? [];
        $specs[$key] = $value;
        $this->specifications = $specs;
    }

    public function getCustomSpecification(string $key, $default = null)
    {
        return $this->specifications[$key] ?? $default;
    }

    public function hasCustomSpecifications(): bool
    {
        return !empty($this->specifications) && is_array($this->specifications);
    }

    public function getSpecificationsSummary(): string
    {
        if (!$this->hasCustomSpecifications()) {
            return '';
        }

        $summary = [];
        foreach ($this->specifications as $key => $value) {
            $summary[] = ucfirst($key) . ': ' . $value;
        }

        return implode(', ', $summary);
    }

    // Scopes
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithProduct($query)
    {
        return $query->with('product:id,name,product_code,base_price,weight_per_unit,tax_rate,image');
    }

    public function scopeWithInvoice($query)
    {
        return $query->with('invoice:id,invoice_number,customer_id,total_amount');
    }

    public function scopeOrderByCreated($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Validation methods
    public function validateQuantity(): bool
    {
        if ($this->quantity <= 0) {
            return false;
        }

        // Check product minimum/maximum quantity if set
        if ($this->product) {
            if ($this->product->minimum_quantity && $this->quantity < $this->product->minimum_quantity) {
                return false;
            }
            
            if ($this->product->maximum_quantity && $this->quantity > $this->product->maximum_quantity) {
                return false;
            }
        }

        return true;
    }

    public function validatePrice(): bool
    {
        return $this->unit_price >= 0;
    }

    public function validateWeight(): bool
    {
        return $this->unit_weight >= 0;
    }

    public function isValid(): bool
    {
        return $this->validateQuantity() && $this->validatePrice() && $this->validateWeight();
    }

    // Boot method for auto-calculations
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Load product relationship and update item from product
            if ($item->product_id && !$item->relationLoaded('product')) {
                $item->load('product');
            }
            
            $item->updateFromProduct();
            $item->calculateLineTotal();
        });

        static::updating(function ($item) {
            // Only auto-calculate if quantity, unit_price, or unit_weight changed
            if ($item->isDirty(['quantity', 'unit_price', 'unit_weight'])) {
                $item->calculateLineTotal();
            }
        });

        static::saved(function ($item) {
            // Update invoice totals when item is saved
            if ($item->invoice) {
                $item->invoice->calculateTotals();
                $item->invoice->saveQuietly();
            }
        });

        static::deleted(function ($item) {
            // Update invoice totals when item is deleted
            if ($item->invoice) {
                $item->invoice->calculateTotals();
                $item->invoice->saveQuietly();
            }
        });
    }
}