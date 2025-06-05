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
            'create products',
            'edit products',
            'delete products',
            'manage stock',
            'view stock movements',

            // Purchasing
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'delete suppliers',
            'view purchase orders',
            'create purchase orders',
            'edit purchase orders',
            'delete purchase orders',
            'receive goods',

            // Sales
            'view sales',
            'create sales',
            'edit sales',
            'delete sales',
            'process payments',
            'manage cash sessions',
            'view pos',

            // Reports
            'view reports',
            'export reports',
            'view analytics',

            // System
            'manage settings',
            'view audit logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin - Full access
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Manager - Store operations, reports, user management
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            'view users',
            'create users',
            'edit users',
            'assign roles',
            'view inventory',
            'create products',
            'edit products',
            'manage stock',
            'view stock movements',
            'view suppliers',
            'create suppliers',
            'edit suppliers',
            'view purchase orders',
            'create purchase orders',
            'edit purchase orders',
            'receive goods',
            'view sales',
            'create sales',
            'edit sales',
            'process payments',
            'manage cash sessions',
            'view pos',
            'view reports',
            'export reports',
            'view analytics',
        ]);

        // Cashier - Sales transactions, basic inventory viewing
        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashier->syncPermissions([
            'view inventory',
            'view sales',
            'create sales',
            'process payments',
            'manage cash sessions',
            'view pos',
        ]);

        // Stock Clerk - Inventory management, receiving purchases
        $stockClerk = Role::firstOrCreate(['name' => 'stock-clerk']);
        $stockClerk->syncPermissions([
            'view inventory',
            'create products',
            'edit products',
            'manage stock',
            'view stock movements',
            'view suppliers',
            'view purchase orders',
            'receive goods',
        ]);
    }
}
