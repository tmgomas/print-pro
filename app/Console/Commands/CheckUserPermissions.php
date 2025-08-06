<?php
// Create this file: app/Console/Commands/CheckUserPermissions.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CheckUserPermissions extends Command
{
    protected $signature = 'user:check-permissions {user_id?}';
    protected $description = 'Check and assign print job permissions to a user';

    public function handle()
    {
        $userId = $this->argument('user_id') ?: auth()->id();
        
        if (!$userId) {
            $this->error('Please provide a user ID or run this while authenticated.');
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }

        $this->info("Checking permissions for: {$user->name} ({$user->email})");
        
        // Required print job permissions
        $requiredPermissions = [
            'view production',
            'manage production',
            'update production status',
            'assign production jobs',
        ];

        // Check if permissions exist
        foreach ($requiredPermissions as $permission) {
            if (!Permission::where('name', $permission)->exists()) {
                $this->warn("Creating missing permission: {$permission}");
                Permission::create(['name' => $permission]);
            }
        }

        // Check user's current permissions
        $this->info("\nCurrent user permissions:");
        foreach ($requiredPermissions as $permission) {
            $hasPermission = $user->can($permission);
            $status = $hasPermission ? '✅' : '❌';
            $this->line("{$status} {$permission}");
        }

        // Check user's roles
        $this->info("\nUser roles:");
        foreach ($user->roles as $role) {
            $this->line("• {$role->name}");
        }

        // Offer to assign missing permissions
        $missingPermissions = collect($requiredPermissions)->filter(function($permission) use ($user) {
            return !$user->can($permission);
        });

        if ($missingPermissions->isNotEmpty()) {
            $this->warn("\nMissing permissions: " . $missingPermissions->implode(', '));
            
            if ($this->confirm('Would you like to assign these permissions to the user?')) {
                $user->givePermissionTo($missingPermissions->toArray());
                $this->info('Permissions assigned successfully!');
                
                // Verify
                $this->info("\nUpdated permissions:");
                foreach ($requiredPermissions as $permission) {
                    $hasPermission = $user->can($permission);
                    $status = $hasPermission ? '✅' : '❌';
                    $this->line("{$status} {$permission}");
                }
            }
        } else {
            $this->info("\n✅ User has all required print job permissions!");
        }
    }
}

// Run this command with: php artisan user:check-permissions [user_id]