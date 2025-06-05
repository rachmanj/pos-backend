<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class InventoryPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create inventory-related permissions
        $permissions = [
            // Core inventory permissions
            'view inventory' => 'View inventory items and stock levels',
            'manage inventory' => 'Full inventory management (create, edit, delete products, manage stock)',

            // Purchasing permissions
            'view purchasing' => 'View purchase orders and supplier information',
            'manage purchasing' => 'Create and manage purchase orders, suppliers',

            // Sales permissions
            'process sales' => 'Process sales transactions and access POS',
            'view sales' => 'View sales history and transactions',
            'manage sales' => 'Manage sales settings and void transactions',

            // Reporting permissions
            'view reports' => 'View business reports and analytics',
            'manage reports' => 'Create and manage custom reports',

            // Stock management permissions
            'adjust stock' => 'Perform stock adjustments and inventory counts',
            'transfer stock' => 'Transfer stock between locations',
        ];

        // Create permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        // Get or create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $inventoryManager = Role::firstOrCreate(['name' => 'inventory-manager']);
        $salesPerson = Role::firstOrCreate(['name' => 'sales-person']);
        $cashier = Role::firstOrCreate(['name' => 'cashier']);

        // Assign permissions to roles

        // Super Admin - all permissions
        $superAdmin->syncPermissions(Permission::all());

        // Admin - most permissions except some super-admin specific ones
        $adminPermissions = [
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'process sales',
            'view sales',
            'manage sales',
            'view reports',
            'manage reports',
            'adjust stock',
            'transfer stock',
        ];
        $admin->syncPermissions($adminPermissions);

        // Manager - operational management
        $managerPermissions = [
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'process sales',
            'view sales',
            'view reports',
            'adjust stock',
            'transfer stock',
        ];
        $manager->syncPermissions($managerPermissions);

        // Inventory Manager - inventory focused
        $inventoryManagerPermissions = [
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'view reports',
            'adjust stock',
            'transfer stock',
        ];
        $inventoryManager->syncPermissions($inventoryManagerPermissions);

        // Sales Person - sales focused
        $salesPersonPermissions = [
            'view inventory',
            'process sales',
            'view sales',
            'view reports',
        ];
        $salesPerson->syncPermissions($salesPersonPermissions);

        // Cashier - basic POS operations
        $cashierPermissions = [
            'view inventory',
            'process sales',
        ];
        $cashier->syncPermissions($cashierPermissions);

        $this->command->info('Inventory permissions seeder completed successfully!');
        $this->command->info('Created permissions:');
        foreach (array_keys($permissions) as $permission) {
            $this->command->info("- {$permission}");
        }
        $this->command->info('Assigned permissions to roles:');
        $this->command->info('- super-admin: All permissions');
        $this->command->info('- admin: Most operational permissions');
        $this->command->info('- manager: Operational management permissions');
        $this->command->info('- inventory-manager: Inventory focused permissions');
        $this->command->info('- sales-person: Sales focused permissions');
        $this->command->info('- cashier: Basic POS permissions');
    }
}
