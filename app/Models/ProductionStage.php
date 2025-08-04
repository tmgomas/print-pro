<?php
// app/Models/ProductionStage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProductionStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'print_job_id',
        'updated_by',
        'stage_name',
        'stage_status',
        'started_at',
        'completed_at',
        'notes',
        'stage_data',
        'approval_status',
        'rejection_reason',
        'attachments',
        'stage_cost',
        'estimated_duration',
        'actual_duration',
        'stage_order',
        'requires_customer_approval',
        'customer_approved_at',
        'approved_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'customer_approved_at' => 'datetime',
        'stage_data' => 'json',
        'attachments' => 'json',
        'stage_cost' => 'decimal:2',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
        'stage_order' => 'integer',
        'requires_customer_approval' => 'boolean',
    ];

    protected $attributes = [
        'stage_status' => 'pending',
        'stage_order' => 0,
        'requires_customer_approval' => false,
    ];

    // Relationships
    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeForPrintJob(Builder $query, int $printJobId): Builder
    {
        return $query->where('print_job_id', $printJobId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('stage_status', $status);
    }

    public function scopeByStage(Builder $query, string $stageName): Builder
    {
        return $query->where('stage_name', $stageName);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('stage_status', 'pending');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('stage_status', 'in_progress');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('stage_status', 'completed');
    }

    public function scopeRequiresApproval(Builder $query): Builder
    {
        return $query->where('stage_status', 'requires_approval');
    }

    public function scopeRequiresCustomerApproval(Builder $query): Builder
    {
        return $query->where('requires_customer_approval', true);
    }

    public function scopeOrderedByStage(Builder $query): Builder
    {
        return $query->orderBy('stage_order');
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('updated_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getStageNameLabelAttribute(): string
    {
        return match($this->stage_name) {
            'design_review' => 'Design Review',
            'customer_approval' => 'Customer Approval',
            'pre_press_setup' => 'Pre-Press Setup',
            'material_preparation' => 'Material Preparation',
            'printing_setup' => 'Printing Setup',
            'printing_process' => 'Printing Process',
            'color_matching' => 'Color Matching',
            'first_proof' => 'First Proof',
            'customer_proof_approval' => 'Customer Proof Approval',
            'production_run' => 'Production Run',
            'cutting' => 'Cutting',
            'folding' => 'Folding',
            'binding' => 'Binding',
            'laminating' => 'Laminating',
            'coating' => 'Coating',
            'die_cutting' => 'Die Cutting',
            'embossing' => 'Embossing',
            'foil_stamping' => 'Foil Stamping',
            'quality_inspection' => 'Quality Inspection',
            'packaging' => 'Packaging',
            'final_review' => 'Final Review',
            'ready_for_delivery' => 'Ready for Delivery',
            default => ucfirst(str_replace('_', ' ', $this->stage_name))
        };
    }

    public function getStageStatusLabelAttribute(): string
    {
        return match($this->stage_status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'requires_approval' => 'Requires Approval',
            'rejected' => 'Rejected',
            'skipped' => 'Skipped',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->stage_status) {
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'completed' => 'green',
            'on_hold' => 'gray',
            'requires_approval' => 'orange',
            'rejected' => 'red',
            'skipped' => 'purple',
            default => 'gray'
        };
    }

    public function getFormattedCostAttribute(): ?string
    {
        return $this->stage_cost ? 'Rs. ' . number_format($this->stage_cost, 2) : null;
    }

    public function getFormattedStartedAtAttribute(): ?string
    {
        return $this->started_at?->format('Y-m-d H:i:s');
    }

    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at?->format('Y-m-d H:i:s');
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->actual_duration) {
            return null;
        }

        $hours = intval($this->actual_duration / 60);
        $minutes = $this->actual_duration % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        
        return $minutes . 'm';
    }

    public function getEstimatedDurationFormattedAttribute(): ?string
    {
        if (!$this->estimated_duration) {
            return null;
        }

        $hours = intval($this->estimated_duration / 60);
        $minutes = $this->estimated_duration % 60;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        
        return $minutes . 'm';
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->started_at || !$this->estimated_duration) {
            return false;
        }

        $expectedCompletion = $this->started_at->addMinutes($this->estimated_duration);
        return $expectedCompletion->isPast() && $this->stage_status !== 'completed';
    }

    public function getIsInProgressAttribute(): bool
    {
        return $this->stage_status === 'in_progress';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->stage_status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->stage_status === 'pending';
    }

    public function getIsOnHoldAttribute(): bool
    {
        return $this->stage_status === 'on_hold';
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->stage_status === 'rejected';
    }

    public function getRequiresApprovalAttribute(): bool
    {
        return $this->stage_status === 'requires_approval';
    }

    public function getHasAttachmentsAttribute(): bool
    {
        return !empty($this->attachments);
    }

    public function getAttachmentUrlsAttribute(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return collect($this->attachments)->map(function ($attachment) {
            return [
                'url' => Storage::url($attachment['path']),
                'type' => $attachment['type'] ?? 'file',
                'description' => $attachment['description'] ?? null,
                'uploaded_at' => $attachment['uploaded_at'] ?? null,
            ];
        })->toArray();
    }

    public function getActualDurationInHoursAttribute(): ?float
    {
        return $this->actual_duration ? round($this->actual_duration / 60, 2) : null;
    }

    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updatedBy?->name ?? 'System';
    }

    public function getApprovedByNameAttribute(): ?string
    {
        return $this->approvedBy?->name ?? null;
    }

    // Methods
    public function start(?int $userId = null, ?string $notes = null): bool
    {
        if ($this->stage_status !== 'pending') {
            return false;
        }

        $updates = [
            'stage_status' => 'in_progress',
            'started_at' => now(),
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($notes) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Started - " . $notes;
        }

        return $this->update($updates);
    }

    public function complete(?int $userId = null, ?string $notes = null, ?array $stageData = null): bool
    {
        if (!in_array($this->stage_status, ['in_progress', 'requires_approval'])) {
            return false;
        }

        $duration = null;
        if ($this->started_at) {
            $duration = $this->started_at->diffInMinutes(now());
        }

        $updates = [
            'stage_status' => 'completed',
            'completed_at' => now(),
            'actual_duration' => $duration,
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($notes) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Completed - " . $notes;
        }

        if ($stageData) {
            $updates['stage_data'] = array_merge($this->stage_data ?? [], $stageData);
        }

        $result = $this->update($updates);

        if ($result) {
            // Update print job progress
            $this->updatePrintJobProgress();
        }

        return $result;
    }

    public function putOnHold(?int $userId = null, ?string $reason = null): bool
    {
        if (!in_array($this->stage_status, ['pending', 'in_progress'])) {
            return false;
        }

        $updates = [
            'stage_status' => 'on_hold',
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($reason) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Put on hold - " . $reason;
        }

        return $this->update($updates);
    }

    public function resume(?int $userId = null, ?string $notes = null): bool
    {
        if ($this->stage_status !== 'on_hold') {
            return false;
        }

        $newStatus = $this->started_at ? 'in_progress' : 'pending';

        $updates = [
            'stage_status' => $newStatus,
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($notes) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Resumed - " . $notes;
        }

        return $this->update($updates);
    }

    public function reject(?int $userId = null, ?string $reason = null): bool
    {
        if (!in_array($this->stage_status, ['in_progress', 'requires_approval'])) {
            return false;
        }

        $updates = [
            'stage_status' => 'rejected',
            'rejection_reason' => $reason,
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($reason) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Rejected - " . $reason;
        }

        return $this->update($updates);
    }

    public function requireApproval(?int $userId = null, ?string $notes = null): bool
    {
        if ($this->stage_status !== 'in_progress') {
            return false;
        }

        $updates = [
            'stage_status' => 'requires_approval',
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($notes) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Requires approval - " . $notes;
        }

        return $this->update($updates);
    }

    public function approve(?int $userId = null, ?string $notes = null): bool
    {
        if ($this->stage_status !== 'requires_approval') {
            return false;
        }

        $updates = [
            'stage_status' => 'completed',
            'completed_at' => now(),
            'approved_by' => $userId ?: auth()->id(),
            'approval_status' => 'approved',
        ];

        if ($this->requires_customer_approval) {
            $updates['customer_approved_at'] = now();
        }

        if ($notes) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Approved - " . $notes;
        }

        $result = $this->update($updates);

        if ($result) {
            // Update print job progress
            $this->updatePrintJobProgress();
        }

        return $result;
    }

    public function skip(?int $userId = null, ?string $reason = null): bool
    {
        if (!in_array($this->stage_status, ['pending', 'on_hold'])) {
            return false;
        }

        $updates = [
            'stage_status' => 'skipped',
            'updated_by' => $userId ?: auth()->id(),
        ];

        if ($reason) {
            $updates['notes'] = $this->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Skipped - " . $reason;
        }

        return $this->update($updates);
    }

    public function addAttachment(string $filePath, string $type = 'file', ?string $description = null): bool
    {
        $attachments = $this->attachments ?? [];
        
        $attachments[] = [
            'path' => $filePath,
            'type' => $type, // file, image, document, proof, etc.
            'description' => $description,
            'uploaded_at' => now()->toISOString(),
            'uploaded_by' => auth()->id(),
        ];
        
        return $this->update(['attachments' => $attachments]);
    }

    public function updateStageData(array $data): bool
    {
        $currentData = $this->stage_data ?? [];
        $mergedData = array_merge($currentData, $data);
        
        return $this->update(['stage_data' => $mergedData]);
    }

    public function calculateActualDuration(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?: now();
        return $this->started_at->diffInMinutes($endTime);
    }

    private function updatePrintJobProgress(): void
    {
        $printJob = $this->printJob;
        if (!$printJob) {
            return;
        }

        $totalStages = $printJob->productionStages()->count();
        $completedStages = $printJob->productionStages()->where('stage_status', 'completed')->count();
        
        $progressPercentage = $totalStages > 0 ? (int) (($completedStages / $totalStages) * 100) : 0;
        
        $printJob->update(['completion_percentage' => $progressPercentage]);

        // Update print job status based on stage completion
        if ($progressPercentage === 100) {
            $printJob->updateStatus('completed');
        } elseif ($progressPercentage > 0 && $printJob->production_status === 'pending') {
            $printJob->updateStatus('design_review');
        }
    }

    public function getNextStage(): ?ProductionStage
    {
        return $this->printJob
                    ->productionStages()
                    ->where('stage_order', '>', $this->stage_order)
                    ->orderBy('stage_order')
                    ->first();
    }

    public function getPreviousStage(): ?ProductionStage
    {
        return $this->printJob
                    ->productionStages()
                    ->where('stage_order', '<', $this->stage_order)
                    ->orderBy('stage_order', 'desc')
                    ->first();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($stage) {
            if ($stage->stage_order === 0) {
                $maxOrder = static::where('print_job_id', $stage->print_job_id)
                                 ->max('stage_order');
                $stage->stage_order = ($maxOrder ?? 0) + 1;
            }
        });
    }
}