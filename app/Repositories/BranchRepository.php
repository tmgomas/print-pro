<?php

namespace App\Repositories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;

class BranchRepository extends BaseRepository
{
    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active branches for dropdown
     */
    public function getForDropdown(?int $companyId = null): Collection
    {
        $query = $this->model->where('status', 'active');
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        
        return $query->orderBy('branch_name')->get(['id', 'branch_name', 'branch_code']);
    }

    /**
     * Get branches with filters
     */
    public function getAllWithFilters(?string $search = null, ?string $status = null, ?int $companyId = null): Collection
    {
        $query = $this->model->with('company');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('branch_name', 'LIKE', "%{$search}%")
                  ->orWhere('branch_code', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Check if branch code exists
     */
    public function codeExists(string $branchCode, ?int $excludeId = null): bool
    {
        $query = $this->model->where('branch_code', $branchCode);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * Set branch as main branch for company
     */
    public function setAsMainBranch(int $branchId, int $companyId): void
    {
        // First, remove main branch status from all branches in the company
        $this->model->where('company_id', $companyId)
                   ->update(['is_main_branch' => false]);
        
        // Then set the specified branch as main (if branchId > 0)
        if ($branchId > 0) {
            $this->model->where('id', $branchId)
                       ->update(['is_main_branch' => true]);
        }
    }

    /**
     * Get main branch for company
     */
    public function getMainBranch(int $companyId): ?Branch
    {
        return $this->model->where('company_id', $companyId)
                          ->where('is_main_branch', true)
                          ->first();
    }

    /**
     * Get branch statistics
     */
    public function getStats(?int $companyId = null): array
    {
        $query = $this->model->query();
        
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'inactive' => $query->where('status', 'inactive')->count(),
        ];
    }

    /**
     * Get branches for specific company
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->model->where('company_id', $companyId)
                          ->orderBy('branch_name')
                          ->get();
    }

    /**
     * Check if branch has users
     */
    public function hasUsers(int $branchId): bool
    {
        return $this->model->find($branchId)->users()->exists();
    }

    /**
     * Check if branch has invoices
     */
    public function hasInvoices(int $branchId): bool
    {
        return $this->model->find($branchId)->invoices()->exists();
    }
}