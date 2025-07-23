<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Company Management
            'view companies',
            'create companies',
            'edit companies',
            'delete companies',
            
            // Branch Management
            'view branches',
            'create branches',
            'edit branches',
            'delete branches',
            
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',
            
            // Product Management
            'view products',
            'create products',
            'edit products',
            'delete products',
            'manage pricing',
            
            // Order Management
            'view orders',
            'create orders',
            'edit orders',
            'delete orders',
            'cancel orders',
            
            // Invoice Management
            'view invoices',
            'create invoices',
            'edit invoices',
            'delete invoices',
            'download invoices',
            
            // Payment Management
            'view payments',
            'process payments',
            'verify payments',
            'refund payments',
            
            // Production Management
            'view production',
            'manage production',
            'update production status',
            'assign production jobs',
            
            // Delivery Management
            'view deliveries',
            'manage deliveries',
            'assign deliveries',
            'update delivery status',
            
            // Customer Management
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            
            // Reporting
            'view reports',
            'export reports',
            'financial reports',
            
            // Settings
            'view settings',
            'edit settings',
            'system settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $this->createSuperAdminRole();
        $this->createCompanyAdminRole();
        $this->createBranchManagerRole();
        $this->createCashierRole();
        $this->createProductionStaffRole();
        $this->createDeliveryCoordinatorRole();
    }

    private function createSuperAdminRole()
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        
        // Super Admin gets all permissions
        $role->givePermissionTo(Permission::all());
    }

    private function createCompanyAdminRole()
    {
        $role = Role::firstOrCreate(['name' => 'Company Admin']);
        
        $permissions = [
            // Branch Management
            'view branches', 'create branches', 'edit branches', 'delete branches',
            
            // User Management (within company)
            'view users', 'create users', 'edit users', 'delete users', 'assign roles',
            
            // Product Management
            'view products', 'create products', 'edit products', 'delete products', 'manage pricing',
            
            // Order Management
            'view orders', 'create orders', 'edit orders', 'delete orders', 'cancel orders',
            
            // Invoice Management
            'view invoices', 'create invoices', 'edit invoices', 'delete invoices', 'download invoices',
            
            // Payment Management
            'view payments', 'process payments', 'verify payments', 'refund payments',
            
            // Production Management
            'view production', 'manage production', 'update production status', 'assign production jobs',
            
            // Delivery Management
            'view deliveries', 'manage deliveries', 'assign deliveries', 'update delivery status',
            
            // Customer Management
            'view customers', 'create customers', 'edit customers', 'delete customers',
            
            // Reporting
            'view reports', 'export reports', 'financial reports',
            
            // Settings
            'view settings', 'edit settings',
        ];
        
        $role->givePermissionTo($permissions);
    }

    private function createBranchManagerRole()
    {
        $role = Role::firstOrCreate(['name' => 'Branch Manager']);
        
        $permissions = [
            // User Management (branch level)
            'view users', 'create users', 'edit users',
            
            // Product Management
            'view products', 'create products', 'edit products',
            
            // Order Management
            'view orders', 'create orders', 'edit orders', 'cancel orders',
            
            // Invoice Management
            'view invoices', 'create invoices', 'edit invoices', 'download invoices',
            
            // Payment Management
            'view payments', 'process payments', 'verify payments',
            
            // Production Management
            'view production', 'manage production', 'update production status', 'assign production jobs',
            
            // Delivery Management
            'view deliveries', 'manage deliveries', 'assign deliveries', 'update delivery status',
            
            // Customer Management
            'view customers', 'create customers', 'edit customers',
            
            // Reporting
            'view reports', 'export reports',
            
            // Settings
            'view settings',
        ];
        
        $role->givePermissionTo($permissions);
    }

    private function createCashierRole()
    {
        $role = Role::firstOrCreate(['name' => 'Cashier']);
        
        $permissions = [
            // Order Management
            'view orders', 'create orders', 'edit orders',
            
            // Invoice Management
            'view invoices', 'create invoices', 'download invoices',
            
            // Payment Management
            'view payments', 'process payments',
            
            // Customer Management
            'view customers', 'create customers', 'edit customers',
            
            // Product Management (view only)
            'view products',
        ];
        
        $role->givePermissionTo($permissions);
    }

    private function createProductionStaffRole()
    {
        $role = Role::firstOrCreate(['name' => 'Production Staff']);
        
        $permissions = [
            // Production Management
            'view production', 'update production status',
            
            // Order Management (view only)
            'view orders',
            
            // Product Management (view only)
            'view products',
        ];
        
        $role->givePermissionTo($permissions);
    }

    private function createDeliveryCoordinatorRole()
    {
        $role = Role::firstOrCreate(['name' => 'Delivery Coordinator']);
        
        $permissions = [
            // Delivery Management
            'view deliveries', 'manage deliveries', 'assign deliveries', 'update delivery status',
            
            // Order Management (view only)
            'view orders',
            
            // Customer Management (view only)
            'view customers',
        ];
        
        $role->givePermissionTo($permissions);
    }
}