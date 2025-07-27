<?php

namespace App\Repositories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $model)
    {
        parent::__construct($model);
    }
 public function getForDropdown(): Collection
    {
        return $this->model
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'registration_number']);
    }
    /**
     * Get paginated companies with filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['branches']);

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get active companies
     */
    public function getActive(): Collection
    {
        return $this->model->active()->get();
    }

    /**
     * Get company by email
     */
    public function findByEmail(string $email): ?Company
    {
        return $this->model->where('email', $email)->first();
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
