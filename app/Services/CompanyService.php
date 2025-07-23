<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Branch;
use App\Repositories\CompanyRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CompanyService extends BaseService
{
    public function __construct(CompanyRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Create company with main branch
     */
    public function createCompany(array $data): Company
    {
        try {
            return DB::transaction(function () use ($data) {
                // Handle logo upload
                if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                    $data['logo'] = $this->uploadLogo($data['logo']);
                }
                
                // Create company
                $company = $this->repository->create($data);
                
                // Create main branch
                $this->createMainBranch($company, $data);
                
                return $company;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'company creation');
        }
    }

    /**
     * Update company
     */
    public function updateCompany(Company $company, array $data): Company
    {
        try {
            // Handle logo upload
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                // Delete old logo
                if ($company->logo) {
                    Storage::delete($company->logo);
                }
                $data['logo'] = $this->uploadLogo($data['logo']);
            }
            
            $company->update($data);
            return $company->fresh();
        } catch (\Exception $e) {
            $this->handleException($e, 'company update');
        }
    }

    /**
     * Upload company logo
     */
    private function uploadLogo(UploadedFile $file): string
    {
        return $file->store('companies/logos', 'public');
    }

    /**
     * Create main branch for company
     */
    private function createMainBranch(Company $company, array $data): Branch
    {
        return Branch::create([
            'company_id' => $company->id,
            'name' => $data['branch_name'] ?? 'Main Branch',
            'code' => $data['branch_code'] ?? 'MAIN',
            'address' => $data['address'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'is_main_branch' => true,
            'status' => 'active',
        ]);
    }
}