<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalesOrderPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Sales Order Management Permissions
        $permissions = [
            // Sales Orders Permissions (7 permissions)
            'view sales orders',
            'manage sales orders',
            'process sales orders',
            'approve sales orders',
            'cancel sales orders',
            'delete sales orders',
            'confirm sales orders',

            // Delivery Orders Permissions (6 permissions)
            'view delivery orders',
            'manage delivery orders',
            'process deliveries',
            'manage deliveries',
            'ship deliveries',
            'complete deliveries',

            // Sales Invoices Permissions (6 permissions)
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'delete sales invoices',
            'generate invoices',

            // Delivery Routes Permissions (6 permissions)
            'view delivery routes',
            'manage delivery routes',
            'plan routes',
            'execute delivery routes',
            'optimize routes',
            'track routes'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('Sales Order Management permissions created and assigned successfully!');
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin - all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->givePermissionTo([
            'view sales orders',
            'manage sales orders',
            'process sales orders',
            'approve sales orders',
            'cancel sales orders',
            'delete sales orders',
            'confirm sales orders',
            'view delivery orders',
            'manage delivery orders',
            'process deliveries',
            'manage deliveries',
            'ship deliveries',
            'complete deliveries',
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'delete sales invoices',
            'generate invoices',
            'view delivery routes',
            'manage delivery routes',
            'plan routes',
            'execute delivery routes',
            'optimize routes',
            'track routes'
        ]);

        // Manager - comprehensive management permissions
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo([
            'view sales orders',
            'manage sales orders',
            'process sales orders',
            'approve sales orders',
            'cancel sales orders',
            'confirm sales orders',
            'view delivery orders',
            'manage delivery orders',
            'process deliveries',
            'manage deliveries',
            'ship deliveries',
            'complete deliveries',
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'generate invoices',
            'view delivery routes',
            'manage delivery routes',
            'plan routes',
            'execute delivery routes',
            'optimize routes',
            'track routes'
        ]);

        // Sales Manager - comprehensive sales order management
        $salesManager = Role::firstOrCreate(['name' => 'sales-manager']);
        $salesManager->givePermissionTo([
            'view sales orders',
            'manage sales orders',
            'process sales orders',
            'approve sales orders',
            'cancel sales orders',
            'confirm sales orders',
            'view delivery orders',
            'manage delivery orders',
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'generate invoices',
            'view delivery routes',
            'plan routes',
            'track routes'
        ]);

        // Sales Rep - sales order processing
        $salesRep = Role::firstOrCreate(['name' => 'sales-rep']);
        $salesRep->givePermissionTo([
            'view sales orders',
            'process sales orders',
            'confirm sales orders',
            'view delivery orders',
            'view sales invoices',
            'process invoices',
            'view delivery routes',
            'track routes'
        ]);

        // Warehouse Manager - delivery and fulfillment focus
        $warehouseManager = Role::firstOrCreate(['name' => 'warehouse-manager']);
        $warehouseManager->givePermissionTo([
            'view sales orders',
            'view delivery orders',
            'manage delivery orders',
            'process deliveries',
            'manage deliveries',
            'ship deliveries',
            'complete deliveries',
            'view sales invoices',
            'view delivery routes',
            'manage delivery routes',
            'plan routes',
            'execute delivery routes',
            'optimize routes',
            'track routes'
        ]);

        // Delivery Driver - delivery execution
        $deliveryDriver = Role::firstOrCreate(['name' => 'delivery-driver']);
        $deliveryDriver->givePermissionTo([
            'view delivery orders',
            'process deliveries',
            'complete deliveries',
            'view delivery routes',
            'execute delivery routes',
            'track routes'
        ]);

        // Finance Manager - invoice management
        $financeManager = Role::firstOrCreate(['name' => 'finance-manager']);
        $financeManager->givePermissionTo([
            'view sales orders',
            'view delivery orders',
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'generate invoices',
            'view delivery routes'
        ]);

        // Accountant - invoice processing
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $accountant->givePermissionTo([
            'view sales orders',
            'view delivery orders',
            'view sales invoices',
            'process invoices',
            'generate invoices',
            'view delivery routes'
        ]);

        // Customer Service - view access for customer support
        $customerService = Role::firstOrCreate(['name' => 'customer-service']);
        $customerService->givePermissionTo([
            'view sales orders',
            'view delivery orders',
            'view sales invoices',
            'view delivery routes',
            'track routes'
        ]);

        // Admin - administrative access
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo([
            'view sales orders',
            'manage sales orders',
            'process sales orders',
            'approve sales orders',
            'cancel sales orders',
            'confirm sales orders',
            'view delivery orders',
            'manage delivery orders',
            'process deliveries',
            'manage deliveries',
            'view sales invoices',
            'manage sales invoices',
            'process invoices',
            'send sales invoices',
            'generate invoices',
            'view delivery routes',
            'manage delivery routes',
            'plan routes',
            'optimize routes',
            'track routes'
        ]);

        $this->command->info('Permissions assigned to roles successfully!');
    }
}
