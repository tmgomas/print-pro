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
     * Start production stage
     */
    public function startStage(ProductionStage $stage, int $userId, ?string $notes = null): bool
    {
        try {
            if ($stage->stage_status !== 'pending') {
                throw new \Exception('Cannot start stage that is not pending');
            }

            $updateData = [
                'stage_status' => 'in_progress',
                'started_at' => now(),
                'updated_by' => $userId,
            ];

            if ($notes) {
                $updateData['notes'] = $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Started - " . $notes;
            }

            return $this->repository->update($stage->id, $updateData);
        } catch (\Exception $e) {
            $this->handleException($e, 'stage start');
            throw $e;
        }
    }

    /**
     * Complete production stage
     */
    public function completeStage(ProductionStage $stage, int $userId, ?string $notes = null, ?array $stageData = null): bool
    {
        try {
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
                $updateData['notes'] = $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Completed - " . $notes;
            }

            if ($stageData) {
                $updateData['stage_data'] = array_merge($stage->stage_data ?? [], $stageData);
            }

            $updated = $this->repository->update($stage->id, $updateData);

            if ($updated) {
                $this->checkPrintJobCompletion($stage->printJob);
                $this->advanceToNextStage($stage);
            }

            return $updated;
        } catch (\Exception $e) {
            $this->handleException($e, 'stage completion');
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
     * Check if print job is completed and update status
     */
    private function checkPrintJobCompletion(PrintJob $printJob): void
    {
        $totalStages = $printJob->productionStages()->count();
        $completedStages = $printJob->productionStages()
            ->where('stage_status', 'completed')
            ->count();

        // Update print job progress
        $printJob->update([
            'completed_stages' => $completedStages,
            'total_stages' => $totalStages
        ]);

        // If all stages are completed
        if ($completedStages === $totalStages) {
            $printJob->update([
                'production_status' => 'completed',
                'actual_completion' => now()
            ]);

            // Fire completion event
            event(new PrintJobCompleted($printJob));
        }
    }

    /**
     * Advance to next stage automatically
     */
    private function advanceToNextStage(ProductionStage $currentStage): void
    {
        $nextStage = $this->repository->getNextStage(
            $currentStage->print_job_id, 
            $currentStage->stage_order
        );

        if ($nextStage && $nextStage->stage_status === 'pending') {
            // If next stage doesn't require customer approval, make it ready
            if (!$nextStage->requires_customer_approval) {
                $this->repository->update($nextStage->id, [
                    'stage_status' => 'ready',
                    'updated_by' => $currentStage->updated_by
                ]);
            }
        }
    }

    /**
     * Handle stage approvals
     */
    public function approveStage(ProductionStage $stage, int $userId, ?string $notes = null): bool
    {
        try {
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
                $updateData['notes'] = $stage->notes . "\n" . now()->format('Y-m-d H:i:s') . ": Approved - " . $notes;
            }

            $updated = $this->repository->update($stage->id, $updateData);

            if ($updated) {
                $this->checkPrintJobCompletion($stage->printJob);
                $this->advanceToNextStage($stage);
            }

            return $updated;
        } catch (\Exception $e) {
            $this->handleException($e, 'stage approval');
            throw $e;
        }
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