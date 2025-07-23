<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function __construct(
        private User $model
    ) {}

    /**
     * Get all users with pagination
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['company', 'branch', 'roles']);

        // Apply filters
        if (!empty($filters['company_id'])) {
            $query->forCompany($filters['company_id']);
        }

        if (!empty($filters['branch_id'])) {
            $query->forBranch($filters['branch_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find user by ID
     */
    public function find(int $id): ?User
    {
        return $this->model->with(['company', 'branch', 'roles', 'permissions'])->find($id);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Create new user
     */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * Update user
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * Delete user (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->model->find($id)?->delete() ?? false;
    }

    /**
     * Get users by company
     */
    public function getByCompany(int $companyId): Collection
    {
        return $this->model->forCompany($companyId)
            ->with(['branch', 'roles'])
            ->active()
            ->get();
    }

    /**
     * Get users by branch
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->model->forBranch($branchId)
            ->with(['roles'])
            ->active()
            ->get();
    }

    /**
     * Get users by role
     */
    public function getByRole(string $role, ?int $companyId = null): Collection
    {
        $query = $this->model->role($role)->active();

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->with(['company', 'branch'])->get();
    }

    /**
     * Check if email exists (excluding specific user)
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get user statistics
     */
    public function getStats(?int $companyId = null): array
    {
        $query = $this->model->query();

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'inactive' => $query->where('status', 'inactive')->count(),
            'suspended' => $query->where('status', 'suspended')->count(),
        ];
    }

    /**
     * Get recently created users
     */
    public function getRecent(int $limit = 10, ?int $companyId = null): Collection
    {
        $query = $this->model->with(['company', 'branch', 'roles']);

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->latest()->limit($limit)->get();
    }

    /**
     * Search users
     */
    public function search(string $term, ?int $companyId = null, ?int $branchId = null): Collection
    {
        $query = $this->model->where(function ($q) use ($term) {
            $q->where('first_name', 'like', '%' . $term . '%')
              ->orWhere('last_name', 'like', '%' . $term . '%')
              ->orWhere('email', 'like', '%' . $term . '%')
              ->orWhere('phone', 'like', '%' . $term . '%');
        });

        if ($companyId) {
            $query->forCompany($companyId);
        }

        if ($branchId) {
            $query->forBranch($branchId);
        }

        return $query->with(['company', 'branch', 'roles'])
            ->active()
            ->limit(20)
            ->get();
    }
}