<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(InventoryPermissionsSeeder::class);

        // Create a super admin user
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@pos-atk.com',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole('super-admin');

        // Create a test manager
        $manager = User::factory()->create([
            'name' => 'Manager User',
            'email' => 'manager@pos-atk.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole('manager');

        // Create a test cashier
        $cashier = User::factory()->create([
            'name' => 'Cashier User',
            'email' => 'cashier@pos-atk.com',
            'password' => Hash::make('password'),
        ]);
        $cashier->assignRole('cashier');
    }
}
