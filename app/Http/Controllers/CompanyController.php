<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Services\CompanyService;
use App\Repositories\CompanyRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CompanyService $companyService,
        private CompanyRepository $companyRepository
    ) {}

    /**
     * Display companies listing
     */
    public function index(Request $request): Response
    {
        $this->authorize('view companies');

        $filters = $request->only(['search', 'status']);
        $companies = $this->companyRepository->paginate(15, $filters);
        $stats = $this->companyRepository->getStats();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'stats' => $stats,
            'filters' => $filters,
        ]);
    }

    /**
     * Show create company form
     */
    public function create(): Response
    {
        $this->authorize('create companies');

        return Inertia::render('Companies/Create');
    }

    /**
     * Store new company
     */
    public function store(CreateCompanyRequest $request): RedirectResponse
    {
        $this->authorize('create companies');

        try {
            $company = $this->companyService->createCompany($request->validated());
            
            return redirect()->route('companies.show', $company->id)
                ->with('success', 'Company created successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Company creation failed. Please try again.']);
        }
    }

    /**
     * Show company details
     */
    public function show(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('view companies');
        
        $company->load([
            'branches' => function($query) {
                $query->withCount('users');
            },
            'users.roles',
            'activeBranches'
        ]);

        $stats = [
            'total_branches' => $company->branches()->count(),
            'active_branches' => $company->activeBranches()->count(),
            'total_users' => $company->users()->count(),
            'active_users' => $company->users()->where('status', 'active')->count(),
        ];
        
        return Inertia::render('Companies/Show', [
            'company' => $company,
            'stats' => $stats,
        ]);
    }

    /**
     * Show edit company form
     */
    public function edit(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('update companies');
        
        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update company
     */
    public function update(UpdateCompanyRequest $request, int $id): RedirectResponse
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('update companies');

        try {
            $updatedCompany = $this->companyService->updateCompany($company, $request->validated());
            
            return redirect()->route('companies.show', $updatedCompany->id)
                ->with('success', 'Company updated successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Company update failed. Please try again.']);
        }
    }

    /**
     * Remove company
     */
    public function destroy(int $id): RedirectResponse
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('delete companies');

        try {
            DB::transaction(function () use ($company) {
                // Soft delete all related branches and users
                $company->branches()->delete();
                $company->users()->update(['status' => 'inactive']);
                
                // Soft delete the company
                $company->delete();
            });
            
            return redirect()->route('companies.index')
                ->with('success', 'Company deleted successfully.');
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Company deletion failed. Please try again.']);
        }
    }

    /**
     * Toggle company status
     */
    public function toggleStatus(int $id): RedirectResponse
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('update companies');

        try {
            $newStatus = $company->status === 'active' ? 'inactive' : 'active';
            $company->update(['status' => $newStatus]);
            
            return back()->with('success', "Company {$newStatus} successfully.");
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Status update failed. Please try again.']);
        }
    }

    /**
     * Get company branches
     */
    public function branches(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('view companies');
        
        $branches = $company->branches()->with('users')->paginate(10);
        
        return Inertia::render('Companies/Branches', [
            'company' => $company,
            'branches' => $branches,
        ]);
    }

    /**
     * Get company users
     */
    public function users(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('view companies');
        
        $users = $company->users()->with(['branch', 'roles'])->paginate(10);
        
        return Inertia::render('Companies/Users', [
            'company' => $company,
            'users' => $users,
        ]);
    }

    /**
     * Show company settings
     */
    public function settings(int $id): Response
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('update companies');
        
        return Inertia::render('Companies/Settings', [
            'company' => $company,
        ]);
    }

    /**
     * Update company settings
     */
    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $company = $this->companyRepository->findOrFail($id);
        
        $this->authorize('update companies');

        $request->validate([
            'settings' => 'required|array',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_number' => 'nullable|string|max:50',
            'bank_details' => 'nullable|array',
        ]);

        try {
            $company->update($request->only(['settings', 'tax_rate', 'tax_number', 'bank_details']));
            
            return back()->with('success', 'Company settings updated successfully.');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Settings update failed. Please try again.']);
        }
    }
}