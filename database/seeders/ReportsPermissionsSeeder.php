<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ReportsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create reporting permissions
        $permissions = [
            // General reporting permissions
            'view reports' => 'View basic reports and analytics',
            'manage reports' => 'Manage and configure reporting system',
            'export reports' => 'Export reports to various formats',

            // Dashboard permissions
            'view dashboard' => 'View executive dashboard and overview metrics',
            'manage dashboard' => 'Configure dashboard widgets and settings',

            // Sales analytics permissions
            'view sales analytics' => 'View sales reports and analytics',
            'manage sales analytics' => 'Configure sales reporting parameters',

            // Inventory analytics permissions
            'view inventory analytics' => 'View inventory reports and analytics',
            'manage inventory analytics' => 'Configure inventory reporting parameters',

            // Purchasing analytics permissions
            'view purchasing analytics' => 'View purchasing reports and analytics',
            'manage purchasing analytics' => 'Configure purchasing reporting parameters',

            // Financial reporting permissions
            'view financial reports' => 'View financial statements and reports',
            'manage financial reports' => 'Configure financial reporting parameters',

            // Advanced analytics permissions
            'view advanced analytics' => 'View advanced business intelligence reports',
            'manage advanced analytics' => 'Configure advanced analytics and BI tools',

            // Data export permissions
            'export sales data' => 'Export sales data and reports',
            'export inventory data' => 'Export inventory data and reports',
            'export financial data' => 'Export financial data and reports',
            'export customer data' => 'Export customer data and analytics',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission => $description) {
            Permission::firstOrCreate(
                ['name' => $permission]
            );
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('Reports permissions created and assigned successfully!');
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin - all permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo([
                'view reports',
                'manage reports',
                'export reports',
                'view dashboard',
                'manage dashboard',
                'view sales analytics',
                'manage sales analytics',
                'view inventory analytics',
                'manage inventory analytics',
                'view purchasing analytics',
                'manage purchasing analytics',
                'view financial reports',
                'manage financial reports',
                'view advanced analytics',
                'manage advanced analytics',
                'export sales data',
                'export inventory data',
                'export financial data',
                'export customer data',
            ]);
        }

        // Admin - comprehensive reporting access
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view sales analytics',
                'view inventory analytics',
                'view purchasing analytics',
                'view financial reports',
                'view advanced analytics',
                'export sales data',
                'export inventory data',
                'export financial data',
                'export customer data',
            ]);
        }

        // Manager - departmental reporting access
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view sales analytics',
                'view inventory analytics',
                'view purchasing analytics',
                'export sales data',
                'export inventory data',
            ]);
        }

        // Sales Manager - sales focused reporting
        $salesManager = Role::where('name', 'sales-manager')->first();
        if ($salesManager) {
            $salesManager->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view sales analytics',
                'export sales data',
                'export customer data',
            ]);
        }

        // Inventory Manager - inventory focused reporting
        $inventoryManager = Role::where('name', 'inventory-manager')->first();
        if ($inventoryManager) {
            $inventoryManager->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view inventory analytics',
                'view purchasing analytics',
                'export inventory data',
            ]);
        }

        // Warehouse Manager - warehouse and inventory reporting
        $warehouseManager = Role::where('name', 'warehouse-manager')->first();
        if ($warehouseManager) {
            $warehouseManager->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view inventory analytics',
                'export inventory data',
            ]);
        }

        // Purchase Manager - purchasing focused reporting
        $purchaseManager = Role::where('name', 'purchase-manager')->first();
        if ($purchaseManager) {
            $purchaseManager->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view purchasing analytics',
                'view inventory analytics',
                'export inventory data',
            ]);
        }

        // Cashier - basic sales reporting
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'view reports',
                'view dashboard',
            ]);
        }

        // Staff - limited reporting access
        $staff = Role::where('name', 'staff')->first();
        if ($staff) {
            $staff->givePermissionTo([
                'view reports',
                'view dashboard',
            ]);
        }

        // Accountant - financial reporting access
        $accountant = Role::where('name', 'accountant')->first();
        if ($accountant) {
            $accountant->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view financial reports',
                'view sales analytics',
                'view purchasing analytics',
                'export financial data',
                'export sales data',
            ]);
        }

        // Auditor - comprehensive read-only access
        $auditor = Role::where('name', 'auditor')->first();
        if ($auditor) {
            $auditor->givePermissionTo([
                'view reports',
                'export reports',
                'view dashboard',
                'view sales analytics',
                'view inventory analytics',
                'view purchasing analytics',
                'view financial reports',
                'view advanced analytics',
                'export sales data',
                'export inventory data',
                'export financial data',
                'export customer data',
            ]);
        }
    }
}
