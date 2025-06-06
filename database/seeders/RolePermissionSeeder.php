<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User Management
            'view users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',

            // Inventory Management
            'view inventory',
            'manage inventory',
            'view stock movements',

            // Purchasing Management
            'view purchasing',
            'manage purchasing',
            'approve purchase orders',
            'receive goods',
            'approve purchase receipts',

            // Sales Management
            'view sales',
            'manage sales',
            'process payments',
            'manage cash sessions',
            'view pos',

            // Reports & Analytics
            'view reports',
            'export reports',
            'view analytics',

            // System Administration
            'manage settings',
            'view audit logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - Full access to everything
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Manager - Full operational access except system settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view users',
            'create users',
            'edit users',
            'assign roles',
            'view inventory',
            'manage inventory',
            'view stock movements',
            'view purchasing',
            'manage purchasing',
            'approve purchase orders',
            'receive goods',
            'approve purchase receipts',
            'view sales',
            'manage sales',
            'process payments',
            'manage cash sessions',
            'view pos',
            'view reports',
            'export reports',
            'view analytics',
        ]);

        // Purchasing Manager - Specialized for purchasing operations
        $purchasingManager = Role::firstOrCreate(['name' => 'purchasing-manager']);
        $purchasingManager->syncPermissions([
            'view inventory',
            'view purchasing',
            'manage purchasing',
            'approve purchase orders',
            'receive goods',
            'approve purchase receipts',
            'view reports',
            'view analytics',
        ]);

        // Warehouse Supervisor - Inventory and receiving focused
        $warehouseSupervisor = Role::firstOrCreate(['name' => 'warehouse-supervisor']);
        $warehouseSupervisor->syncPermissions([
            'view inventory',
            'manage inventory',
            'view stock movements',
            'view purchasing',
            'receive goods',
            'approve purchase receipts',
            'view reports',
        ]);

        // Cashier - Point of sale operations
        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashier->syncPermissions([
            'view inventory',
            'view sales',
            'manage sales',
            'process payments',
            'manage cash sessions',
            'view pos',
        ]);

        // Stock Clerk - Basic inventory and receiving
        $stockClerk = Role::firstOrCreate(['name' => 'stock-clerk']);
        $stockClerk->syncPermissions([
            'view inventory',
            'manage inventory',
            'view stock movements',
            'view purchasing',
            'receive goods',
        ]);

        // Purchasing Clerk - Purchase order creation and management
        $purchasingClerk = Role::firstOrCreate(['name' => 'purchasing-clerk']);
        $purchasingClerk->syncPermissions([
            'view inventory',
            'view purchasing',
            'manage purchasing',
            'receive goods',
        ]);

        // Sales Associate - Basic sales operations
        $salesAssociate = Role::firstOrCreate(['name' => 'sales-associate']);
        $salesAssociate->syncPermissions([
            'view inventory',
            'view sales',
            'manage sales',
            'process payments',
            'view pos',
        ]);
    }
}
