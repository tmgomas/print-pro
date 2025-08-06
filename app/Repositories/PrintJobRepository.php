<?php
// app/Repositories/PrintJobRepository.php

namespace App\Repositories;

use App\Models\PrintJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PrintJobRepository extends BaseRepository
{
    public function __construct(PrintJob $model)
    {
        parent::__construct($model);
    }

    /**
     * Find print job by invoice ID
     */
    public function findByInvoice(int $invoiceId): ?PrintJob
    {
        return $this->model->where('invoice_id', $invoiceId)->first();
    }

    /**
     * Check if print job exists for invoice
     */
    public function existsForInvoice(int $invoiceId): bool
    {
        return $this->model->where('invoice_id', $invoiceId)->exists();
    }

    /**
     * Search and paginate print jobs with filters
     */
    public function searchAndPaginate(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch', 'assignedTo', 'productionStages'])
            ->where('company_id', $companyId);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('job_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('job_type', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('invoice.customer', function (Builder $subQ) use ($filters) {
                      $subQ->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('production_status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('estimated_completion', '<', now())
                  ->whereNotIn('production_status', ['completed', 'cancelled']);
        }

        return $query->orderBy('priority', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Get production queue for a branch
     */
    public function getProductionQueue(int $branchId, ?int $assignedTo = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'productionStages'])
            ->where('branch_id', $branchId)
            ->whereIn('production_status', ['pending', 'in_progress', 'on_hold']);

        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }

        return $query->orderBy('priority', 'desc')
                    ->orderBy('estimated_completion', 'asc')
                    ->get();
    }

    /**
     * Get statistics for print jobs
     */
    public function getStats(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->newQuery()
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return [
            'total' => $query->count(),
            'pending' => $query->clone()->where('production_status', 'pending')->count(),
            'in_progress' => $query->clone()->where('production_status', 'in_progress')->count(),
            'completed' => $query->clone()->where('production_status', 'completed')->count(),
            'overdue' => $query->clone()
                ->where('estimated_completion', '<', now())
                ->whereNotIn('production_status', ['completed', 'cancelled'])
                ->count(),
        ];
    }

    /**
     * Find by job number
     */
    public function findByJobNumber(string $jobNumber): ?PrintJob
    {
        return $this->model->where('job_number', $jobNumber)->first();
    }

    /**
     * Get print jobs for dropdown
     */
    public function getForDropdown(int $companyId): Collection
    {
        return $this->model->where('company_id', $companyId)
                          ->where('production_status', '!=', 'completed')
                          ->select('id', 'job_number', 'job_type')
                          ->orderBy('job_number')
                          ->get();
    }

    /**
     * Get overdue print jobs
     */
    public function getOverdueJobs(int $companyId): Collection
    {
        return $this->model->newQuery()
            ->with(['invoice.customer', 'branch', 'assignedTo'])
            ->where('company_id', $companyId)
            ->where('estimated_completion', '<', now())
            ->whereNotIn('production_status', ['completed', 'cancelled'])
            ->orderBy('estimated_completion', 'asc')
            ->get();
    }

    /**
     * Get jobs assigned to user
     */
    public function getJobsByUser(int $userId): Collection
    {
        return $this->model->newQuery()
            ->with(['invoice.customer', 'productionStages'])
            ->where('assigned_to', $userId)
            ->whereNotIn('production_status', ['completed', 'cancelled'])
            ->orderBy('priority', 'desc')
            ->orderBy('estimated_completion', 'asc')
            ->get();
    }

    /**
     * Get print jobs by company
     */
    public function getByCompany(int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch', 'assignedTo'])
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get recent print jobs
     */
    public function getRecentJobs(int $companyId, int $limit = 10, ?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch'])
            ->where('company_id', $companyId)
            ->select('id', 'job_number', 'job_type', 'production_status', 'priority', 'invoice_id', 'branch_id', 'created_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get print jobs requiring attention (overdue, high priority, etc.)
     */
    public function getJobsRequiringAttention(int $companyId, ?int $branchId = null): array
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch'])
            ->where('company_id', $companyId)
            ->whereNotIn('production_status', ['completed', 'cancelled']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $jobs = $query->get();

        return [
            'overdue' => $jobs->where('estimated_completion', '<', now())->count(),
            'urgent' => $jobs->where('priority', 'urgent')->count(),
            'high_priority' => $jobs->whereIn('priority', ['high', 'urgent'])->count(),
            'pending' => $jobs->where('production_status', 'pending')->count(),
            'on_hold' => $jobs->where('production_status', 'on_hold')->count(),
        ];
    }

    /**
     * Check if print job can be deleted
     */
    public function canBeDeleted(int $id): bool
    {
        $printJob = $this->find($id);
        
        if (!$printJob) {
            return false;
        }

        // Cannot delete if production has started
        return $printJob->production_status === 'pending' && 
               $printJob->productionStages()->where('stage_status', '!=', 'pending')->count() === 0;
    }

    /**
     * Check if print job can be modified
     */
    public function canBeModified(int $id): bool
    {
        $printJob = $this->find($id);
        
        if (!$printJob) {
            return false;
        }

        // Can modify if not completed or cancelled
        return !in_array($printJob->production_status, ['completed', 'cancelled']);
    }

    /**
     * Get print jobs by status
     */
    public function getByStatus(string $status, int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch', 'assignedTo'])
            ->where('company_id', $companyId)
            ->where('production_status', $status);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get print jobs by priority
     */
    public function getByPriority(string $priority, int $companyId, ?int $branchId = null): Collection
    {
        $query = $this->model->newQuery()
            ->with(['invoice.customer', 'branch', 'assignedTo'])
            ->where('company_id', $companyId)
            ->where('priority', $priority);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('estimated_completion', 'asc')->get();
    }
}