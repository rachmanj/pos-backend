<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ARPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create AR-specific permissions
        $arPermissions = [
            // Customer Payment Receive permissions
            'view ar payments' => 'View customer payment receives and AR data',
            'manage ar payments' => 'Create and edit customer payment receives',
            'process ar payments' => 'Process customer payments and allocations',
            'verify ar payments' => 'Verify customer payment receives',
            'approve ar payments' => 'Approve customer payment receives',
            'reject ar payments' => 'Reject customer payment receives',
            'delete ar payments' => 'Delete customer payment receives',

            // Credit Management permissions
            'view credit limits' => 'View customer credit limits and status',
            'manage credit limits' => 'Create and edit customer credit limits',
            'approve credit limits' => 'Approve credit limit changes',
            'review credit limits' => 'Perform credit limit reviews',

            // Payment Allocation permissions
            'allocate payments' => 'Allocate payments to sales',
            'reverse allocations' => 'Reverse payment allocations',
            'auto allocate payments' => 'Use auto-allocation features',

            // AR Aging and Reporting permissions
            'view ar aging' => 'View AR aging reports and analysis',
            'generate ar reports' => 'Generate AR reports and snapshots',
            'export ar data' => 'Export AR data and reports',

            // Payment Schedule permissions
            'view payment schedules' => 'View customer payment schedules',
            'manage payment schedules' => 'Create and edit payment schedules',
            'approve payment schedules' => 'Approve payment schedule changes',

            // Collection Management permissions
            'view collections' => 'View collection activities and overdue accounts',
            'manage collections' => 'Manage collection activities and follow-ups',
            'write off debts' => 'Write off bad debts and uncollectible amounts'
        ];

        // Create permissions
        foreach ($arPermissions as $permission => $description) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to existing roles
        $this->assignPermissionsToRoles();

        $this->command->info('AR permissions created and assigned successfully!');
    }

    /**
     * Assign AR permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Super Admin gets all permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'verify ar payments',
                'approve ar payments',
                'reject ar payments',
                'delete ar payments',
                'view credit limits',
                'manage credit limits',
                'approve credit limits',
                'review credit limits',
                'allocate payments',
                'reverse allocations',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'export ar data',
                'view payment schedules',
                'manage payment schedules',
                'approve payment schedules',
                'view collections',
                'manage collections',
                'write off debts'
            ]);
        }

        // Manager gets most permissions except delete and write-off
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'verify ar payments',
                'approve ar payments',
                'reject ar payments',
                'view credit limits',
                'manage credit limits',
                'approve credit limits',
                'review credit limits',
                'allocate payments',
                'reverse allocations',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'export ar data',
                'view payment schedules',
                'manage payment schedules',
                'approve payment schedules',
                'view collections',
                'manage collections'
            ]);
        }

        // Finance Manager gets comprehensive AR permissions
        $financeManager = Role::where('name', 'finance-manager')->first();
        if ($financeManager) {
            $financeManager->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'verify ar payments',
                'approve ar payments',
                'reject ar payments',
                'view credit limits',
                'manage credit limits',
                'approve credit limits',
                'review credit limits',
                'allocate payments',
                'reverse allocations',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'export ar data',
                'view payment schedules',
                'manage payment schedules',
                'approve payment schedules',
                'view collections',
                'manage collections',
                'write off debts'
            ]);
        }

        // Sales Manager gets sales-related AR permissions
        $salesManager = Role::where('name', 'sales-manager')->first();
        if ($salesManager) {
            $salesManager->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'view credit limits',
                'manage credit limits',
                'review credit limits',
                'allocate payments',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'view payment schedules',
                'manage payment schedules',
                'view collections',
                'manage collections'
            ]);
        }

        // Accountant gets processing and reporting permissions
        $accountant = Role::where('name', 'accountant')->first();
        if ($accountant) {
            $accountant->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'verify ar payments',
                'view credit limits',
                'review credit limits',
                'allocate payments',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'export ar data',
                'view payment schedules',
                'view collections'
            ]);
        }

        // Customer Service gets view and basic processing permissions
        $customerService = Role::where('name', 'customer-service')->first();
        if ($customerService) {
            $customerService->givePermissionTo([
                'view ar payments',
                'process ar payments',
                'view credit limits',
                'view ar aging',
                'view payment schedules',
                'view collections'
            ]);
        }

        // Account Manager gets customer-focused AR permissions
        $accountManager = Role::where('name', 'account-manager')->first();
        if ($accountManager) {
            $accountManager->givePermissionTo([
                'view ar payments',
                'manage ar payments',
                'process ar payments',
                'view credit limits',
                'manage credit limits',
                'allocate payments',
                'auto allocate payments',
                'view ar aging',
                'generate ar reports',
                'view payment schedules',
                'manage payment schedules',
                'view collections',
                'manage collections'
            ]);
        }

        // Collection Agent gets collection-specific permissions
        $collectionAgent = Role::firstOrCreate(['name' => 'collection-agent']);
        $collectionAgent->givePermissionTo([
            'view ar payments',
            'view credit limits',
            'view ar aging',
            'generate ar reports',
            'view payment schedules',
            'view collections',
            'manage collections'
        ]);

        // AR Clerk gets basic AR processing permissions
        $arClerk = Role::firstOrCreate(['name' => 'ar-clerk']);
        $arClerk->givePermissionTo([
            'view ar payments',
            'manage ar payments',
            'process ar payments',
            'view credit limits',
            'allocate payments',
            'auto allocate payments',
            'view ar aging',
            'view payment schedules'
        ]);

        // Credit Analyst gets credit management permissions
        $creditAnalyst = Role::firstOrCreate(['name' => 'credit-analyst']);
        $creditAnalyst->givePermissionTo([
            'view ar payments',
            'view credit limits',
            'manage credit limits',
            'review credit limits',
            'view ar aging',
            'generate ar reports',
            'export ar data',
            'view payment schedules',
            'manage payment schedules',
            'view collections'
        ]);
    }
}
