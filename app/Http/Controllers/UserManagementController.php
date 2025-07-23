<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\CompanyRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository,
        private CompanyRepository $companyRepository
    ) {}

    /**
     * Display a listing of users
     */
    public function index(Request $request): Response
    {
        $this->authorize('view users');

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'role' => $request->get('role'),
            'company_id' => $request->get('company_id'),
            'branch_id' => $request->get('branch_id'),
        ];

        $user = auth()->user();
        
        // Apply company restriction for non-super admins
        if (!$user->isSuperAdmin()) {
            $filters['company_id'] = $user->company_id;
        }

        $users = $this->userRepository->paginate(15, $filters);
        $stats = $this->userRepository->getStats($filters['company_id']);

        // Get filter options
        $companies = $user->isSuperAdmin() 
            ? $this->companyRepository->getActive()->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            : [['value' => $user->company_id, 'label' => $user->company->name]];

        $roles = \Spatie\Permission\Models\Role::all()->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        return Inertia::render('Users/Index', [
            'users' => [
                'data' => $users->items(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'filters' => $filters,
            'stats' => $stats,
            'filterOptions' => [
                'companies' => $companies,
                'roles' => $roles,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'suspended', 'label' => 'Suspended'],
                ],
            ],
            'permissions' => [
                'canCreate' => auth()->user()->can('create users'),
                'canEdit' => auth()->user()->can('edit users'),
                'canDelete' => auth()->user()->can('delete users'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new user
     */
    public function create(): Response
    {
        $this->authorize('create users');

        $user = auth()->user();
        
        $companies = $user->isSuperAdmin() 
            ? $this->companyRepository->getActive()->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            : [['value' => $user->company_id, 'label' => $user->company->name]];

        $roles = $user->isSuperAdmin()
            ? \Spatie\Permission\Models\Role::all()->map(fn($r) => ['value' => $r->name, 'label' => $r->name])
            : \Spatie\Permission\Models\Role::where('name', '!=', 'Super Admin')->get()->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        return Inertia::render('Users/Create', [
            'companies' => $companies,
            'roles' => $roles,
            'defaultCompanyId' => $user->isSuperAdmin() ? null : $user->company_id,
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        try {
            $user = $this->authService->createUser($request->validated());
            
            return redirect()->route('users.show', $user->id)
                ->with('success', 'User created successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'User creation failed. Please try again.']);
        }
    }

    /**
     * Display the specified user
     */
    public function show(int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('view users');
        
        // Check if current user can view this user
        if (!$this->canViewUser($user)) {
            abort(403, 'You cannot view this user.');
        }

        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
                'last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s'),
                'last_login_ip' => $user->last_login_ip,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $user->updated_at->format('Y-m-d H:i:s'),
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'email' => $user->company->email,
                    'status' => $user->company->status,
                ] : null,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                    'status' => $user->branch->status,
                ] : null,
                'roles' => $user->roles->map(fn($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ]),
                'permissions' => $user->getAllPermissions()->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ]),
            ],
            'permissions' => [
                'canEdit' => $this->canEditUser($user),
                'canDelete' => $this->canDeleteUser($user),
                'canActivate' => auth()->user()->can('edit users') && $this->canEditUser($user),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('edit users');
        
        if (!$this->canEditUser($user)) {
            abort(403, 'You cannot edit this user.');
        }

        $currentUser = auth()->user();
        
        $companies = $currentUser->isSuperAdmin() 
            ? $this->companyRepository->getActive()->map(fn($c) => ['value' => $c->id, 'label' => $c->name])
            : [['value' => $currentUser->company_id, 'label' => $currentUser->company->name]];

        $roles = $currentUser->isSuperAdmin()
            ? \Spatie\Permission\Models\Role::all()->map(fn($r) => ['value' => $r->name, 'label' => $r->name])
            : \Spatie\Permission\Models\Role::where('name', '!=', 'Super Admin')->get()->map(fn($r) => ['value' => $r->name, 'label' => $r->name]);

        $branches = $user->company ? $user->company->activeBranches->map(fn($b) => ['value' => $b->id, 'label' => $b->name]) : [];

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'company_id' => $user->company_id,
                'branch_id' => $user->branch_id,
                'current_role' => $user->roles->first()?->name,
                'avatar_url' => $user->avatar_url,
            ],
            'companies' => $companies,
            'roles' => $roles,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        if (!$this->canEditUser($user)) {
            abort(403, 'You cannot edit this user.');
        }

        try {
            $this->userRepository->update($id, $request->validated());
            
            // Update role if provided
            if ($request->has('role')) {
                $user->syncRoles([$request->role]);
            }
            
            return redirect()->route('users.show', $user->id)
                ->with('success', 'User updated successfully.');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'User update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified user from storage
     */
    public function destroy(int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('delete users');
        
        if (!$this->canDeleteUser($user)) {
            abort(403, 'You cannot delete this user.');
        }

        try {
            $this->userRepository->delete($id);
            
            return redirect()->route('users.index')
                ->with('success', 'User deleted successfully.');
                
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'User deletion failed. Please try again.']);
        }
    }

    /**
     * Activate user
     */
    public function activate(int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('edit users');
        
        if (!$this->canEditUser($user)) {
            abort(403, 'You cannot modify this user.');
        }

        $this->authService->activateUser($user);
        
        return back()->with('success', 'User activated successfully.');
    }

    /**
     * Deactivate user
     */
    public function deactivate(int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('edit users');
        
        if (!$this->canEditUser($user)) {
            abort(403, 'You cannot modify this user.');
        }

        $this->authService->deactivateUser($user);
        
        return back()->with('success', 'User deactivated successfully.');
    }

    /**
     * Suspend user
     */
    public function suspend(int $id): RedirectResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            abort(404, 'User not found.');
        }

        $this->authorize('edit users');
        
        if (!$this->canEditUser($user)) {
            abort(403, 'You cannot modify this user.');
        }

        $this->authService->suspendUser($user);
        
        return back()->with('success', 'User suspended successfully.');
    }

    /**
     * Get branches for a company (AJAX)
     */
    public function getBranches(Request $request): JsonResponse
    {
        $companyId = $request->get('company_id');
        
        if (!$companyId) {
            return response()->json([]);
        }

        $user = auth()->user();
        
        // Check if user can access this company
        if (!$user->isSuperAdmin() && $user->company_id != $companyId) {
            return response()->json([], 403);
        }

        $branches = \App\Models\Branch::where('company_id', $companyId)
            ->active()
            ->select('id', 'name')
            ->get()
            ->map(fn($b) => ['value' => $b->id, 'label' => $b->name]);

        return response()->json($branches);
    }

    /**
     * Search users (AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('term');
        $companyId = $request->get('company_id');
        $branchId = $request->get('branch_id');
        
        $user = auth()->user();
        
        // Apply company restriction for non-super admins
        if (!$user->isSuperAdmin()) {
            $companyId = $user->company_id;
        }

        $users = $this->userRepository->search($term, $companyId, $branchId);
        
        return response()->json($users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'branch' => $user->branch?->name,
                'status' => $user->status,
                'avatar_url' => $user->avatar_url,
            ];
        }));
    }

    /**
     * Check if current user can view the specified user
     */
    private function canViewUser($user): bool
    {
        $currentUser = auth()->user();
        
        if ($currentUser->isSuperAdmin()) {
            return true;
        }
        
        return $currentUser->belongsToCompany($user->company);
    }

    /**
     * Check if current user can edit the specified user
     */
    private function canEditUser($user): bool
    {
        $currentUser = auth()->user();
        
        if ($currentUser->isSuperAdmin()) {
            return true;
        }
        
        if ($user->isSuperAdmin()) {
            return false; // Non-super admin cannot edit super admin
        }
        
        return $currentUser->belongsToCompany($user->company);
    }

    /**
     * Check if current user can delete the specified user
     */
    private function canDeleteUser($user): bool
    {
        $currentUser = auth()->user();
        
        if ($currentUser->id === $user->id) {
            return false; // Cannot delete own account
        }
        
        if ($currentUser->isSuperAdmin()) {
            return true;
        }
        
        if ($user->isSuperAdmin()) {
            return false; // Non-super admin cannot delete super admin
        }
        
        return $currentUser->belongsToCompany($user->company);
    }
}