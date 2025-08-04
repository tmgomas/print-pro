<?php
// app/Models/Delivery.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'assigned_to',
        'tracking_number',
        'delivery_address',
        'contact_person',
        'contact_phone',
        'delivery_method',
        'external_tracking_id',
        'delivery_provider',
        'delivery_cost',
        'status',
        'estimated_delivery_date',
        'actual_delivery_datetime',
        'pickup_datetime',
        'delivery_notes',
        'delivery_proof',
        'latitude',
        'longitude',
        'delivery_image',
        'customer_feedback',
        'delivery_attempts',
        'last_attempt_at',
    ];

    protected $casts = [
        'estimated_delivery_date' => 'date',
        'actual_delivery_datetime' => 'datetime',
        'pickup_datetime' => 'datetime',
        'last_attempt_at' => 'datetime',
        'delivery_proof' => 'json',
        'delivery_cost' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'delivery_attempts' => 'integer',
    ];

    protected $attributes = [
        'status' => 'pending',
        'delivery_method' => 'internal',
        'delivery_cost' => 0.00,
        'delivery_attempts' => 0,
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->orderBy('status_datetime', 'desc');
    }

    // Scopes
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', ['assigned', 'picked_up', 'in_transit', 'out_for_delivery']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', ['failed', 'returned', 'cancelled']);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByDeliveryMethod(Builder $query, string $method): Builder
    {
        return $query->where('delivery_method', $method);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('estimated_delivery_date', '<', now()->toDateString())
                    ->whereNotIn('status', ['delivered', 'cancelled', 'returned']);
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getDeliveryMethodLabelAttribute(): string
    {
        return match($this->delivery_method) {
            'internal' => 'Internal Delivery',
            'external' => 'External Courier',
            'pickup' => 'Customer Pickup',
            'courier' => 'Third-party Courier',
            default => ucfirst(str_replace('_', ' ', $this->delivery_method))
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'assigned' => 'blue',
            'picked_up' => 'indigo',
            'in_transit' => 'purple',
            'out_for_delivery' => 'orange',
            'delivered' => 'green',
            'failed' => 'red',
            'returned' => 'pink',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    public function getFormattedCostAttribute(): string
    {
        return 'Rs. ' . number_format($this->delivery_cost, 2);
    }

    public function getDeliveryImageUrlAttribute(): ?string
    {
        return $this->delivery_image ? Storage::url($this->delivery_image) : null;
    }

    public function getFormattedEstimatedDateAttribute(): ?string
    {
        return $this->estimated_delivery_date?->format('Y-m-d');
    }

    public function getFormattedActualDateAttribute(): ?string
    {
        return $this->actual_delivery_datetime?->format('Y-m-d H:i:s');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->estimated_delivery_date && 
               $this->estimated_delivery_date->isPast() && 
               !in_array($this->status, ['delivered', 'cancelled', 'returned']);
    }

    public function getIsInProgressAttribute(): bool
    {
        return in_array($this->status, ['assigned', 'picked_up', 'in_transit', 'out_for_delivery']);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    public function getIsFailedAttribute(): bool
    {
        return in_array($this->status, ['failed', 'returned', 'cancelled']);
    }

    public function getDaysUntilDeliveryAttribute(): ?int
    {
        if (!$this->estimated_delivery_date) {
            return null;
        }
        
        return now()->diffInDays($this->estimated_delivery_date, false);
    }

    // Methods
    public function updateStatus(string $status, ?int $updatedBy = null, ?string $notes = null, ?array $locationData = null): bool
    {
        $oldStatus = $this->status;
        
        // Update delivery status
        $result = $this->update([
            'status' => $status,
            'last_attempt_at' => now(),
        ]);

        if ($result) {
            // Record status history
            $this->statusHistory()->create([
                'status' => $status,
                'updated_by' => $updatedBy ?: auth()->id(),
                'notes' => $notes,
                'location_data' => $locationData,
                'status_datetime' => now(),
            ]);

            // Update specific timestamps based on status
            $this->updateStatusTimestamps($status);

            // Fire events or notifications if needed
            $this->fireStatusChangeEvent($oldStatus, $status);
        }

        return $result;
    }

    private function updateStatusTimestamps(string $status): void
    {
        $updates = [];

        switch ($status) {
            case 'picked_up':
                $updates['pickup_datetime'] = now();
                break;
            case 'delivered':
                $updates['actual_delivery_datetime'] = now();
                break;
            case 'failed':
            case 'returned':
                $updates['delivery_attempts'] = $this->delivery_attempts + 1;
                break;
        }

        if (!empty($updates)) {
            $this->update($updates);
        }
    }

    private function fireStatusChangeEvent(string $oldStatus, string $newStatus): void
    {
        // Here you can fire events, send notifications, etc.
        // Example: event(new DeliveryStatusChanged($this, $oldStatus, $newStatus));
    }

    public function assignTo(int $userId, ?string $notes = null): bool
    {
        $result = $this->update([
            'assigned_to' => $userId,
            'status' => 'assigned',
        ]);

        if ($result) {
            $this->updateStatus('assigned', auth()->id(), $notes);
        }

        return $result;
    }

    public function markAsPickedUp(?string $notes = null, ?array $locationData = null): bool
    {
        return $this->updateStatus('picked_up', auth()->id(), $notes, $locationData);
    }

    public function markAsInTransit(?string $notes = null, ?array $locationData = null): bool
    {
        return $this->updateStatus('in_transit', auth()->id(), $notes, $locationData);
    }

    public function markAsOutForDelivery(?string $notes = null, ?array $locationData = null): bool
    {
        return $this->updateStatus('out_for_delivery', auth()->id(), $notes, $locationData);
    }

    public function markAsDelivered(?string $notes = null, ?array $deliveryProof = null, ?string $customerFeedback = null): bool
    {
        $updates = [
            'status' => 'delivered',
            'actual_delivery_datetime' => now(),
        ];

        if ($deliveryProof) {
            $updates['delivery_proof'] = $deliveryProof;
        }

        if ($customerFeedback) {
            $updates['customer_feedback'] = $customerFeedback;
        }

        $result = $this->update($updates);

        if ($result) {
            $this->statusHistory()->create([
                'status' => 'delivered',
                'updated_by' => auth()->id(),
                'notes' => $notes,
                'status_datetime' => now(),
            ]);

            // Update invoice delivery status if needed
            if ($this->invoice) {
                $this->invoice->update(['delivery_status' => 'delivered']);
            }
        }

        return $result;
    }

    public function markAsFailed(?string $notes = null, ?array $locationData = null): bool
    {
        return $this->updateStatus('failed', auth()->id(), $notes, $locationData);
    }

    public function cancel(?string $notes = null): bool
    {
        return $this->updateStatus('cancelled', auth()->id(), $notes);
    }

    public function generateTrackingNumber(): string
    {
        $prefix = 'DEL';
        $branchCode = $this->branch?->branch_code ?? 'B001';
        $timestamp = now()->format('ymdHis');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$branchCode}-{$timestamp}-{$random}";
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            if (empty($delivery->tracking_number)) {
                $delivery->tracking_number = $delivery->generateTrackingNumber();
            }
        });
    }
}