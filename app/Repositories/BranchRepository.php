<?php

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $model)
    {
        parent::__construct($model);
    }

    /**
     * Get active companies for dropdown
     */
    public function getForDropdown(): Collection
    {
        return $this->model
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'registration_number']);
    }

    /**
     * Get companies with filters
     */
    public function getAllWithFilters(?string $search = null, ?string $status = null): Collection
    {
        $query = $this->model->query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('registration_number', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('created_at', 'desc')->get();
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
     * Get company with branches count
     */
    public function findWithBranchesCount(int $id): ?Company
    {
        return $this->model
            ->withCount('branches')
            ->find($id);
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
        ];
    }
}