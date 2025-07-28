<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchStoreRequest;
use App\Http\Requests\BranchUpdateRequest;
use App\Services\BranchService;
use App\Repositories\BranchRepository;
use App\Repositories\CompanyRepository;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
class BranchController extends Controller
{
    use AuthorizesRequests;
    protected BranchService $branchService;
    protected BranchRepository $branchRepository;
    protected CompanyRepository $companyRepository;

    public function __construct(
        BranchService $branchService,
        BranchRepository $branchRepository,
        CompanyRepository $companyRepository
    ) {
        $this->branchService = $branchService;
        $this->branchRepository = $branchRepository;
        $this->companyRepository = $companyRepository;
    }

    /**
     * Show the form for creating a new branch
     */
  public function create(): Response
{
    $this->authorize('create branches');

    // Get companies for dropdown - FIXED: This was missing
    $companies = $this->companyRepository->getForDropdown();
    
    return Inertia::render('Branches/Create', [
        'companies' => $companies->map(function ($company) {
            return [
                'value' => $company->id,
                'label' => $company->name . ' (' . $company->registration_number . ')',
            ];
        })->toArray(),
    ]);
}
    /**
     * Store a newly created branch
     */
    public function store(BranchStoreRequest $request): RedirectResponse
    {
        try {
            $branch = $this->branchService->createBranch($request->validated());
            
            return redirect()
                ->route('branches.show', $branch->id)
                ->with('success', 'Branch created successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Branch creation failed. Please try again.']);
        }
    }

    /**
     * Display a listing of branches
     */
  public function index(): Response
{
    $this->authorize('view branches');

    // Get branches with pagination
    $branches = $this->branchRepository->getAllWithFilters(
        request()->get('search'),
        request()->get('status'),
        request()->get('company_id')
    );

    // Get stats
    $stats = [
        'total' => $branches->count(),
        'active' => $branches->where('status', 'active')->count(),
        'inactive' => $branches->where('status', 'inactive')->count(),
        'main_branches' => $branches->where('is_main_branch', true)->count(),
    ];

    return Inertia::render('Branches/Index', [
        'branches' => [
            'data' => $branches->toArray(),
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $branches->count(),
            'total' => $branches->count(),
            'from' => 1,
            'to' => $branches->count(),
        ],
        'stats' => $stats,
        'filters' => request()->only(['search', 'status', 'company_id']),
    ]);
}

    /**
     * Display the specified branch
     */
    public function show(int $id): Response
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('view branches', $branch);

        return Inertia::render('Branches/Show', [
            'branch' => $branch->load(['company', 'users.roles']),
        ]);
    }

    /**
     * Show the form for editing the branch
     */
    public function edit(int $id): Response
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('edit branches', $branch);

        return Inertia::render('Branches/Edit', [
            'branch' => $branch->load('company'),
        ]);
    }

    /**
     * Update the specified branch
     */
    public function update(BranchUpdateRequest $request, int $id): RedirectResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('edit branches', $branch);

        try {
            $updatedBranch = $this->branchService->updateBranch($branch, $request->validated());
            
            return redirect()
                ->route('branches.show', $updatedBranch->id)
                ->with('success', 'Branch updated successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Branch update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified branch
     */
    public function destroy(int $id): RedirectResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('delete branches', $branch);

        if ($branch->is_main_branch) {
            return back()->withErrors(['error' => 'Cannot delete the main branch.']);
        }

        try {
            $this->branchService->deleteBranch($branch);
            
            return redirect()
                ->route('branches.index')
                ->with('success', 'Branch deleted successfully.');
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Branch deletion failed. Please try again.']);
        }
    }
}