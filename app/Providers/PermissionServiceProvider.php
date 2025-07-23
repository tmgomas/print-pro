<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind permission and role classes to the container
        $this->app->singleton('permission', function () {
            return new Permission();
        });
        
        $this->app->singleton('role', function () {
            return new Role();
        });
        
        // Register the Permission Registrar
        $this->app->singleton(PermissionRegistrar::class, function ($app) {
            return new PermissionRegistrar($app);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Clear cached permissions
        if ($this->app->runningInConsole()) {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
}