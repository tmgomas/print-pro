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
}