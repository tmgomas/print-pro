<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Get paginated users with filters
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
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
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
     * Get user statistics
     */
    public function getStats(?int $companyId = null): array
    {
        $query = $this->model->newQuery();

        // Apply company filter if provided
        if ($companyId) {
            $query->forCompany($companyId);
        }

        $totalUsers = $query->count();
        
        // Active users
        $activeUsers = (clone $query)->where('status', 'active')->count();
        
        // Inactive users
        $inactiveUsers = (clone $query)->where('status', 'inactive')->count();
        
        // Suspended users
        $suspendedUsers = (clone $query)->where('status', 'suspended')->count();
        
        // Users created this month
        $usersThisMonth = (clone $query)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        // Users created last month
        $usersLastMonth = (clone $query)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        
        // Calculate growth percentage
        $growthPercentage = $usersLastMonth > 0 
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 2)
            : ($usersThisMonth > 0 ? 100 : 0);

        // Users with verified email
        $verifiedUsers = (clone $query)->whereNotNull('email_verified_at')->count();
        
        // Users logged in within last 30 days
        $recentlyActiveUsers = (clone $query)
            ->where('last_login_at', '>=', now()->subDays(30))
            ->count();

        // User statistics by role (if using Spatie permissions)
        $usersByRole = [];
        if (class_exists('\Spatie\Permission\Models\Role')) {
            $roles = \Spatie\Permission\Models\Role::withCount(['users' => function ($q) use ($companyId) {
                if ($companyId) {
                    $q->forCompany($companyId);
                }
            }])->get();
            
            foreach ($roles as $role) {
                $usersByRole[$role->name] = $role->users_count;
            }
        }

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'suspended_users' => $suspendedUsers,
            'verified_users' => $verifiedUsers,
            'recently_active_users' => $recentlyActiveUsers,
            'users_this_month' => $usersThisMonth,
            'users_last_month' => $usersLastMonth,
            'growth_percentage' => $growthPercentage,
            'users_by_role' => $usersByRole,
            'verification_rate' => $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 2) : 0,
            'activity_rate' => $totalUsers > 0 ? round(($recentlyActiveUsers / $totalUsers) * 100, 2) : 0,
        ];
    }

    /**
     * Get user count by status
     */
    public function getCountByStatus(?int $companyId = null): array
    {
        $query = $this->model->newQuery();

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get recently registered users
     */
    public function getRecentlyRegistered(int $limit = 10, ?int $companyId = null): Collection
    {
        $query = $this->model->with(['company', 'branch', 'roles']);

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->latest('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get users by date range
     */
    public function getUsersByDateRange(string $startDate, string $endDate, ?int $companyId = null): Collection
    {
        $query = $this->model->with(['company', 'branch', 'roles']);

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->whereBetween('created_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * Search users
     */
    public function search(string $term, ?int $companyId = null): Collection
    {
        $query = $this->model->with(['company', 'branch', 'roles']);

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', '%' . $term . '%')
              ->orWhere('last_name', 'like', '%' . $term . '%')
              ->orWhere('email', 'like', '%' . $term . '%')
              ->orWhere('phone', 'like', '%' . $term . '%');
        })->get();
    }
}