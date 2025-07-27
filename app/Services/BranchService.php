<?php

namespace App\Services;

use App\Models\Branch;
use App\Repositories\BranchRepository;
use App\Repositories\CompanyRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BranchService extends BaseService
{
    protected CompanyRepository $companyRepository;

    public function __construct(
        BranchRepository $repository,
        CompanyRepository $companyRepository
    ) {
        parent::__construct($repository);
        $this->companyRepository = $companyRepository;
    }

    /**
     * Create a new branch
     */
    public function createBranch(array $data): Branch
    {
        try {
            return DB::transaction(function () use ($data) {
                // Validate company exists
                $company = $this->companyRepository->findOrFail($data['company_id']);

                // Check if branch code is unique
                if ($this->repository->codeExists($data['code'])) {
                    throw new \Exception('Branch code already exists.');
                }

                // If this is the first branch for the company, make it main branch
                if ($company->branches()->count() === 0) {
                    $data['is_main_branch'] = true;
                }

                // If setting as main branch, ensure only one main branch per company
                if (isset($data['is_main_branch']) && $data['is_main_branch']) {
                    $this->repository->setAsMainBranch(0, $data['company_id']);
                }

                // Generate branch code if not provided
                if (empty($data['code'])) {
                    $data['code'] = $this->generateBranchCode($company);
                }

                // Create the branch
                $branch = $this->repository->create($data);

                // If this is set as main branch, update other branches
                if ($branch->is_main_branch) {
                    $this->repository->setAsMainBranch($branch->id, $branch->company_id);
                }

                return $branch->load('company');
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'branch creation');
            throw $e;
        }
    }

    /**
     * Update branch
     */
    public function updateBranch(Branch $branch, array $data): Branch
    {
        try {
            return DB::transaction(function () use ($branch, $data) {
                // Check if branch code is unique (excluding current branch)
                if (isset($data['code']) && $data['code'] !== $branch->code) {
                    if ($this->repository->codeExists($data['code'], $branch->id)) {
                        throw new \Exception('Branch code already exists.');
                    }
                }

                // Handle main branch logic
                if (isset($data['is_main_branch']) && $data['is_main_branch'] && !$branch->is_main_branch) {
                    $this->repository->setAsMainBranch($branch->id, $branch->company_id);
                } elseif (isset($data['is_main_branch']) && !$data['is_main_branch'] && $branch->is_main_branch) {
                    // Don't allow removing main branch status if it's the only branch
                    $branchCount = Branch::where('company_id', $branch->company_id)->count();
                    if ($branchCount === 1) {
                        throw new \Exception('Cannot remove main branch status. Company must have at least one main branch.');
                    }
                }

                // Update the branch
                $this->repository->update($branch->id, $data);

                return $branch->fresh()->load('company');
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'branch update');
            throw $e;
        }
    }

    /**
     * Delete branch
     */
    public function deleteBranch(Branch $branch): bool
    {
        try {
            return DB::transaction(function () use ($branch) {
                // Check if branch has users
                if ($branch->users()->count() > 0) {
                    throw new \Exception('Cannot delete branch. Branch has assigned users.');
                }

                // Check if branch has orders/invoices
                if ($branch->invoices()->count() > 0) {
                    throw new \Exception('Cannot delete branch. Branch has invoices.');
                }

                // If this is the main branch, make another branch main
                if ($branch->is_main_branch) {
                    $otherBranch = Branch::where('company_id', $branch->company_id)
                        ->where('id', '!=', $branch->id)
                        ->first();
                    
                    if ($otherBranch) {
                        $otherBranch->update(['is_main_branch' => true]);
                    }
                }

                return $branch->delete();
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'branch deletion');
            throw $e;
        }
    }

    /**
     * Toggle branch status
     */
    public function toggleStatus(Branch $branch): Branch
    {
        try {
            $newStatus = $branch->status === 'active' ? 'inactive' : 'active';
            
            // Don't allow deactivating main branch if it's the only active branch
            if ($newStatus === 'inactive' && $branch->is_main_branch) {
                $activeBranches = Branch::where('company_id', $branch->company_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $branch->id)
                    ->count();
                
                if ($activeBranches === 0) {
                    throw new \Exception('Cannot deactivate the main branch. Company must have at least one active branch.');
                }
            }

            $this->repository->update($branch->id, ['status' => $newStatus]);
            
            return $branch->fresh();
        } catch (\Exception $e) {
            $this->handleException($e, 'branch status toggle');
            throw $e;
        }
    }

    /**
     * Update branch settings
     */
    public function updateSettings(Branch $branch, array $settings): Branch
    {
        try {
            return DB::transaction(function () use ($branch, $settings) {
                // Merge with existing settings
                $currentSettings = $branch->settings ?? [];
                $newSettings = array_merge($currentSettings, $settings);

                $this->repository->update($branch->id, ['settings' => $newSettings]);

                return $branch->fresh();
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'branch settings update');
            throw $e;
        }
    }

    /**
     * Get branch statistics
     */
    public function getBranchStats(Branch $branch): array
    {
        return [
            'total_users' => $branch->users()->count(),
            'active_users' => $branch->users()->where('status', 'active')->count(),
            'total_orders' => $branch->invoices()->count(),
            'total_invoices' => $branch->invoices()->count(),
            'monthly_revenue' => $branch->invoices()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_amount') ?? 0,
            'pending_orders' => $branch->invoices()
                ->where('status', 'pending')
                ->count(),
            'completed_orders' => $branch->invoices()
                ->where('status', 'paid')
                ->count(),
        ];
    }

    /**
     * Generate unique branch code
     */
    protected function generateBranchCode($company): string
    {
        $baseCode = strtoupper(substr($company->name, 0, 3));
        $counter = 1;

        do {
            $code = $baseCode . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        } while ($this->repository->codeExists($code));

        return $code;
    }

    /**
     * Get branches for dropdown
     */
    public function getBranchesForDropdown(?int $companyId = null): array
    {
        $branches = $this->repository->getForDropdown($companyId);

        return $branches->map(function ($branch) {
            return [
                'value' => $branch->id,
                'label' => $branch->name . ' (' . $branch->code . ')',
            ];
        })->toArray();
    }

    /**
     * Get branch with complete information
     */
    public function getBranchDetails(int $id): Branch
    {
        $branch = $this->repository->findWithStats($id);
        
        if (!$branch) {
            throw new \Exception('Branch not found.');
        }

        // Add statistics
        $branch->stats = $this->getBranchStats($branch);

        return $branch;
    }

    /**
     * Validate branch data
     */
    protected function validateBranchData(array $data, ?Branch $branch = null): void
    {
        // Custom validation logic can go here
        if (empty($data['name'])) {
            throw new \Exception('Branch name is required.');
        }

        if (empty($data['code'])) {
            throw new \Exception('Branch code is required.');
        }

        if (empty($data['company_id'])) {
            throw new \Exception('Company is required.');
        }

        // Validate branch code format (alphanumeric, no spaces)
        if (!preg_match('/^[A-Z0-9]+$/', $data['code'])) {
            throw new \Exception('Branch code must contain only uppercase letters and numbers.');
        }
    }

    /**
     * Get company branches with stats
     */
    public function getCompanyBranches(int $companyId): array
    {
        $branches = $this->repository->getByCompany($companyId);

        return $branches->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
                'is_main_branch' => $branch->is_main_branch,
                'users_count' => $branch->users()->count(),
                'address' => $branch->address,
                'phone' => $branch->phone,
                'email' => $branch->email,
            ];
        })->toArray();
    }
}