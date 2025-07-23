<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    /**
     * Register a new user
     */
    public function registerUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Split name if provided as full name
            if (isset($data['name']) && !isset($data['first_name'])) {
                $nameParts = explode(' ', trim($data['name']), 2);
                $data['first_name'] = $nameParts[0];
                $data['last_name'] = isset($nameParts[1]) ? $nameParts[1] : '';
            }

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'email_verified_at' => now(), // Auto-verify for now
            ]);

            // Assign default role if specified
            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            // Log activity
            $this->logActivity($user, 'user_registered', 'User account created');

            return $user;
        });
    }

    /**
     * Create a new user (for admin creation)
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? '',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'status' => $data['status'] ?? 'active',
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'email_verified_at' => now(),
            ]);

            // Assign role if specified
            if (isset($data['role'])) {
                $user->assignRole($data['role']);
            }

            // Assign permissions if specified
            if (isset($data['permissions'])) {
                $user->givePermissionTo($data['permissions']);
            }

            // Log activity
            $this->logActivity($user, 'user_created', 'User created by admin', [
                'created_by' => auth()->id(),
            ]);

            return $user;
        });
    }

    /**
     * Update user information
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $originalData = $user->toArray();

            // Update basic information
            $user->update([
                'first_name' => $data['first_name'] ?? $user->first_name,
                'last_name' => $data['last_name'] ?? $user->last_name,
                'email' => $data['email'] ?? $user->email,
                'phone' => $data['phone'] ?? $user->phone,
                'company_id' => $data['company_id'] ?? $user->company_id,
                'branch_id' => $data['branch_id'] ?? $user->branch_id,
            ]);

            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            // Update role if specified
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            // Update permissions if specified
            if (isset($data['permissions'])) {
                $user->syncPermissions($data['permissions']);
            }

            // Log activity
            $this->logActivity($user, 'user_updated', 'User information updated', [
                'updated_by' => auth()->id(),
                'changes' => array_diff_assoc($user->toArray(), $originalData),
            ]);

            return $user->fresh();
        });
    }

    /**
     * Update user password
     */
    public function updatePassword(User $user, string $newPassword): bool
    {
        $updated = $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        if ($updated) {
            $this->logActivity($user, 'password_changed', 'Password updated');
        }

        return $updated;
    }

    /**
     * Update user status
     */
    public function updateUserStatus(User $user, string $status): bool
    {
        $oldStatus = $user->status;
        $updated = $user->update(['status' => $status]);

        if ($updated) {
            $this->logActivity($user, 'status_changed', "Status changed from {$oldStatus} to {$status}", [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'changed_by' => auth()->id(),
            ]);
        }

        return $updated;
    }

    /**
     * Activate user
     */
    public function activateUser(User $user): bool
    {
        return $this->updateUserStatus($user, 'active');
    }

    /**
     * Deactivate user
     */
    public function deactivateUser(User $user): bool
    {
        return $this->updateUserStatus($user, 'inactive');
    }

    /**
     * Suspend user
     */
    public function suspendUser(User $user): bool
    {
        return $this->updateUserStatus($user, 'suspended');
    }

    /**
     * Track user login
     */
    public function trackLogin(User $user, string $ipAddress = null): void
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
        ]);

        $this->logActivity($user, 'user_login', 'User logged in', [
            'ip_address' => $ipAddress,
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Track user logout
     */
    public function trackLogout(User $user): void
    {
        if ($user) {
            $this->logActivity($user, 'user_logout', 'User logged out', [
                'ip_address' => request()->ip(),
            ]);
        }
    }

    /**
     * Bulk actions on users
     */
    public function bulkUserAction(string $action, array $userIds): int
    {
        $count = 0;
        
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (!$user) continue;

            try {
                switch ($action) {
                    case 'activate':
                        $this->activateUser($user);
                        $count++;
                        break;
                    case 'deactivate':
                        $this->deactivateUser($user);
                        $count++;
                        break;
                    case 'suspend':
                        $this->suspendUser($user);
                        $count++;
                        break;
                    case 'delete':
                        $user->delete();
                        $this->logActivity($user, 'user_deleted', 'User deleted via bulk action', [
                            'deleted_by' => auth()->id(),
                        ]);
                        $count++;
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Bulk action failed for user {$userId}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification(string $email): bool
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return false;
        }

        // Generate reset token (you might want to use Laravel's built-in password reset)
        $token = Str::random(64);
        
        // Store token in database (create password_resets table)
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Log activity
        $this->logActivity($user, 'password_reset_requested', 'Password reset requested');

        // Send email notification (implement your email logic here)
        // Mail::to($user)->send(new PasswordResetMail($token));

        return true;
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        $resetRecord = DB::table('password_resets')
            ->where('email', $email)
            ->first();

        if (!$resetRecord || !Hash::check($token, $resetRecord->token)) {
            return false;
        }

        // Check if token is not expired (24 hours)
        if (Carbon::parse($resetRecord->created_at)->addHours(24)->isPast()) {
            return false;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return false;
        }

        // Update password
        $this->updatePassword($user, $newPassword);

        // Delete reset token
        DB::table('password_resets')->where('email', $email)->delete();

        $this->logActivity($user, 'password_reset_completed', 'Password reset completed');

        return true;
    }

    /**
     * Verify user email
     */
    public function verifyEmail(User $user): bool
    {
        $updated = $user->update(['email_verified_at' => now()]);

        if ($updated) {
            $this->logActivity($user, 'email_verified', 'Email address verified');
        }

        return $updated;
    }

    /**
     * Check if user can perform action on target user
     */
    public function canManageUser(User $actor, User $target): bool
    {
        // Super Admin can manage anyone
        if ($actor->hasRole('Super Admin')) {
            return true;
        }

        // Company Admin can manage users in their company (except Super Admins)
        if ($actor->hasRole('Company Admin')) {
            return $target->company_id === $actor->company_id && 
                   !$target->hasRole('Super Admin');
        }

        // Branch Manager can manage users in their branch (except Super Admins and Company Admins)
        if ($actor->hasRole('Branch Manager')) {
            return $target->branch_id === $actor->branch_id && 
                   !$target->hasRole(['Super Admin', 'Company Admin']);
        }

        // Users can only manage themselves
        return $actor->id === $target->id;
    }

    /**
     * Log user activity
     */
    private function logActivity(User $user, string $action, string $description, array $metadata = []): void
    {
        try {
            UserActivity::create([
                'user_id' => $user->id,
                'action' => $action,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log user activity: ' . $e->getMessage());
        }
    }

    /**
     * Get user activities
     */
    public function getUserActivities(User $user, int $limit = 50): \Illuminate\Support\Collection
    {
        return UserActivity::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old user activities (for maintenance)
     */
    public function cleanupOldActivities(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return UserActivity::where('created_at', '<', $cutoffDate)->delete();
    }
}