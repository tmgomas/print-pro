<?php

namespace App\Repositories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchRepository extends BaseRepository
{
    public function __construct(Branch $model)
    {
        parent::__construct($model);
    }

    /**
     * Find branch by ID or fail
     */
    public function findOrFail(int $id): Branch
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Find branch by ID
     */
    public function find(int $id): ?Branch
    {
        return $this->model->find($id);
    }

    /**
     * Create new branch
     */
    public function create(array $data): Branch
    {
        return $this->model->create($data);
    }

    /**
     * Get paginated branches with filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['company', 'users']);

        // Apply filters
        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('address', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get branches by company
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->model->where('company_id', $companyId)
            ->active()
            ->orderBy('is_main_branch', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Find main branch for company
     */
    public function findMainBranch(int $companyId): ?Branch
    {
        return $this->model->where('company_id', $companyId)
            ->where('is_main_branch', true)
            ->first();
    }

    /**
     * Get branch by code
     */
    public function findByCode(string $code): ?Branch
    {
        return $this->model->where('code', $code)->first();
    }

    /**
     * Search branches
     */
    public function search(string $term, ?int $companyId = null): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('code', 'like', "%{$term}%")
              ->orWhere('address', 'like', "%{$term}%");
        });

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->active()->limit(10)->get();
    }

    /**
     * Get branch statistics
     */
    public function getStats(): array
    {
        return [
            'total' => $this->model->count(),
            'active' => $this->model->where('status', 'active')->count(),
            'inactive' => $this->model->where('status', 'inactive')->count(),
            'main_branches' => $this->model->where('is_main_branch', true)->count(),
        ];
    }

    /**
     * Get active branches
     */
    public function getActive(): Collection
    {
        return $this->model->active()->get();
    }

    /**
     * Get branches for dropdown
     */
    public function getForDropdown(?int $companyId = null): Collection
    {
        $query = $this->model->active();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->orderBy('name')->get(['id', 'name', 'code', 'company_id']);
    }

    /**
     * Update branch
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * Check if branch code exists
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = $this->model->where('code', $code);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    /**
     * Set main branch for company (ensure only one main branch)
     */
    public function setAsMainBranch(int $branchId, int $companyId): bool
    {
        // First, remove main branch status from all branches in company
        $this->model->where('company_id', $companyId)
            ->update(['is_main_branch' => false]);

        // Then set the specified branch as main
        return $this->model->where('id', $branchId)
            ->update(['is_main_branch' => true]);
    }

    /**
     * Get branch with relationships
     */
    public function findWithRelations(int $id, array $relations = []): ?Branch
    {
        $defaultRelations = ['company', 'users.roles'];
        $relations = array_merge($defaultRelations, $relations);

        return $this->model->with($relations)->find($id);
    }

    /**
     * Get branch with stats
     */
    public function findWithStats(int $id): ?Branch
    {
        return $this->model->with([
            'company',
            'users' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'email', 'status', 'branch_id', 'avatar_url')
                      ->with('roles:name');
            }
        ])->find($id);
    }
}