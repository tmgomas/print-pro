<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'admin:create 
                           {--name= : The name of the super admin}
                           {--email= : The email of the super admin}
                           {--password= : The password of the super admin}
                           {--force : Force creation without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Super Admin user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Creating Super Admin User...');
        $this->newLine();

        // Get user details
        $name = $this->option('name') ?: $this->ask('Enter the Super Admin name');
        $email = $this->option('email') ?: $this->ask('Enter the Super Admin email');
        $password = $this->option('password') ?: $this->secret('Enter the Super Admin password');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return 1;
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists!");
            return 1;
        }

        // Show confirmation
        if (!$this->option('force')) {
            $this->table(
                ['Field', 'Value'],
                [
                    ['Name', $name],
                    ['Email', $email],
                    ['Role', 'Super Admin'],
                ]
            );

            if (!$this->confirm('Do you want to create this Super Admin user?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            // Create Super Admin role if it doesn't exist
            $this->createSuperAdminRole();

            // Split name into first and last name
            $nameParts = explode(' ', trim($name), 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            // Create the user
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
                'company_id' => null, // Super Admin doesn't belong to any company
                'branch_id' => null,  // Super Admin doesn't belong to any branch
            ]);

            // Assign Super Admin role
            $user->assignRole('Super Admin');

            // Give all permissions to Super Admin
            $this->assignAllPermissions($user);

            $this->newLine();
            $this->info('✅ Super Admin user created successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $user->id],
                    ['Name', $user->full_name],
                    ['Email', $user->email],
                    ['Role', 'Super Admin'],
                    ['Status', ucfirst($user->status)],
                    ['Created At', $user->created_at->format('Y-m-d H:i:s')],
                ]
            );

            $this->newLine();
            $this->info('🔗 Login URL: ' . url('/login'));
            $this->info('📧 Email: ' . $email);
            $this->warn('🔒 Password: [Hidden for security]');

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create Super Admin user: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Create Super Admin role and permissions
     */
    private function createSuperAdminRole(): void
    {
        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Define all permissions
        $permissions = [
            // User Management
            'manage users',
            'create users',
            'view users', 
            'edit users',
            'delete users',
            'bulk actions users',

            // Company Management
            'manage companies',
            'create companies',
            'view companies',
            'edit companies',
            'delete companies',

            // Branch Management
            'manage branches',
            'create branches',
            'view branches',
            'edit branches',
            'delete branches',

            // Product Management
            'manage products',
            'create products',
            'view products',
            'edit products',
            'delete products',

            // Customer Management
            'manage customers',
            'create customers',
            'view customers',
            'edit customers',
            'delete customers',

            // Order Management
            'manage orders',
            'create orders',
            'view orders',
            'edit orders',
            'delete orders',
            'process orders',

            // Invoice Management
            'manage invoices',
            'create invoices',
            'view invoices',
            'edit invoices',
            'delete invoices',
            'send invoices',

            // Payment Management
            'manage payments',
            'create payments',
            'view payments',
            'edit payments',
            'verify payments',
            'process refunds',

            // Production Management
            'manage production',
            'view production',
            'update production status',
            'assign production tasks',

            // Delivery Management
            'manage delivery',
            'view delivery',
            'schedule delivery',
            'track delivery',
            'update delivery status',

            // Reports & Analytics
            'view reports',
            'export reports',
            'view analytics',

            // Settings & Configuration
            'manage settings',
            'manage roles',
            'manage permissions',
            'system administration',

            // Financial Management
            'view financial data',
            'manage pricing',
            'view profit reports',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        $this->info('Super Admin role and permissions created/updated.');
    }

    /**
     * Assign all permissions to the user
     */
    private function assignAllPermissions(User $user): void
    {
        // Get all permissions
        $allPermissions = Permission::all();
        
        // Assign all permissions to the user
        $user->givePermissionTo($allPermissions);
        
        $this->info("Assigned {$allPermissions->count()} permissions to Super Admin.");
    }
}