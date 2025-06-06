<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WarehousePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create warehouse permissions
        $warehousePermissions = [
            'view warehouses',
            'manage warehouses',
            'view warehouse zones',
            'manage warehouse zones',
            'view warehouse stocks',
            'manage warehouse stocks',
            'view stock transfers',
            'manage stock transfers',
            'approve transfers',
            'receive transfers',
            'ship transfers',
        ];

        foreach ($warehousePermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign warehouse permissions to existing roles
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($warehousePermissions);
        }

        $manager = Role::where('name', 'Manager')->first();
        if ($manager) {
            $manager->givePermissionTo([
                'view warehouses',
                'manage warehouses',
                'view warehouse zones',
                'manage warehouse zones',
                'view warehouse stocks',
                'manage warehouse stocks',
                'view stock transfers',
                'manage stock transfers',
                'approve transfers',
                'receive transfers',
                'ship transfers',
            ]);
        }

        // Create warehouse-specific roles
        $warehouseManager = Role::firstOrCreate(['name' => 'Warehouse Manager']);
        $warehouseManager->givePermissionTo([
            'view warehouses',
            'view warehouse zones',
            'view warehouse stocks',
            'manage warehouse stocks',
            'view stock transfers',
            'manage stock transfers',
            'approve transfers',
            'receive transfers',
            'ship transfers',
        ]);

        $warehouseStaff = Role::firstOrCreate(['name' => 'Warehouse Staff']);
        $warehouseStaff->givePermissionTo([
            'view warehouses',
            'view warehouse zones',
            'view warehouse stocks',
            'view stock transfers',
            'receive transfers',
            'ship transfers',
        ]);

        $this->command->info('Warehouse permissions created and assigned successfully!');
    }
}
