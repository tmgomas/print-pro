<?php

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Register a new company with admin user
     */
    public function registerCompany(array $companyData, array $adminData): User
    {
        \DB::transaction(function () use ($companyData, $adminData, &$user) {
            // Create company
            $company = Company::create([
                'name' => $companyData['company_name'],
                'registration_number' => $companyData['registration_number'] ?? null,
                'address' => $companyData['address'],
                'phone' => $companyData['phone'],
                'email' => $companyData['email'],
                'tax_rate' => $companyData['tax_rate'] ?? 0.00,
                'tax_number' => $companyData['tax_number'] ?? null,
            ]);

            // Create main branch
            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => $companyData['branch_name'] ?? 'Main Branch',
                'code' => $this->generateBranchCode($companyData['company_name']),
                'address' => $companyData['address'],
                'phone' => $companyData['phone'],
                'email' => $companyData['email'],
                'is_main_branch' => true,
                'status' => 'active',
            ]);

            // Create admin user
            $user = User::create([
                'first_name' => $adminData['first_name'],
                'last_name' => $adminData['last_name'],
                'email' => $adminData['email'],
                'phone' => $adminData['phone'] ?? null,
                'password' => Hash::make($adminData['password']),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'status' => 'active',
            ]);

            // Assign Company Admin role
            $user->assignRole('Company Admin');

            event(new Registered($user));
        });

        return $user;
    }

    /**
     * Create a new user
     */
    public function createUser(array $userData): User
    {
        $user = User::create([
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? null,
            'password' => Hash::make($userData['password']),
            'company_id' => $userData['company_id'],
            'branch_id' => $userData['branch_id'] ?? null,
            'status' => $userData['status'] ?? 'active',
        ]);

        // Assign role if provided
        if (!empty($userData['role'])) {
            $user->assignRole($userData['role']);
        }

        event(new Registered($user));

        return $user;
    }

    /**
     * Authenticate user and update login info
     */
    public function login(array $credentials, Request $request): bool
    {
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            
            // Check if user is active
            if (!$user->isActive()) {
                Auth::logout();
                return false;
            }

            // Update last login info
            $user->updateLastLogin($request->ip());
            
            $request->session()->regenerate();
            
            return true;
        }

        return false;
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
        ];

        // Handle avatar upload
        if (isset($data['avatar']) && $data['avatar']) {
            // Delete old avatar
            if ($user->avatar) {
                Storage::delete($user->avatar);
            }
            
            $updateData['avatar'] = $data['avatar']->store('avatars', 'public');
        }

        $user->update($updateData);

        return $user->fresh();
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, string $newPassword): void
    {
        $user->update([
            'password' => Hash::make($newPassword)
        ]);
    }

    /**
     * Check if user can access resource
     */
    public function canAccessCompany(User $user, Company $company): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToCompany($company);
    }

    /**
     * Check if user can access branch
     */
    public function canAccessBranch(User $user, Branch $branch): bool
    {
        return $user->canAccessBranch($branch);
    }

    /**
     * Get user's accessible branches
     */
    public function getAccessibleBranches(User $user): \Illuminate\Database\Eloquent\Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::active()->get();
        }

        if ($user->isCompanyAdmin()) {
            return $user->company->activeBranches;
        }

        return Branch::where('id', $user->branch_id)->active()->get();
    }

    /**
     * Generate branch code from company name
     */
    private function generateBranchCode(string $companyName): string
    {
        $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $companyName), 0, 3));
        
        // Ensure uniqueness
        $counter = 1;
        $originalCode = $code;
        
        while (Branch::where('code', $code)->exists()) {
            $code = $originalCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }

    /**
     * Activate user account
     */
    public function activateUser(User $user): void
    {
        $user->update(['status' => 'active']);
    }

    /**
     * Deactivate user account
     */
    public function deactivateUser(User $user): void
    {
        $user->update(['status' => 'inactive']);
    }

    /**
     * Suspend user account
     */
    public function suspendUser(User $user): void
    {
        $user->update(['status' => 'suspended']);
    }

    /**
     * Get users by role for a company/branch
     */
    public function getUsersByRole(string $role, ?int $companyId = null, ?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::role($role)->active();

        if ($companyId) {
            $query->forCompany($companyId);
        }

        if ($branchId) {
            $query->forBranch($branchId);
        }

        return $query->get();
    }
}