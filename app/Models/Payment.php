<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'customer_id',
        'received_by',
        'payment_reference',
        'amount',
        'payment_date',
        'payment_method',
        'bank_name',
        'gateway_reference',
        'transaction_id',
        'cheque_number',
        'status',
        'verification_status',
        'notes',
        'payment_metadata',
        'receipt_image',
        'verified_at',
        'verified_by',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'verified_at' => 'datetime',
        'payment_metadata' => 'json',
    ];

    protected $attributes = [
        'status' => 'pending',
        'verification_status' => 'pending',
        'amount' => 0.00,
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }

    // Scopes
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByVerificationStatus($query, $verificationStatus)
    {
        return $query->where('verification_status', $verificationStatus);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereYear('payment_date', now()->year)
                    ->whereMonth('payment_date', now()->month);
    }

    // Accessors & Mutators
    public function getFormattedAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->amount, 2);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online Payment',
            'card' => 'Card Payment',
            'cheque' => 'Cheque',
            'mobile_payment' => 'Mobile Payment',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            default => 'Unknown'
        };
    }

    public function getVerificationStatusLabelAttribute(): string
    {
        return match($this->verification_status) {
            'pending' => 'Pending Verification',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'cancelled' => 'gray',
            'refunded' => 'purple',
            default => 'gray'
        };
    }

    public function getVerificationStatusColorAttribute(): string
    {
        return match($this->verification_status) {
            'pending' => 'yellow',
            'verified' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }

    public function getReceiptImageUrlAttribute(): ?string
    {
        return $this->receipt_image ? Storage::url($this->receipt_image) : null;
    }

    public function getFormattedPaymentDateAttribute(): string
    {
        return $this->payment_date->format('Y-m-d H:i:s');
    }

    public function getFormattedVerifiedAtAttribute(): ?string
    {
        return $this->verified_at?->format('Y-m-d H:i:s');
    }

    public function getDaysFromPaymentAttribute(): int
    {
        return $this->payment_date->diffInDays(now());
    }

    // Methods
    public function markAsCompleted(): bool
    {
        $result = $this->update([
            'status' => 'completed',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        if ($result && $this->invoice) {
            $this->invoice->updatePaymentStatus();
        }

        return $result;
    }

    public function markAsVerified(int $verifiedBy): bool
    {
        $result = $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'status' => $this->status === 'pending' ? 'completed' : $this->status,
        ]);

        if ($result && $this->invoice) {
            $this->invoice->updatePaymentStatus();
        }

        return $result;
    }

    public function markAsRejected(int $rejectedBy, string $reason): bool
    {
        return $this->update([
            'verification_status' => 'rejected',
            'verified_by' => $rejectedBy,
            'verified_at' => now(),
            'rejection_reason' => $reason,
            'status' => 'failed',
        ]);
    }

    public function markAsFailed(string $reason = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'rejection_reason' => $reason,
        ]);
    }

    public function markAsCancelled(string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'rejection_reason' => $reason,
        ]);
    }

    public function refund(float $refundAmount = null, string $reason = null): bool
    {
        $refundAmount = $refundAmount ?? $this->amount;
        
        if ($refundAmount > $this->amount) {
            throw new \Exception('Refund amount cannot exceed payment amount');
        }

        $result = $this->update([
            'status' => 'refunded',
            'rejection_reason' => $reason,
            'payment_metadata' => array_merge($this->payment_metadata ?? [], [
                'refund_amount' => $refundAmount,
                'refund_date' => now()->toISOString(),
                'refund_reason' => $reason,
            ]),
        ]);

        if ($result && $this->invoice) {
            $this->invoice->updatePaymentStatus();
        }

        return $result;
    }

    public function canBeVerified(): bool
    {
        return $this->verification_status === 'pending' && 
               in_array($this->status, ['pending', 'processing']);
    }

    public function canBeRejected(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function canBeRefunded(): bool
    {
        return $this->status === 'completed' && $this->verification_status === 'verified';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rejected';
    }

    public function generatePaymentReference(): string
    {
        $prefix = 'PAY';
        $branchCode = $this->branch ? $this->branch->code : 'GEN';
        $date = now()->format('ymd');
        $counter = static::whereDate('created_at', today())->count() + 1;
        
        return $prefix . '-' . $branchCode . '-' . $date . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    public function uploadReceiptImage($file): string
    {
        $path = $file->store('payment-receipts', 'public');
        $this->update(['receipt_image' => $path]);
        return $path;
    }

    public function deleteReceiptImage(): void
    {
        if ($this->receipt_image && Storage::disk('public')->exists($this->receipt_image)) {
            Storage::disk('public')->delete($this->receipt_image);
            $this->update(['receipt_image' => null]);
        }
    }

    public function getPaymentSummary(): array
    {
        return [
            'reference' => $this->payment_reference,
            'amount' => $this->formatted_amount,
            'date' => $this->formatted_payment_date,
            'method' => $this->payment_method_label,
            'status' => $this->status_label,
            'verification' => $this->verification_status_label,
            'invoice_number' => $this->invoice?->invoice_number,
            'customer_name' => $this->customer?->name ?? $this->invoice?->customer?->name,
        ];
    }

    // Boot method for auto-generation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            // Generate payment reference if not provided
            if (empty($payment->payment_reference)) {
                $payment->payment_reference = $payment->generatePaymentReference();
            }

            // Set customer_id from invoice if not provided
            if (empty($payment->customer_id) && $payment->invoice_id) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $payment->customer_id = $invoice->customer_id;
                }
            }

            // Set payment_date if not provided
            if (empty($payment->payment_date)) {
                $payment->payment_date = now();
            }
        });

        static::saved(function ($payment) {
            // Update invoice payment status when payment is saved
            if ($payment->invoice && $payment->wasChanged(['status', 'verification_status', 'amount'])) {
                $payment->invoice->updatePaymentStatus();
            }
        });

        static::deleted(function ($payment) {
            // Update invoice payment status when payment is deleted
            if ($payment->invoice) {
                $payment->invoice->updatePaymentStatus();
            }
        });
    }
}