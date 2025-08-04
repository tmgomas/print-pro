<?php
// app/Models/PrintJob.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PrintJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'assigned_to',
        'job_number',
        'job_type',
        'specifications',
        'design_files',
        'production_status',
        'priority',
        'quantity',
        'estimated_cost',
        'actual_cost',
        'estimated_completion',
        'actual_completion',
        'started_at',
        'production_notes',
        'quality_check_data',
        'batch_number',
        'completion_percentage',
        'special_instructions',
        'material_requirements',
    ];

    protected $casts = [
        'specifications' => 'json',
        'design_files' => 'json',
        'quality_check_data' => 'json',
        'material_requirements' => 'json',
        'estimated_completion' => 'datetime',
        'actual_completion' => 'datetime',
        'started_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'quantity' => 'integer',
        'completion_percentage' => 'integer',
    ];

    protected $attributes = [
        'production_status' => 'pending',
        'priority' => 'normal',
        'quantity' => 1,
        'estimated_cost' => 0.00,
        'completion_percentage' => 0,
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

    public function productionStages(): HasMany
    {
        return $this->hasMany(ProductionStage::class)->orderBy('stage_order');
    }

    public function currentStage(): HasMany
    {
        return $this->hasMany(ProductionStage::class)
                    ->where('stage_status', 'in_progress')
                    ->orderBy('stage_order');
    }

    public function completedStages(): HasMany
    {
        return $this->hasMany(ProductionStage::class)
                    ->where('stage_status', 'completed')
                    ->orderBy('stage_order');
    }

    // Scopes
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('production_status', $status);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeByJobType(Builder $query, string $jobType): Builder
    {
        return $query->where('job_type', $jobType);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('production_status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('production_status', [
            'design_review', 'design_approved', 'pre_press', 'printing', 'finishing'
        ]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('production_status', 'completed');
    }

    public function scopeOnHold(Builder $query): Builder
    {
        return $query->where('production_status', 'on_hold');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('estimated_completion', '<', now())
                    ->whereNotIn('production_status', ['completed', 'cancelled']);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')");
    }

    // Accessors
    public function getProductionStatusLabelAttribute(): string
    {
        return match($this->production_status) {
            'pending' => 'Pending',
            'design_review' => 'Design Review',
            'design_approved' => 'Design Approved',
            'pre_press' => 'Pre-Press',
            'printing' => 'Printing',
            'finishing' => 'Finishing',
            'quality_check' => 'Quality Check',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
            default => 'Normal'
        };
    }

    public function getJobTypeLabelAttribute(): string
    {
        return match($this->job_type) {
            'business_cards' => 'Business Cards',
            'brochures' => 'Brochures',
            'flyers' => 'Flyers',
            'posters' => 'Posters',
            'banners' => 'Banners',
            'stickers' => 'Stickers',
            'letterheads' => 'Letterheads',
            'envelopes' => 'Envelopes',
            'books' => 'Books',
            'magazines' => 'Magazines',
            'packaging' => 'Packaging',
            'labels' => 'Labels',
            'custom' => 'Custom',
            default => ucfirst(str_replace('_', ' ', $this->job_type))
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->production_status) {
            'pending' => 'yellow',
            'design_review' => 'blue',
            'design_approved' => 'green',
            'pre_press' => 'indigo',
            'printing' => 'purple',
            'finishing' => 'pink',
            'quality_check' => 'orange',
            'completed' => 'green',
            'on_hold' => 'gray',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'green',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue'
        };
    }

    public function getFormattedEstimatedCostAttribute(): string
    {
        return 'Rs. ' . number_format($this->estimated_cost, 2);
    }

    public function getFormattedActualCostAttribute(): string
    {
        return $this->actual_cost ? 'Rs. ' . number_format($this->actual_cost, 2) : 'N/A';
    }

    public function getFormattedEstimatedCompletionAttribute(): ?string
    {
        return $this->estimated_completion?->format('Y-m-d H:i:s');
    }

    public function getFormattedActualCompletionAttribute(): ?string
    {
        return $this->actual_completion?->format('Y-m-d H:i:s');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->estimated_completion && 
               $this->estimated_completion->isPast() && 
               !in_array($this->production_status, ['completed', 'cancelled']);
    }

    public function getIsInProgressAttribute(): bool
    {
        return in_array($this->production_status, [
            'design_review', 'design_approved', 'pre_press', 'printing', 'finishing', 'quality_check'
        ]);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->production_status === 'completed';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->production_status === 'cancelled';
    }

    public function getIsOnHoldAttribute(): bool
    {
        return $this->production_status === 'on_hold';
    }

    public function getDaysUntilCompletionAttribute(): ?int
    {
        if (!$this->estimated_completion) {
            return null;
        }
        
        return now()->diffInDays($this->estimated_completion, false);
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->completion_percentage > 0) {
            return $this->completion_percentage;
        }

        // Calculate based on completed stages
        $totalStages = $this->productionStages()->count();
        $completedStages = $this->completedStages()->count();
        
        return $totalStages > 0 ? (int) (($completedStages / $totalStages) * 100) : 0;
    }

    public function getDurationInHoursAttribute(): ?float
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->actual_completion ?: now();
        return $this->started_at->diffInHours($endTime, true);
    }

    // Methods
    public function updateStatus(string $status, ?string $notes = null): bool
    {
        $oldStatus = $this->production_status;
        
        $updates = [
            'production_status' => $status,
        ];

        // Handle status-specific updates
        switch ($status) {
            case 'design_review':
            case 'pre_press':
            case 'printing':
                if (!$this->started_at) {
                    $updates['started_at'] = now();
                }
                break;
            case 'completed':
                $updates['actual_completion'] = now();
                $updates['completion_percentage'] = 100;
                break;
            case 'cancelled':
                // Don't update completion date for cancelled jobs
                break;
        }

        if ($notes) {
            $updates['production_notes'] = $this->production_notes . "\n" . now()->format('Y-m-d H:i:s') . ": " . $notes;
        }

        $result = $this->update($updates);

        if ($result) {
            // Fire events or notifications
            $this->fireStatusChangeEvent($oldStatus, $status);
        }

        return $result;
    }

    private function fireStatusChangeEvent(string $oldStatus, string $newStatus): void
    {
        // Here you can fire events, send notifications, etc.
        // Example: event(new PrintJobStatusChanged($this, $oldStatus, $newStatus));
    }

    public function assignTo(int $userId, ?string $notes = null): bool
    {
        $result = $this->update([
            'assigned_to' => $userId,
        ]);

        if ($result && $notes) {
            $this->update([
                'production_notes' => $this->production_notes . "\n" . now()->format('Y-m-d H:i:s') . ": Assigned to user #{$userId}. " . $notes
            ]);
        }

        return $result;
    }

    public function setPriority(string $priority, ?string $reason = null): bool
    {
        $oldPriority = $this->priority;
        
        $result = $this->update(['priority' => $priority]);

        if ($result && $reason) {
            $this->update([
                'production_notes' => $this->production_notes . "\n" . now()->format('Y-m-d H:i:s') . ": Priority changed from {$oldPriority} to {$priority}. Reason: " . $reason
            ]);
        }

        return $result;
    }

    public function putOnHold(?string $reason = null): bool
    {
        return $this->updateStatus('on_hold', $reason ? "Put on hold: " . $reason : null);
    }

    public function resume(?string $notes = null): bool
    {
        $previousStatus = 'design_review'; // Default to design review when resuming
        
        // Try to determine the previous status from notes or stages
        if ($this->productionStages()->exists()) {
            $lastCompletedStage = $this->productionStages()
                                       ->where('stage_status', 'completed')
                                       ->orderBy('stage_order', 'desc')
                                       ->first();
            
            if ($lastCompletedStage) {
                $previousStatus = $this->getNextStatusFromStage($lastCompletedStage->stage_name);
            }
        }
        
        return $this->updateStatus($previousStatus, $notes ? "Resumed: " . $notes : "Job resumed");
    }

    private function getNextStatusFromStage(string $stageName): string
    {
        return match($stageName) {
            'design_review', 'customer_approval' => 'design_approved',
            'pre_press_setup', 'material_preparation' => 'pre_press',
            'printing_setup', 'printing_process', 'color_matching' => 'printing',
            'cutting', 'folding', 'binding', 'laminating', 'coating' => 'finishing',
            'quality_inspection' => 'quality_check',
            default => 'design_review'
        };
    }

    public function cancel(?string $reason = null): bool
    {
        return $this->updateStatus('cancelled', $reason ? "Cancelled: " . $reason : null);
    }

    public function complete(?string $notes = null): bool
    {
        return $this->updateStatus('completed', $notes ? "Completed: " . $notes : "Job completed successfully");
    }

    public function updateProgress(int $percentage, ?string $notes = null): bool
    {
        $percentage = max(0, min(100, $percentage)); // Ensure 0-100 range
        
        $updates = ['completion_percentage' => $percentage];
        
        if ($notes) {
            $updates['production_notes'] = $this->production_notes . "\n" . now()->format('Y-m-d H:i:s') . ": Progress updated to {$percentage}%. " . $notes;
        }

        return $this->update($updates);
    }

    public function addDesignFile(string $filePath, ?string $description = null): bool
    {
        $designFiles = $this->design_files ?? [];
        
        $designFiles[] = [
            'path' => $filePath,
            'description' => $description,
            'uploaded_at' => now()->toISOString(),
            'uploaded_by' => auth()->id(),
        ];
        
        return $this->update(['design_files' => $designFiles]);
    }

    public function getDesignFileUrls(): array
    {
        if (!$this->design_files) {
            return [];
        }

        return collect($this->design_files)->map(function ($file) {
            return [
                'url' => Storage::url($file['path']),
                'description' => $file['description'] ?? null,
                'uploaded_at' => $file['uploaded_at'] ?? null,
            ];
        })->toArray();
    }

    public function generateJobNumber(): string
    {
        $prefix = 'PJ';
        $branchCode = $this->branch?->branch_code ?? 'B001';
        $year = now()->format('y');
        $month = now()->format('m');
        $sequential = str_pad(static::whereYear('created_at', now()->year)
                                   ->whereMonth('created_at', now()->month)
                                   ->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$branchCode}-{$year}{$month}-{$sequential}";
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($printJob) {
            if (empty($printJob->job_number)) {
                $printJob->job_number = $printJob->generateJobNumber();
            }
        });
    }
}