<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AssignWarehousePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Super Admin role
        $superAdminRole = Role::where('name', 'super-admin')->first();

        if (!$superAdminRole) {
            $this->command->error('Super Admin role not found!');
            return;
        }

        // Get all warehouse permissions
        $warehousePermissions = [
            'view warehouses',
            'manage warehouses',
            'view warehouse zones',
            'manage warehouse zones',
            'view warehouse stocks',
            'manage warehouse stocks',
            'view transfers',
            'manage transfers',
            'approve transfers',
        ];

        foreach ($warehousePermissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();
            if ($permission && !$superAdminRole->hasPermissionTo($permission)) {
                $superAdminRole->givePermissionTo($permission);
                $this->command->info("Assigned permission: {$permissionName}");
            }
        }

        // Also assign to Manager role
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            foreach ($warehousePermissions as $permissionName) {
                $permission = Permission::where('name', $permissionName)->first();
                if ($permission && !$managerRole->hasPermissionTo($permission)) {
                    $managerRole->givePermissionTo($permission);
                }
            }
            $this->command->info("Assigned warehouse permissions to Manager role");
        }

        $this->command->info('Warehouse permissions assigned successfully!');
    }
}
