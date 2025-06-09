<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CustomerCrmPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Customer CRM Permissions
        $permissions = [
            // Customer Management
            'view customers',
            'create customers',
            'edit customers',
            'delete customers',
            'blacklist customers',
            'view customer analytics',

            // Customer Contacts
            'view customer contacts',
            'create customer contacts',
            'edit customer contacts',
            'delete customer contacts',

            // Customer Addresses
            'view customer addresses',
            'create customer addresses',
            'edit customer addresses',
            'delete customer addresses',

            // Customer Notes
            'view customer notes',
            'create customer notes',
            'edit customer notes',
            'delete customer notes',
            'view private customer notes',
            'create private customer notes',

            // Customer Loyalty Points
            'view customer loyalty points',
            'adjust customer loyalty points',
            'redeem customer loyalty points',

            // Advanced CRM Features
            'assign customers to sales reps',
            'manage customer follow ups',
            'export customer data',
            'import customer data',
            'view customer reports',
            'manage customer stages',
            'manage customer priorities',
            'view customer credit limits',
            'edit customer credit limits',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    private function assignPermissionsToRoles(): void
    {
        // Super Admin - All permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(Permission::all());
        }

        // Manager - Most permissions
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view customers',
                'create customers',
                'edit customers',
                'blacklist customers',
                'view customer analytics',
                'view customer contacts',
                'create customer contacts',
                'edit customer contacts',
                'delete customer contacts',
                'view customer addresses',
                'create customer addresses',
                'edit customer addresses',
                'delete customer addresses',
                'view customer notes',
                'create customer notes',
                'edit customer notes',
                'delete customer notes',
                'view customer loyalty points',
                'adjust customer loyalty points',
                'redeem customer loyalty points',
                'assign customers to sales reps',
                'manage customer follow ups',
                'export customer data',
                'import customer data',
                'view customer reports',
                'manage customer stages',
                'manage customer priorities',
                'view customer credit limits',
                'edit customer credit limits',
            ]);
        }

        // Sales Manager - Sales focused permissions
        $salesManager = Role::where('name', 'sales-manager')->first();
        if ($salesManager) {
            $salesManager->givePermissionTo([
                'view customers',
                'create customers',
                'edit customers',
                'view customer analytics',
                'view customer contacts',
                'create customer contacts',
                'edit customer contacts',
                'view customer addresses',
                'create customer addresses',
                'edit customer addresses',
                'view customer notes',
                'create customer notes',
                'edit customer notes',
                'view customer loyalty points',
                'redeem customer loyalty points',
                'assign customers to sales reps',
                'manage customer follow ups',
                'export customer data',
                'view customer reports',
                'manage customer stages',
                'manage customer priorities',
                'view customer credit limits',
            ]);
        }

        // Cashier - Basic customer operations
        $cashier = Role::where('name', 'cashier')->first();
        if ($cashier) {
            $cashier->givePermissionTo([
                'view customers',
                'create customers',
                'edit customers',
                'view customer contacts',
                'view customer addresses',
                'view customer notes',
                'create customer notes',
                'view customer loyalty points',
                'redeem customer loyalty points',
            ]);
        }

        // Create new CRM-specific roles
        $this->createCrmRoles();
    }

    private function createCrmRoles(): void
    {
        // Customer Service Representative
        $customerService = Role::firstOrCreate(['name' => 'customer-service']);
        $customerService->givePermissionTo([
            'view customers',
            'edit customers',
            'view customer contacts',
            'create customer contacts',
            'edit customer contacts',
            'view customer addresses',
            'create customer addresses',
            'edit customer addresses',
            'view customer notes',
            'create customer notes',
            'edit customer notes',
            'view customer loyalty points',
            'manage customer follow ups',
            'view customer reports',
        ]);

        // Account Manager
        $accountManager = Role::firstOrCreate(['name' => 'account-manager']);
        $accountManager->givePermissionTo([
            'view customers',
            'create customers',
            'edit customers',
            'view customer analytics',
            'view customer contacts',
            'create customer contacts',
            'edit customer contacts',
            'delete customer contacts',
            'view customer addresses',
            'create customer addresses',
            'edit customer addresses',
            'delete customer addresses',
            'view customer notes',
            'create customer notes',
            'edit customer notes',
            'view private customer notes',
            'create private customer notes',
            'view customer loyalty points',
            'adjust customer loyalty points',
            'redeem customer loyalty points',
            'assign customers to sales reps',
            'manage customer follow ups',
            'export customer data',
            'view customer reports',
            'manage customer stages',
            'manage customer priorities',
            'view customer credit limits',
        ]);

        // Marketing Specialist
        $marketing = Role::firstOrCreate(['name' => 'marketing-specialist']);
        $marketing->givePermissionTo([
            'view customers',
            'view customer analytics',
            'view customer contacts',
            'view customer notes',
            'create customer notes',
            'view customer loyalty points',
            'export customer data',
            'view customer reports',
        ]);

        // Finance roles for credit management
        $financeManager = Role::firstOrCreate(['name' => 'finance-manager']);
        $financeManager->givePermissionTo([
            'view customers',
            'view customer analytics',
            'view customer notes',
            'create customer notes',
            'blacklist customers',
            'view customer credit limits',
            'edit customer credit limits',
            'view customer reports',
        ]);

        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $accountant->givePermissionTo([
            'view customers',
            'view customer notes',
            'create customer notes',
            'view customer credit limits',
            'view customer reports',
        ]);
    }
}
