<?php

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyRepository
{
    public function __construct(
        private Company $model
    ) {}

    /**
     * Get all companies with pagination
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['branches', 'users']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('registration_number', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find company by ID
     */
    public function find(int $id): ?Company
    {
        return $this->model->with(['branches', 'users'])->find($id);
    }

    /**
     * Find company by email
     */
    public function findByEmail(string $email): ?Company
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Create new company
     */
    public function create(array $data): Company
    {
        return $this->model->create($data);
    }

    /**
     * Update company
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * Delete company (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->model->find($id)?->delete() ?? false;
    }

    /**
     * Get active companies
     */
    public function getActive(): Collection
    {
        return $this->model->active()->with(['branches'])->get();
    }

    /**
     * Check if email exists (excluding specific company)
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
     * Check if registration number exists
     */
    public function registrationNumberExists(string $registrationNumber, ?int $excludeId = null): bool
    {
        $query = $this->model->where('registration_number', $registrationNumber);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get company statistics
     */
    public function getStats(): array
    {
        return [
            'total' => $this->model->count(),
            'active' => $this->model->where('status', 'active')->count(),
            'inactive' => $this->model->where('status', 'inactive')->count(),
            'suspended' => $this->model->where('status', 'suspended')->count(),
        ];
    }

    /**
     * Get recently created companies
     */
    public function getRecent(int $limit = 10): Collection
    {
        return $this->model->with(['branches'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Search companies
     */
    public function search(string $term): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('email', 'like', '%' . $term . '%')
              ->orWhere('phone', 'like', '%' . $term . '%')
              ->orWhere('registration_number', 'like', '%' . $term . '%');
        })
        ->active()
        ->limit(20)
        ->get();
    }

    /**
     * Get company with detailed information
     */
    public function getWithDetails(int $id): ?Company
    {
        return $this->model->with([
            'branches' => function ($query) {
                $query->active();
            },
            'users' => function ($query) {
                $query->active()->with('roles');
            }
        ])->find($id);
    }
}