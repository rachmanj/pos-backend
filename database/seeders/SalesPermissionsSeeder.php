<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create new sales permissions
        $salesPermissions = [
            // Customer Management
            'view customers',
            'manage customers',
            'delete customers',

            // Payment Methods
            'view payment methods',
            'manage payment methods',

            // Cash Sessions
            'view cash sessions',
            'manage cash',
            'open cash sessions',
            'close cash sessions',

            // Sales & POS
            'process sales',
            'void sales',
            'view sales reports',
            'manage sales settings',

            // Point of Sale
            'access pos',
            'full screen pos',
        ];

        foreach ($salesPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Update existing roles with new permissions

        // Super Admin - gets all permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }

        // Manager - gets most sales permissions
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view customers',
                'manage customers',
                'view payment methods',
                'manage payment methods',
                'view cash sessions',
                'manage cash',
                'open cash sessions',
                'close cash sessions',
                'process sales',
                'void sales',
                'view sales reports',
                'manage sales settings',
                'access pos',
                'full screen pos',
            ]);
        }

        // Cashier - specialized for POS operations
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'view customers',
                'manage customers', // Allow creating customers during sales
                'view payment methods',
                'view cash sessions',
                'open cash sessions',
                'close cash sessions',
                'process sales',
                'access pos',
                'full screen pos',
            ]);
        }

        // Sales Associate - basic sales permissions
        $salesAssociate = Role::where('name', 'sales-associate')->first();
        if ($salesAssociate) {
            $salesAssociate->givePermissionTo([
                'view customers',
                'manage customers', // Allow creating customers during sales
                'view payment methods',
                'process sales',
                'access pos',
            ]);
        }

        // Create new sales-specific roles

        // Store Manager - comprehensive store operations
        $storeManager = Role::firstOrCreate(['name' => 'store-manager']);
        $storeManager->syncPermissions([
            // Inventory (view only)
            'view inventory',

            // Customer management
            'view customers',
            'manage customers',
            'delete customers',

            // Payment methods
            'view payment methods',
            'manage payment methods',

            // Cash sessions
            'view cash sessions',
            'manage cash',
            'open cash sessions',
            'close cash sessions',

            // Sales
            'view sales',
            'manage sales',
            'process sales',
            'void sales',
            'view sales reports',
            'manage sales settings',

            // POS
            'access pos',
            'full screen pos',

            // Reports
            'view reports',
        ]);

        // Senior Cashier - enhanced cashier with more permissions
        $seniorCashier = Role::firstOrCreate(['name' => 'senior-cashier']);
        $seniorCashier->syncPermissions([
            // Inventory (view only)
            'view inventory',

            // Customer management
            'view customers',
            'manage customers',

            // Payment methods
            'view payment methods',

            // Cash sessions
            'view cash sessions',
            'manage cash',
            'open cash sessions',
            'close cash sessions',

            // Sales
            'view sales',
            'manage sales',
            'process sales',
            'void sales', // Can void sales

            // POS
            'access pos',
            'full screen pos',

            // Basic reports
            'view sales reports',
        ]);

        // Customer Service Representative
        $customerService = Role::firstOrCreate(['name' => 'customer-service']);
        $customerService->syncPermissions([
            // Customer management
            'view customers',
            'manage customers',

            // Sales (view for support)
            'view sales',

            // Limited POS access for returns/exchanges
            'access pos',
        ]);

        $this->command->info('Sales permissions and roles created successfully!');
    }
}
