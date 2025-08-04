<?php
// app/Models/DeliveryStatusHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DeliveryStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'delivery_status_history';

    public $timestamps = false; // We only use created_at

    protected $fillable = [
        'delivery_id',
        'updated_by',
        'status',
        'notes',
        'location_data',
        'image_path',
        'status_datetime',
    ];

    protected $casts = [
        'status_datetime' => 'datetime',
        'location_data' => 'json',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'status_datetime' => null,
    ];

    // Relationships
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForDelivery(Builder $query, int $deliveryId): Builder
    {
        return $query->where('delivery_id', $deliveryId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('updated_by', $userId);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('status_datetime', '>=', now()->subDays($days));
    }

    public function scopeOrderByLatest(Builder $query): Builder
    {
        return $query->orderBy('status_datetime', 'desc');
    }

    public function scopeOrderByOldest(Builder $query): Builder
    {
        return $query->orderBy('status_datetime', 'asc');
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

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->status_datetime?->format('Y-m-d H:i:s') ?? '';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->status_datetime?->format('Y-m-d') ?? '';
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->status_datetime?->format('H:i:s') ?? '';
    }

    public function getHumanReadableDateAttribute(): string
    {
        return $this->status_datetime?->diffForHumans() ?? '';
    }

    public function getLocationAddressAttribute(): ?string
    {
        return $this->location_data['address'] ?? null;
    }

    public function getLocationCoordinatesAttribute(): ?array
    {
        if (!$this->location_data) {
            return null;
        }

        return [
            'latitude' => $this->location_data['latitude'] ?? null,
            'longitude' => $this->location_data['longitude'] ?? null,
        ];
    }

    public function getHasLocationAttribute(): bool
    {
        return !empty($this->location_data) && 
               (isset($this->location_data['latitude']) || isset($this->location_data['address']));
    }

    public function getHasImageAttribute(): bool
    {
        return !empty($this->image_path);
    }

    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updatedBy?->name ?? 'System';
    }

    // Methods
    public function hasCoordinates(): bool
    {
        return $this->location_data && 
               isset($this->location_data['latitude']) && 
               isset($this->location_data['longitude']);
    }

    public function getCoordinates(): ?array
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        return [
            'lat' => (float) $this->location_data['latitude'],
            'lng' => (float) $this->location_data['longitude'],
        ];
    }

    public function setLocationFromCoordinates(float $latitude, float $longitude, ?string $address = null): void
    {
        $locationData = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => now()->toISOString(),
        ];

        if ($address) {
            $locationData['address'] = $address;
        }

        $this->location_data = $locationData;
    }

    public function addLocationNote(string $note): void
    {
        $locationData = $this->location_data ?? [];
        $locationData['notes'] = $locationData['notes'] ?? [];
        $locationData['notes'][] = [
            'note' => $note,
            'added_at' => now()->toISOString(),
        ];
        
        $this->location_data = $locationData;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($statusHistory) {
            if (empty($statusHistory->status_datetime)) {
                $statusHistory->status_datetime = now();
            }
        });
    }
}