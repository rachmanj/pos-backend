<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Models\ProductStock;
use App\Models\WarehouseStock;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Main Warehouse (Default)
        $mainWarehouse = Warehouse::create([
            'code' => 'WH001',
            'name' => 'Main Warehouse',
            'description' => 'Primary warehouse for main operations',
            'type' => 'main',
            'status' => 'active',
            'address' => 'Jl. Industri Raya No. 123',
            'city' => 'Jakarta',
            'state' => 'DKI Jakarta',
            'postal_code' => '12345',
            'country' => 'Indonesia',
            'phone' => '+62-21-1234567',
            'email' => 'warehouse@company.com',
            'manager_name' => 'John Doe',
            'manager_phone' => '+62-812-3456789',
            'total_area' => 5000.00,
            'storage_area' => 4000.00,
            'max_capacity' => 10000,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'is_default' => true,
            'sort_order' => 1,
        ]);

        // Create Branch Warehouse
        $branchWarehouse = Warehouse::create([
            'code' => 'WH002',
            'name' => 'Branch Warehouse - Surabaya',
            'description' => 'Branch warehouse for East Java operations',
            'type' => 'branch',
            'status' => 'active',
            'address' => 'Jl. Raya Surabaya No. 456',
            'city' => 'Surabaya',
            'state' => 'Jawa Timur',
            'postal_code' => '60123',
            'country' => 'Indonesia',
            'phone' => '+62-31-7654321',
            'email' => 'surabaya@company.com',
            'manager_name' => 'Jane Smith',
            'manager_phone' => '+62-812-9876543',
            'total_area' => 3000.00,
            'storage_area' => 2500.00,
            'max_capacity' => 6000,
            'opening_time' => '08:00',
            'closing_time' => '17:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'is_default' => false,
            'sort_order' => 2,
        ]);

        // Create Distribution Center
        $distributionCenter = Warehouse::create([
            'code' => 'WH003',
            'name' => 'Distribution Center - Bandung',
            'description' => 'Distribution center for West Java region',
            'type' => 'distribution',
            'status' => 'active',
            'address' => 'Jl. Soekarno Hatta No. 789',
            'city' => 'Bandung',
            'state' => 'Jawa Barat',
            'postal_code' => '40123',
            'country' => 'Indonesia',
            'phone' => '+62-22-1111222',
            'email' => 'bandung@company.com',
            'manager_name' => 'Bob Wilson',
            'manager_phone' => '+62-812-1111222',
            'total_area' => 2000.00,
            'storage_area' => 1800.00,
            'max_capacity' => 4000,
            'opening_time' => '07:00',
            'closing_time' => '18:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'is_default' => false,
            'sort_order' => 3,
        ]);

        // Create zones for Main Warehouse
        $this->createWarehouseZones($mainWarehouse);

        // Create zones for Branch Warehouse
        $this->createWarehouseZones($branchWarehouse);

        // Create zones for Distribution Center
        $this->createWarehouseZones($distributionCenter);

        // Migrate existing product stocks to main warehouse
        $this->migrateExistingStocks($mainWarehouse);

        $this->command->info('Warehouses and zones created successfully!');
    }

    private function createWarehouseZones(Warehouse $warehouse): void
    {
        $zones = [
            [
                'code' => 'A1',
                'name' => 'Receiving Area',
                'description' => 'Area for receiving incoming goods',
                'type' => 'receiving',
                'status' => 'active',
                'aisle' => 'A',
                'row' => '1',
                'area' => 200.00,
                'max_capacity' => 500,
                'sort_order' => 1,
            ],
            [
                'code' => 'B1',
                'name' => 'General Storage Zone 1',
                'description' => 'General purpose storage area',
                'type' => 'storage',
                'status' => 'active',
                'aisle' => 'B',
                'row' => '1',
                'area' => 800.00,
                'max_capacity' => 2000,
                'sort_order' => 2,
            ],
            [
                'code' => 'B2',
                'name' => 'General Storage Zone 2',
                'description' => 'Additional general storage area',
                'type' => 'storage',
                'status' => 'active',
                'aisle' => 'B',
                'row' => '2',
                'area' => 800.00,
                'max_capacity' => 2000,
                'sort_order' => 3,
            ],
            [
                'code' => 'C1',
                'name' => 'Picking Zone',
                'description' => 'Area for order picking operations',
                'type' => 'picking',
                'status' => 'active',
                'aisle' => 'C',
                'row' => '1',
                'area' => 300.00,
                'max_capacity' => 800,
                'sort_order' => 4,
            ],
            [
                'code' => 'D1',
                'name' => 'Shipping Area',
                'description' => 'Area for outgoing shipments',
                'type' => 'shipping',
                'status' => 'active',
                'aisle' => 'D',
                'row' => '1',
                'area' => 200.00,
                'max_capacity' => 500,
                'sort_order' => 5,
            ],
            [
                'code' => 'E1',
                'name' => 'Cold Storage',
                'description' => 'Temperature controlled storage',
                'type' => 'storage',
                'status' => 'active',
                'aisle' => 'E',
                'row' => '1',
                'area' => 150.00,
                'max_capacity' => 300,
                'temperature_controlled' => true,
                'min_temperature' => 2.00,
                'max_temperature' => 8.00,
                'sort_order' => 6,
            ],
            [
                'code' => 'Q1',
                'name' => 'Quarantine Zone',
                'description' => 'Area for quarantined items',
                'type' => 'quarantine',
                'status' => 'active',
                'aisle' => 'Q',
                'row' => '1',
                'area' => 100.00,
                'max_capacity' => 200,
                'restricted_access' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($zones as $zoneData) {
            $zoneData['warehouse_id'] = $warehouse->id;
            WarehouseZone::create($zoneData);
        }
    }

    private function migrateExistingStocks(Warehouse $mainWarehouse): void
    {
        // Get all existing product stocks
        $existingStocks = ProductStock::with(['product'])->get();

        foreach ($existingStocks as $stock) {
            // Get the product's base unit (assuming products have a base unit)
            $product = $stock->product;
            if (!$product) {
                continue;
            }

            // Find the product's base unit or use the first available unit
            $baseUnit = $product->units()->where('is_base_unit', true)->first() ??
                $product->units()->first();

            if (!$baseUnit) {
                continue; // Skip if no unit found
            }

            // Create warehouse stock record
            WarehouseStock::create([
                'warehouse_id' => $mainWarehouse->id,
                'warehouse_zone_id' => null, // Will be assigned later
                'product_id' => $stock->product_id,
                'unit_id' => $baseUnit->id,
                'quantity' => $stock->current_stock,
                'reserved_quantity' => $stock->reserved_stock,
                'available_quantity' => $stock->available_stock,
                'minimum_stock' => $product->min_stock_level ?? 0,
                'maximum_stock' => $product->max_stock_level,
                'reorder_point' => $product->min_stock_level ?? 0,
                'reorder_quantity' => 0,
                'average_cost' => $product->cost_price ?? 0,
                'last_cost' => $product->cost_price ?? 0,
                'total_value' => $stock->current_stock * ($product->cost_price ?? 0),
                'status' => 'available',
                'is_active' => true,
                'last_movement_at' => $stock->updated_at,
            ]);

            // Mark the original stock as legacy
            $stock->update([
                'warehouse_id' => $mainWarehouse->id,
                'is_legacy' => true,
            ]);
        }

        $this->command->info('Migrated ' . $existingStocks->count() . ' existing stock records to main warehouse.');
    }
}
