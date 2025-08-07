<?php
// app/Services/ProductionStageService.php

namespace App\Services;

use App\Models\ProductionStage;
use App\Models\PrintJob;
use App\Repositories\ProductionStageRepository;
use App\Events\ProductionStageUpdated;
use App\Events\PrintJobCompleted;
use Illuminate\Support\Facades\DB;

class ProductionStageService extends BaseService
{
    public function __construct(ProductionStageRepository $repository)
    {
        parent::__construct($repository);
    }
public function completeStage(ProductionStage $stage, int $userId, ?string $notes = null, ?array $stageData = null): bool
{
    try {
        return DB::transaction(function () use ($stage, $userId, $notes, $stageData) {
            if (!in_array($stage->stage_status, ['in_progress', 'requires_approval'])) {
                throw new \Exception('Cannot complete stage in current status');
            }

            $duration = null;
            if ($stage->started_at) {
                $duration = $stage->started_at->diffInMinutes(now());
            }

            $updateData = [
                'stage_status' => 'completed',
                'completed_at' => now(),
                'actual_duration' => $duration,
                'updated_by' => $userId,
            ];

            if ($notes) {
                $updateData['notes'] = ($stage->notes ?? '') . "\n" . now()->format('Y-m-d H:i:s') . ": Completed - " . $notes;
            }

            if ($stageData) {
                $updateData['stage_data'] = array_merge($stage->stage_data ?? [], $stageData);
            }

            $updated = $this->repository->update($stage->id, $updateData);

            if ($updated) {
                // Auto-advance to next stage
                $this->advanceToNextStage($stage->fresh(), $userId);
                
                // Check if entire print job is completed
                $this->checkPrintJobCompletion($stage->printJob);
            }

            return $updated;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'stage completion');
        throw $e;
    }
}

/**
 * Approve stage with auto-advance
 */
public function approveStage(ProductionStage $stage, int $userId, ?string $notes = null): bool
{
    try {
        return DB::transaction(function () use ($stage, $userId, $notes) {
            if ($stage->stage_status !== 'requires_approval') {
                throw new \Exception('Stage does not require approval');
            }

            $updateData = [
                'stage_status' => 'completed',
                'completed_at' => now(),
                'approved_by' => $userId,
                'approval_status' => 'approved',
            ];

            if ($stage->requires_customer_approval) {
                $updateData['customer_approved_at'] = now();
            }

            if ($notes) {
                $updateData['notes'] = ($stage->notes ?? '') . "\n" . now()->format('Y-m-d H:i:s') . ": Approved - " . $notes;
            }

            $updated = $this->repository->update($stage->id, $updateData);

            if ($updated) {
                // Auto-advance to next stage after approval
                $this->advanceToNextStage($stage->fresh(), $userId);
                
                // Check if entire print job is completed
                $this->checkPrintJobCompletion($stage->printJob);
            }

            return $updated;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'stage approval');
        throw $e;
    }
}

/**
 * Enhanced auto-advance to next stage
 */
private function advanceToNextStage(ProductionStage $currentStage, int $userId): void
{
    try {
        $nextStage = $this->repository->getNextStage(
            $currentStage->print_job_id, 
            $currentStage->stage_order
        );

        if ($nextStage && $nextStage->stage_status === 'pending') {
            $updateData = [
                'updated_by' => $userId,
                'notes' => ($nextStage->notes ?? '') . "\n" . now()->format('Y-m-d H:i:s') . ": Auto-advanced from " . $currentStage->stage_name
            ];

            // Different logic based on stage requirements
            if ($nextStage->requires_customer_approval) {
                // Customer approval stages should go to 'requires_approval' status
                $updateData['stage_status'] = 'requires_approval';
                \Log::info('Stage advanced to requires_approval', [
                    'current_stage' => $currentStage->stage_name,
                    'next_stage' => $nextStage->stage_name,
                    'requires_customer_approval' => true
                ]);
            } else {
                // Regular stages should go to 'ready' status
                $updateData['stage_status'] = 'ready';
                \Log::info('Stage advanced to ready', [
                    'current_stage' => $currentStage->stage_name,
                    'next_stage' => $nextStage->stage_name,
                    'requires_customer_approval' => false
                ]);
            }

            $this->repository->update($nextStage->id, $updateData);
        }
    } catch (\Exception $e) {
        \Log::error('Failed to advance to next stage', [
            'current_stage_id' => $currentStage->id,
            'current_stage_name' => $currentStage->stage_name,
            'error' => $e->getMessage()
        ]);
        // Don't throw exception - just log error so main operation doesn't fail
    }
}

/**
 * Check if print job is completed
 */
private function checkPrintJobCompletion(PrintJob $printJob): void
{
    try {
        $totalStages = $printJob->productionStages()->count();
        $completedStages = $printJob->productionStages()
            ->where('stage_status', 'completed')
            ->count();

        \Log::info('Checking print job completion', [
            'print_job_id' => $printJob->id,
            'total_stages' => $totalStages,
            'completed_stages' => $completedStages
        ]);

        if ($completedStages === $totalStages && $totalStages > 0) {
            $printJob->update([
                'production_status' => 'completed',
                'actual_completion' => now(),
                'completion_percentage' => 100
            ]);

            \Log::info('Print job marked as completed', [
                'print_job_id' => $printJob->id
            ]);

            // Fire completion event if it exists
            try {
                if (class_exists('\App\Events\PrintJobCompleted')) {
                    event(new \App\Events\PrintJobCompleted($printJob));
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to fire PrintJobCompleted event', [
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // Update completion percentage
            $percentage = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
            $printJob->update(['completion_percentage' => $percentage]);
        }
    } catch (\Exception $e) {
        \Log::error('Failed to check print job completion', [
            'print_job_id' => $printJob->id,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Start stage with ready status check
 */
public function startStage(ProductionStage $stage, int $userId, ?string $notes = null): bool
{
    try {
        // Allow starting from both 'pending' and 'ready' status
        if (!in_array($stage->stage_status, ['pending', 'ready'])) {
            throw new \Exception('Cannot start stage - current status: ' . $stage->stage_status);
        }

        $updateData = [
            'stage_status' => 'in_progress',
            'started_at' => now(),
            'updated_by' => $userId,
        ];

        if ($notes) {
            $updateData['notes'] = ($stage->notes ?? '') . "\n" . now()->format('Y-m-d H:i:s') . ": Started - " . $notes;
        }

        return $this->repository->update($stage->id, $updateData);
    } catch (\Exception $e) {
        $this->handleException($e, 'stage start');
        throw $e;
    }
}
    /**
     * Update production stage with business logic
     */
    public function updateStage(ProductionStage $stage, array $data): bool
    {
        try {
            return DB::transaction(function () use ($stage, $data) {
                $updated = $this->repository->update($stage->id, $data);

                if ($updated) {
                    // Fire event for notifications
                    event(new ProductionStageUpdated($stage->fresh()));

                    // Check if print job is completed
                    $this->checkPrintJobCompletion($stage->printJob);

                    // Auto-advance to next stage if completed
                    if ($data['stage_status'] === 'completed') {
                        $this->advanceToNextStage($stage);
                    }
                }

                return $updated;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'production stage update');
            throw $e;
        }
    }





    /**
     * Get pending approvals for a company
     */
    public function getPendingApprovals(int $companyId): array
    {
        $approvals = $this->repository->getPendingApprovals($companyId);

        return [
            'customer_approvals' => $approvals->where('requires_customer_approval', true),
            'internal_approvals' => $approvals->where('requires_customer_approval', false),
        ];
    }

    /**
     * Get active stages for a user
     */
    public function getActiveStagesForUser(int $userId): array
    {
        $stages = $this->repository->getActiveStagesByUser($userId);

        return $stages->groupBy('stage_name')->toArray();
    }

    /**
     * Bulk update multiple stages
     */
    public function bulkUpdateStages(array $stageIds, array $updateData, int $userId): bool
    {
        try {
            return DB::transaction(function () use ($stageIds, $updateData, $userId) {
                $updateData['updated_by'] = $userId;
                $updateData['updated_at'] = now();

                return $this->repository->bulkUpdateStatus($stageIds, $updateData['stage_status'], $userId);
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'bulk stage update');
            throw $e;
        }
    }

    /**
     * Get stage performance analytics
     */
    public function getStageAnalytics(int $companyId, ?int $branchId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = $this->repository->getModel()
            ->whereHas('printJob', function ($q) use ($companyId, $branchId) {
                $q->where('company_id', $companyId);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            });

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $stages = $query->get();

        return [
            'total_stages' => $stages->count(),
            'completed_stages' => $stages->where('stage_status', 'completed')->count(),
            'average_duration' => $stages->where('actual_duration', '>', 0)->avg('actual_duration'),
            'overdue_stages' => $stages->filter(function ($stage) {
                return $stage->is_overdue;
            })->count(),
            'stages_by_type' => $stages->groupBy('stage_name')->map->count(),
            'stages_by_status' => $stages->groupBy('stage_status')->map->count(),
        ];
    }




    /**
     * Reject stage
     */
    public function rejectStage(ProductionStage $stage, int $userId, string $reason): bool
    {
        try {
            if (!in_array($stage->stage_status, ['in_progress', 'requires_approval'])) {
                throw new \Exception('Cannot reject stage in current status');
            }

            $updateData = [
                'stage_status' => 'rejected',
                'rejection_reason' => $reason,
                'updated_by' => $userId,
                'notes' => $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Rejected - " . $reason
            ];

            return $this->repository->update($stage->id, $updateData);
        } catch (\Exception $e) {
            $this->handleException($e, 'stage rejection');
            throw $e;
        }
    }

    /**
     * Put stage on hold
     */
    public function holdStage(ProductionStage $stage, int $userId, string $reason): bool
    {
        try {
            if (!in_array($stage->stage_status, ['pending', 'in_progress'])) {
                throw new \Exception('Cannot put stage on hold in current status');
            }

            $updateData = [
                'stage_status' => 'on_hold',
                'updated_by' => $userId,
                'notes' => $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Put on hold - " . $reason
            ];

            return $this->repository->update($stage->id, $updateData);
        } catch (\Exception $e) {
            $this->handleException($e, 'stage hold');
            throw $e;
        }
    }

    /**
     * Resume stage from hold
     */
    public function resumeStage(ProductionStage $stage, int $userId, ?string $notes = null): bool
    {
        try {
            if ($stage->stage_status !== 'on_hold') {
                throw new \Exception('Stage is not on hold');
            }

            $newStatus = $stage->started_at ? 'in_progress' : 'pending';

            $updateData = [
                'stage_status' => $newStatus,
                'updated_by' => $userId,
            ];

            if ($notes) {
                $updateData['notes'] = $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Resumed - " . $notes;
            }

            return $this->repository->update($stage->id, $updateData);
        } catch (\Exception $e) {
            $this->handleException($e, 'stage resume');
            throw $e;
        }
    }
}