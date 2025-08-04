<?php
// app/Repositories/CustomerRepository.php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerRepository extends BaseRepository
{
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }

    /**
     * Search and paginate customers
     */
    public function searchAndPaginate(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->forCompany($companyId)
            ->with(['branch']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->searchByName($filters['search']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_type'])) {
            $query->byType($filters['customer_type']);
        }

        if (!empty($filters['branch_id'])) {
            $query->forBranch($filters['branch_id']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', 'like', "%{$filters['city']}%");
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Get customers for dropdown
     */
    

    /**
     * Generate unique customer code
     */
    public function generateUniqueCode(int $companyId): string
    {
        $prefix = 'CUS';
        $year = date('y');
        $counter = 1;
        
        // Get the last customer code for this year
        $lastCustomer = $this->model
            ->forCompany($companyId)
            ->where('customer_code', 'like', "{$prefix}{$year}%")
            ->orderBy('customer_code', 'desc')
            ->first();
            
        if ($lastCustomer) {
            $lastNumber = (int) substr($lastCustomer->customer_code, -4);
            $counter = $lastNumber + 1;
        }
        
        return $prefix . $year . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get customer statistics
     */
    public function getStats(int $companyId): array
    {
        $totalCustomers = $this->model->forCompany($companyId)->count();
        $activeCustomers = $this->model->forCompany($companyId)->active()->count();
        $businessCustomers = $this->model->forCompany($companyId)->byType('business')->count();
        $suspendedCustomers = $this->model->forCompany($companyId)->where('status', 'suspended')->count();
        $totalCreditLimit = $this->model->forCompany($companyId)->sum('credit_limit');
        $totalOutstandingBalance = $this->model->forCompany($companyId)->sum('current_balance');

        return [
            'total' => $totalCustomers,
            'active' => $activeCustomers,
            'inactive' => $this->model->forCompany($companyId)->where('status', 'inactive')->count(),
            'suspended' => $suspendedCustomers,
            'business' => $businessCustomers,
            'individual' => $totalCustomers - $businessCustomers,
            'total_credit_limit' => $totalCreditLimit,
            'total_outstanding' => $totalOutstandingBalance,
            'average_credit_limit' => $totalCustomers > 0 ? $totalCreditLimit / $totalCustomers : 0,
        ];
    }

    /**
     * Get customers with outstanding balances
     */
    public function getCustomersWithOutstandingBalance(int $companyId): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->where('current_balance', '>', 0)
            ->orderBy('current_balance', 'desc')
            ->get();
    }

    /**
     * Get customers by city
     */
    public function getCustomersByCity(int $companyId): Collection
    {
        return $this->model
            ->forCompany($companyId)
            ->selectRaw('city, COUNT(*) as customer_count')
            ->groupBy('city')
            ->orderBy('customer_count', 'desc')
            ->get();
    }

    /**
     * Update customer balance
     */
    public function updateBalance(int $customerId, float $amount, string $operation = 'add'): bool
    {
        $customer = $this->find($customerId);
        
        if (!$customer) {
            return false;
        }
        
        $newBalance = $operation === 'add' 
            ? $customer->current_balance + $amount
            : $customer->current_balance - $amount;
            
        return $customer->update(['current_balance' => max(0, $newBalance)]);
    }

    /**
     * Bulk update customer status
     */
    public function bulkUpdateStatus(array $customerIds, string $status): int
    {
        return $this->model->whereIn('id', $customerIds)->update(['status' => $status]);
    }

    // app/Repositories/CustomerRepository.php - getForDropdown method
public function getForDropdown(int $companyId, ?int $branchId = null): Collection
{
    $query = $this->model
        ->where('company_id', $companyId)
        ->where('status', 'active')
        ->select([
            'id', 
            'name', 
            'customer_code', 
            'phone', 
            'email',
            'customer_type', 
            'company_name', 
            'credit_limit', 
            'current_balance'
        ]);

    if ($branchId) {
        $query->where('branch_id', $branchId);
    }

    return $query->orderBy('name')->get();
}

}