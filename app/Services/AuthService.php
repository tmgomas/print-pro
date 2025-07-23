<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AuthService extends BaseService
{
    public function __construct(UserRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                // Hash password
                $data['password'] = Hash::make($data['password']);
                
                // Create user
                $user = $this->repository->create($data);
                
                // Assign role if provided
                if (isset($data['role'])) {
                    $role = Role::findByName($data['role']);
                    $user->assignRole($role);
                }
                
                // Send email verification
                $user->sendEmailVerificationNotification();
                
                return $user;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'user creation');
        }
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                // Hash password if provided
                if (isset($data['password'])) {
                    $data['password'] = Hash::make($data['password']);
                }
                
                // Update user data
                $user->update($data);
                
                // Update role if provided
                if (isset($data['role'])) {
                    $user->syncRoles([$data['role']]);
                }
                
                return $user->fresh();
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'user update');
        }
    }

    /**
     * Suspend user
     */
    public function suspendUser(User $user): User
    {
        $user->update(['status' => 'suspended']);
        return $user;
    }

    /**
     * Activate user
     */
    public function activateUser(User $user): User
    {
        $user->update(['status' => 'active']);
        return $user;
    }
    public function registerUser(array $data): User
{
    try {
        return DB::transaction(function () use ($data) {
            // Hash password
            $data['password'] = Hash::make($data['password']);
            
            // Set default status
            $data['status'] = 'active';
            
            // Create user
            $user = $this->repository->create($data);
            
            // Assign default role (e.g., Customer)
            $defaultRole = Role::findByName('Customer') ?? Role::findByName('User');
            if ($defaultRole) {
                $user->assignRole($defaultRole);
            }
            
            // Send email verification
            $user->sendEmailVerificationNotification();
            
            return $user;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'user registration');
    }
}

/**
 * Track user login activity
 */
public function trackLogin(User $user, string $ipAddress): void
{
    try {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);
        
        // Optional: Log activity
        activity('auth')
            ->causedBy($user)
            ->log('User logged in from IP: ' . $ipAddress);
            
    } catch (\Exception $e) {
        // Log error but don't fail the login process
        \Log::error('Failed to track login for user ' . $user->id . ': ' . $e->getMessage());
    }
}

/**
 * Track user logout activity
 */
public function trackLogout(User $user): void
{
    try {
        // Optional: Log activity
        activity('auth')
            ->causedBy($user)
            ->log('User logged out');
            
    } catch (\Exception $e) {
        // Log error but don't fail the logout process
        \Log::error('Failed to track logout for user ' . $user->id . ': ' . $e->getMessage());
    }
}

/**
 * Deactivate user
 */
public function deactivateUser(User $user): User
{
    $user->update(['status' => 'inactive']);
    
    // Optional: Log activity
    activity('user')
        ->causedBy(auth()->user())
        ->performedOn($user)
        ->log('User deactivated');
    
    return $user;
}

/**
 * Reactivate user
 */
public function reactivateUser(User $user): User
{
    $user->update(['status' => 'active']);
    
    // Optional: Log activity
    activity('user')
        ->causedBy(auth()->user())
        ->performedOn($user)
        ->log('User reactivated');
    
    return $user;
}

/**
 * Change user password
 */
public function changePassword(User $user, string $newPassword): User
{
    try {
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
        
        // Optional: Log activity
        activity('user')
            ->causedBy($user)
            ->performedOn($user)
            ->log('Password changed');
        
        return $user;
    } catch (\Exception $e) {
        $this->handleException($e, 'password change');
    }
}

/**
 * Verify user email
 */
public function verifyEmail(User $user): User
{
    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        
        // Optional: Log activity
        activity('user')
            ->causedBy($user)
            ->performedOn($user)
            ->log('Email verified');
    }
    
    return $user;
}
}