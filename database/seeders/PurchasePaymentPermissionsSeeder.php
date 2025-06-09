<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PurchasePaymentPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create purchase payment permissions
        $permissions = [
            'view payments' => 'View payment records and reports',
            'manage payments' => 'Create, edit, and manage payment records',
            'approve payments' => 'Approve payment transactions',
            'view supplier balances' => 'View supplier outstanding balances',
            'manage supplier balances' => 'Manage supplier credit limits and balances',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(['name' => $name]);
        }

        // Assign permissions to roles
        $rolePermissions = [
            'super-admin' => [
                'view payments',
                'manage payments',
                'approve payments',
                'view supplier balances',
                'manage supplier balances',
            ],
            'manager' => [
                'view payments',
                'manage payments',
                'approve payments',
                'view supplier balances',
                'manage supplier balances',
            ],
            'purchasing-manager' => [
                'view payments',
                'manage payments',
                'approve payments',
                'view supplier balances',
                'manage supplier balances',
            ],
            'purchasing-clerk' => [
                'view payments',
                'manage payments',
                'view supplier balances',
            ],
            'finance-manager' => [
                'view payments',
                'manage payments',
                'approve payments',
                'view supplier balances',
                'manage supplier balances',
            ],
            'accountant' => [
                'view payments',
                'manage payments',
                'view supplier balances',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($rolePerms as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission && !$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        // Create finance roles if they don't exist
        $financeRoles = [
            'finance-manager' => 'Finance Manager - Full financial management access',
            'accountant' => 'Accountant - Financial record management',
        ];

        foreach ($financeRoles as $roleName => $description) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Give basic permissions to new finance roles
            if ($roleName === 'finance-manager') {
                $basicPermissions = [
                    'view payments',
                    'manage payments',
                    'approve payments',
                    'view supplier balances',
                    'manage supplier balances',
                    'view reports',
                    'view financial reports',
                ];
            } else { // accountant
                $basicPermissions = [
                    'view payments',
                    'manage payments',
                    'view supplier balances',
                    'view reports',
                    'view financial reports',
                ];
            }

            foreach ($basicPermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && !$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        echo "Purchase payment permissions created and assigned successfully!\n";
        echo "Created permissions:\n";
        foreach ($permissions as $name => $description) {
            echo "- {$name}: {$description}\n";
        }
        echo "\nAssigned permissions to roles:\n";
        foreach ($rolePermissions as $roleName => $rolePerms) {
            echo "- {$roleName}: " . implode(', ', $rolePerms) . "\n";
        }
    }
}
