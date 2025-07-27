<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchService;
use App\Repositories\BranchRepository;
use App\Http\Requests\BranchStoreRequest;
use App\Http\Requests\BranchUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BranchController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        private BranchService $branchService,
        private BranchRepository $branchRepository
    ) {}

    /**
     * Display a listing of branches
     */
    public function index(Request $request): Response
    {
        $this->authorize('view branches');

        $filters = $request->only(['company_id', 'status', 'search']);
        $branches = $this->branchRepository->paginate(15, $filters);

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new branch
     */
    public function create(): Response
    {
        $this->authorize('create branches');

        return Inertia::render('Branches/Create');
    }

    /**
     * Store a newly created branch
     */
    public function store(BranchStoreRequest $request): RedirectResponse
    {
        $this->authorize('create branches');

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
     * Display the specified branch
     */
    public function show(int $id): Response
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('view branches', $branch);

        $branch->load(['company', 'users.roles', 'customers']);

        return Inertia::render('Branches/Show', [
            'branch' => $branch,
            'stats' => [
                'total_users' => $branch->users()->count(),
                'active_users' => $branch->activeUsers()->count(),
                'total_customers' => $branch->customers()->count(),
                'total_invoices' => $branch->invoices()->count(),
            ]
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
            'branch' => $branch,
        ]);
    }

    /**
     * Update the specified branch
     */
    public function update(BranchUpdateRequest $request, int $id): RedirectResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('update branches', $branch);

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

        // Prevent deletion of main branch
        if ($branch->isMainBranch()) {
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

    /**
     * Toggle branch status
     */
    public function toggleStatus(int $id): RedirectResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('update branches', $branch);

        try {
            $newStatus = $branch->status === 'active' ? 'inactive' : 'active';
            $branch->update(['status' => $newStatus]);
            
            return back()->with('success', "Branch status updated to {$newStatus}.");
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Status update failed. Please try again.']);
        }
    }

    /**
     * Get branch users
     */
    public function users(int $id): Response
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('view branches', $branch);
        
        $users = $branch->users()->with('roles')->paginate(10);
        
        return Inertia::render('Branches/Users', [
            'branch' => $branch,
            'users' => $users,
        ]);
    }

    /**
     * Get branch settings
     */
    public function settings(int $id): Response
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('update branches', $branch);
        
        return Inertia::render('Branches/Settings', [
            'branch' => $branch,
        ]);
    }

    /**
     * Update branch settings
     */
    public function updateSettings(Request $request, int $id): RedirectResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('update branches', $branch);

        $request->validate([
            'settings' => 'required|array',
        ]);

        try {
            $branch->update(['settings' => $request->settings]);
            
            return back()->with('success', 'Branch settings updated successfully.');
            
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Settings update failed. Please try again.']);
        }
    }

    /**
     * Generate next invoice number for branch
     */
    public function generateInvoiceNumber(int $id): JsonResponse
    {
        $branch = $this->branchRepository->findOrFail($id);
        
        $this->authorize('view branches', $branch);

        return response()->json([
            'invoice_number' => $branch->generateInvoiceNumber()
        ]);
    }
}