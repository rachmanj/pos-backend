<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ProductionSeeder extends Seeder
{
    /**
     * Seed the production database with only essential data:
     * - Roles and permissions
     * - Admin users
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Production Database Seeding...');

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
        $this->createUsers();

        $this->command->info('✅ Production database seeding completed successfully!');
    }

    private function createPermissions(): void
    {
        $this->command->info('📋 Creating permissions...');

        $permissions = [
            // User Management
            'view users' => 'View user accounts and profiles',
            'create users' => 'Create new user accounts',
            'edit users' => 'Edit existing user accounts',
            'delete users' => 'Delete user accounts',
            'assign roles' => 'Assign roles to users',

            // Inventory Management
            'view inventory' => 'View inventory items and stock levels',
            'manage inventory' => 'Full inventory management (create, edit, delete products, manage stock)',
            'view stock movements' => 'View stock movement history and audit trails',
            'adjust stock' => 'Perform stock adjustments and inventory counts',
            'transfer stock' => 'Transfer stock between locations',

            // Warehouse Management
            'view warehouses' => 'View warehouse information and capacity',
            'manage warehouses' => 'Create and manage warehouse settings',
            'manage warehouse zones' => 'Manage warehouse zones and storage areas',

            // Purchasing Management
            'view purchasing' => 'View purchase orders and supplier information',
            'manage purchasing' => 'Create and manage purchase orders, suppliers',
            'approve purchase orders' => 'Approve purchase orders for processing',
            'receive goods' => 'Process goods receiving and update inventory',
            'approve purchase receipts' => 'Approve received goods and finalize receipts',
            'manage suppliers' => 'Create and manage supplier information',
            'view purchase payments' => 'View purchase payment records',
            'manage purchase payments' => 'Process and manage purchase payments',

            // Sales Management
            'view sales' => 'View sales history and transactions',
            'manage sales' => 'Manage sales settings and void transactions',
            'process sales' => 'Process sales transactions and access POS',
            'manage cash sessions' => 'Manage cash register sessions',
            'view pos' => 'Access point of sale interface',
            'manage sales orders' => 'Create and manage B2B sales orders',
            'approve sales orders' => 'Approve sales orders for fulfillment',

            // Customer Management
            'view customers' => 'View customer information and profiles',
            'manage customers' => 'Create and manage customer accounts',
            'manage customer credit' => 'Manage customer credit limits and terms',
            'view customer payments' => 'View customer payment history',
            'manage customer payments' => 'Process customer payments and allocations',

            // Financial Management
            'view accounts payable' => 'View accounts payable information',
            'manage accounts payable' => 'Manage supplier payments and AP processes',
            'view accounts receivable' => 'View accounts receivable information',
            'manage accounts receivable' => 'Manage customer payments and AR processes',
            'view financial reports' => 'View financial reports and analytics',

            // Reports & Analytics
            'view reports' => 'View business reports and analytics',
            'export reports' => 'Export reports to various formats',
            'view analytics' => 'View business analytics and dashboards',
            'manage reports' => 'Create and manage custom reports',

            // System Administration
            'manage settings' => 'Manage system settings and configuration',
            'view audit logs' => 'View system audit logs and activity',
            'manage system' => 'Full system administration access',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        $this->command->info("   ✓ Created " . count($permissions) . " permissions");
    }

    private function createRoles(): void
    {
        $this->command->info('👥 Creating roles and assigning permissions...');

        // Super Admin - Full access to everything
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->syncPermissions(Permission::all());
        $this->command->info('   ✓ Super Admin role created with all permissions');

        // Manager - Full operational access except system settings
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $managerPermissions = [
            'view users',
            'create users',
            'edit users',
            'assign roles',
            'view inventory',
            'manage inventory',
            'view stock movements',
            'adjust stock',
            'transfer stock',
            'view warehouses',
            'manage warehouses',
            'manage warehouse zones',
            'view purchasing',
            'manage purchasing',
            'approve purchase orders',
            'receive goods',
            'approve purchase receipts',
            'manage suppliers',
            'view purchase payments',
            'manage purchase payments',
            'view sales',
            'manage sales',
            'process sales',
            'manage cash sessions',
            'view pos',
            'manage sales orders',
            'approve sales orders',
            'view customers',
            'manage customers',
            'manage customer credit',
            'view customer payments',
            'manage customer payments',
            'view accounts payable',
            'manage accounts payable',
            'view accounts receivable',
            'manage accounts receivable',
            'view financial reports',
            'view reports',
            'export reports',
            'view analytics',
            'manage reports',
        ];
        $manager->syncPermissions($managerPermissions);
        $this->command->info('   ✓ Manager role created with operational permissions');

        // Purchasing Manager - Specialized for purchasing operations
        $purchasingManager = Role::firstOrCreate(['name' => 'purchasing-manager']);
        $purchasingManagerPermissions = [
            'view inventory',
            'view stock movements',
            'view warehouses',
            'view purchasing',
            'manage purchasing',
            'approve purchase orders',
            'receive goods',
            'approve purchase receipts',
            'manage suppliers',
            'view purchase payments',
            'manage purchase payments',
            'view accounts payable',
            'manage accounts payable',
            'view reports',
            'view analytics',
        ];
        $purchasingManager->syncPermissions($purchasingManagerPermissions);
        $this->command->info('   ✓ Purchasing Manager role created');

        // Sales Manager - Specialized for sales operations
        $salesManager = Role::firstOrCreate(['name' => 'sales-manager']);
        $salesManagerPermissions = [
            'view inventory',
            'view sales',
            'manage sales',
            'process sales',
            'manage cash sessions',
            'view pos',
            'manage sales orders',
            'approve sales orders',
            'view customers',
            'manage customers',
            'manage customer credit',
            'view customer payments',
            'manage customer payments',
            'view accounts receivable',
            'manage accounts receivable',
            'view financial reports',
            'view reports',
            'view analytics',
        ];
        $salesManager->syncPermissions($salesManagerPermissions);
        $this->command->info('   ✓ Sales Manager role created');

        // Warehouse Supervisor - Inventory and receiving focused
        $warehouseSupervisor = Role::firstOrCreate(['name' => 'warehouse-supervisor']);
        $warehouseSupervisorPermissions = [
            'view inventory',
            'manage inventory',
            'view stock movements',
            'adjust stock',
            'transfer stock',
            'view warehouses',
            'manage warehouses',
            'manage warehouse zones',
            'view purchasing',
            'receive goods',
            'approve purchase receipts',
            'view reports',
        ];
        $warehouseSupervisor->syncPermissions($warehouseSupervisorPermissions);
        $this->command->info('   ✓ Warehouse Supervisor role created');

        // Cashier - Point of sale operations
        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashierPermissions = [
            'view inventory',
            'view sales',
            'process sales',
            'manage cash sessions',
            'view pos',
            'view customers',
        ];
        $cashier->syncPermissions($cashierPermissions);
        $this->command->info('   ✓ Cashier role created');

        // Stock Clerk - Basic inventory and receiving
        $stockClerk = Role::firstOrCreate(['name' => 'stock-clerk']);
        $stockClerkPermissions = [
            'view inventory',
            'manage inventory',
            'view stock movements',
            'adjust stock',
            'view warehouses',
            'view purchasing',
            'receive goods',
        ];
        $stockClerk->syncPermissions($stockClerkPermissions);
        $this->command->info('   ✓ Stock Clerk role created');

        // Purchasing Clerk - Purchase order creation and management
        $purchasingClerk = Role::firstOrCreate(['name' => 'purchasing-clerk']);
        $purchasingClerkPermissions = [
            'view inventory',
            'view warehouses',
            'view purchasing',
            'manage purchasing',
            'receive goods',
            'manage suppliers',
            'view purchase payments',
        ];
        $purchasingClerk->syncPermissions($purchasingClerkPermissions);
        $this->command->info('   ✓ Purchasing Clerk role created');

        // Sales Associate - Basic sales operations
        $salesAssociate = Role::firstOrCreate(['name' => 'sales-associate']);
        $salesAssociatePermissions = [
            'view inventory',
            'view sales',
            'process sales',
            'view pos',
            'view customers',
            'manage customers',
            'manage sales orders',
        ];
        $salesAssociate->syncPermissions($salesAssociatePermissions);
        $this->command->info('   ✓ Sales Associate role created');
    }

    private function createUsers(): void
    {
        $this->command->info('👤 Creating admin users...');

        // Create a super admin user
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@sarange-erp.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole('super-admin');
        $this->command->info('   ✓ Super Admin user created (admin@sarange-erp.com)');

        // Create a manager user
        $manager = User::firstOrCreate(
            ['email' => 'manager@sarange-erp.com'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $manager->assignRole('manager');
        $this->command->info('   ✓ Manager user created (manager@sarange-erp.com)');

        // Create a cashier user
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@sarange-erp.com'],
            [
                'name' => 'Cashier User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $cashier->assignRole('cashier');
        $this->command->info('   ✓ Cashier user created (cashier@sarange-erp.com)');

        $this->command->info('');
        $this->command->info('🔐 Default Login Credentials:');
        $this->command->info('   Super Admin: admin@sarange-erp.com / password');
        $this->command->info('   Manager: manager@sarange-erp.com / password');
        $this->command->info('   Cashier: cashier@sarange-erp.com / password');
        $this->command->info('');
        $this->command->info('⚠️  IMPORTANT: Change these default passwords immediately after deployment!');
    }
}
