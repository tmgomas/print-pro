<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PaymentVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'verified_by',
        'customer_id',
        'verification_method',
        'bank_reference',
        'bank_name',
        'verification_notes',
        'bank_slip_image',
        'claimed_amount',
        'payment_claimed_date',
        'verified_at',
        'verification_status',
        'rejection_reason',
    ];

    protected $casts = [
        'claimed_amount' => 'decimal:2',
        'payment_claimed_date' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $attributes = [
        'verification_status' => 'pending',
        'claimed_amount' => 0.00,
    ];

    // Relationships
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Accessors
    public function getFormattedClaimedAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->claimed_amount, 2);
    }

    public function getBankSlipImageUrlAttribute(): ?string
    {
        return $this->bank_slip_image ? Storage::url($this->bank_slip_image) : null;
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

    // Scopes
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }

    // Methods
    public function verify(int $verifiedBy): bool
    {
        return $this->update([
            'verification_status' => 'verified',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);
    }

    public function reject(int $rejectedBy, string $reason): bool
    {
        return $this->update([
            'verification_status' => 'rejected',
            'verified_by' => $rejectedBy,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}