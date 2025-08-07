<?php
// app/Repositories/ProductionStageRepository.php

namespace App\Repositories;

use App\Models\ProductionStage;
use Illuminate\Database\Eloquent\Collection;

class ProductionStageRepository extends BaseRepository
{
    public function __construct(ProductionStage $model)
    {
        parent::__construct($model);
    }
public function getNextStage(int $printJobId, int $currentStageOrder): ?ProductionStage
{
    return $this->model->newQuery()
                      ->where('print_job_id', $printJobId)
                      ->where('stage_order', $currentStageOrder + 1)
                      ->first();
}

/**
 * Get previous stage in sequence
 */
public function getPreviousStage(int $printJobId, int $currentStageOrder): ?ProductionStage
{
    return $this->model->newQuery()
                      ->where('print_job_id', $printJobId)
                      ->where('stage_order', $currentStageOrder - 1)
                      ->first();
}

/**
 * Get all stages for a print job ordered by stage_order
 */
public function getByPrintJob(int $printJobId): Collection
{
    return $this->model->newQuery()
                      ->where('print_job_id', $printJobId)
                      ->orderBy('stage_order')
                      ->get();
}

/**
 * Get current active stage (in progress)
 */
public function getCurrentActiveStage(int $printJobId): ?ProductionStage
{
    return $this->model->newQuery()
                      ->where('print_job_id', $printJobId)
                      ->where('stage_status', 'in_progress')
                      ->orderBy('stage_order')
                      ->first();
}

/**
 * Get next ready stage
 */
public function getNextReadyStage(int $printJobId): ?ProductionStage
{
    return $this->model->newQuery()
                      ->where('print_job_id', $printJobId)
                      ->whereIn('stage_status', ['ready', 'pending'])
                      ->orderBy('stage_order')
                      ->first();
}
 
    /**
     * Get pending approvals for company
     */
    public function getPendingApprovals(int $companyId): Collection
    {
        return $this->model->newQuery()
                          ->with(['printJob.invoice.customer', 'printJob.branch'])
                          ->whereHas('printJob', function ($query) use ($companyId) {
                              $query->where('company_id', $companyId);
                          })
                          ->where('stage_status', 'requires_approval')
                          ->orderBy('created_at', 'asc')
                          ->get();
    }

    /**
     * Get active stages by user
     */
    public function getActiveStagesByUser(int $userId): Collection
    {
        return $this->model->newQuery()
                          ->with(['printJob.invoice.customer'])
                          ->whereHas('printJob', function ($query) use ($userId) {
                              $query->where('assigned_to', $userId);
                          })
                          ->where('stage_status', 'in_progress')
                          ->orderBy('started_at', 'asc')
                          ->get();
    }

    /**
     * Get stages requiring customer approval
     */
    public function getCustomerApprovalStages(int $companyId): Collection
    {
        return $this->model->newQuery()
                          ->with(['printJob.invoice.customer', 'printJob.branch'])
                          ->whereHas('printJob', function ($query) use ($companyId) {
                              $query->where('company_id', $companyId);
                          })
                          ->where('requires_customer_approval', true)
                          ->where('stage_status', 'requires_approval')
                          ->orderBy('created_at', 'asc')
                          ->get();
    }

    /**
     * Get overdue stages
     */
    public function getOverdueStages(int $companyId): Collection
    {
        return $this->model->newQuery()
                          ->with(['printJob.invoice.customer', 'printJob.branch', 'updatedBy'])
                          ->whereHas('printJob', function ($query) use ($companyId) {
                              $query->where('company_id', $companyId);
                          })
                          ->where('stage_status', 'in_progress')
                          ->whereNotNull('started_at')
                          ->whereNotNull('estimated_duration')
                          ->whereRaw('DATE_ADD(started_at, INTERVAL estimated_duration MINUTE) < NOW()')
                          ->orderBy('started_at', 'asc')
                          ->get();
    }

    /**
     * Get stage statistics
     */
    public function getStageStats(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->newQuery()
                            ->whereHas('printJob', function ($q) use ($companyId, $branchId) {
                                $q->where('company_id', $companyId);
                                if ($branchId) {
                                    $q->where('branch_id', $branchId);
                                }
                            });

        return [
            'total' => $query->count(),
            'pending' => $query->clone()->where('stage_status', 'pending')->count(),
            'in_progress' => $query->clone()->where('stage_status', 'in_progress')->count(),
            'completed' => $query->clone()->where('stage_status', 'completed')->count(),
            'on_hold' => $query->clone()->where('stage_status', 'on_hold')->count(),
            'requires_approval' => $query->clone()->where('stage_status', 'requires_approval')->count(),
            'customer_approvals' => $query->clone()
                ->where('requires_customer_approval', true)
                ->where('stage_status', 'requires_approval')
                ->count(),
        ];
    }





    /**
     * Get stages by status for branch
     */
    public function getStagesByStatus(int $branchId, string $status): Collection
    {
        return $this->model->newQuery()
                          ->with(['printJob.invoice.customer', 'updatedBy'])
                          ->whereHas('printJob', function ($query) use ($branchId) {
                              $query->where('branch_id', $branchId);
                          })
                          ->where('stage_status', $status)
                          ->orderBy('updated_at', 'desc')
                          ->get();
    }

    /**
     * Bulk update stages
     */
    public function bulkUpdateStatus(array $stageIds, string $status, int $userId): bool
    {
        return $this->model->newQuery()
                          ->whereIn('id', $stageIds)
                          ->update([
                              'stage_status' => $status,
                              'updated_by' => $userId,
                              'updated_at' => now()
                          ]);
    }
}