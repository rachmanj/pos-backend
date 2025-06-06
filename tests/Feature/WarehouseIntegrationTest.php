<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseZone;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class WarehouseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Warehouse $mainWarehouse;
    private Warehouse $branchWarehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create warehouse permissions for tests
        $permissions = [
            'manage warehouses',
            'view warehouses',
            'manage stock transfers',
            'view stock transfers',
            'view inventory',
            'manage inventory',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::create(['name' => $permission]);
        }

        // Create test user with warehouse permissions
        $this->user = User::factory()->create();
        $role = Role::create(['name' => 'warehouse_manager']);
        $role->givePermissionTo($permissions);
        $this->user->assignRole($role);

        // Create test warehouses
        $this->mainWarehouse = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->branchWarehouse = Warehouse::factory()->create([
            'name' => 'Branch Warehouse',
            'code' => 'BRANCH',
            'is_default' => false,
            'is_active' => true,
        ]);

        // Create warehouse zones
        WarehouseZone::factory()->create([
            'warehouse_id' => $this->mainWarehouse->id,
            'name' => 'Zone A',
            'code' => 'A-01',
        ]);

        WarehouseZone::factory()->create([
            'warehouse_id' => $this->branchWarehouse->id,
            'name' => 'Zone B',
            'code' => 'B-01',
        ]);

        // Create test product
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
        ]);

        // Create initial stock in main warehouse
        WarehouseStock::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->product->id,
            'current_stock' => 100,
            'reserved_stock' => 0,
            'reorder_level' => 10,
        ]);
    }

    /** @test */
    public function can_create_warehouse_with_zones()
    {
        $warehouseData = [
            'name' => 'New Warehouse',
            'code' => 'NEW',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
            'phone' => '+1234567890',
            'email' => 'new@warehouse.com',
            'manager_name' => 'Test Manager',
            'capacity' => 10000,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/warehouses', $warehouseData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Warehouse',
                    'code' => 'NEW',
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'New Warehouse',
            'code' => 'NEW',
        ]);
    }

    /** @test */
    public function can_transfer_stock_between_warehouses()
    {
        $transferData = [
            'from_warehouse_id' => $this->mainWarehouse->id,
            'to_warehouse_id' => $this->branchWarehouse->id,
            'reference_number' => 'TXN-001',
            'notes' => 'Test transfer',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'notes' => 'Transfer 20 units',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/stock-transfers', $transferData);

        $response->assertStatus(201);

        // Check transfer was created
        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $this->mainWarehouse->id,
            'to_warehouse_id' => $this->branchWarehouse->id,
            'reference_number' => 'TXN-001',
            'status' => 'pending',
        ]);

        // Check transfer items were created
        $this->assertDatabaseHas('stock_transfer_items', [
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);

        // Stock should be reserved in source warehouse
        $mainStock = WarehouseStock::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertEquals(20, $mainStock->reserved_stock);
    }

    /** @test */
    public function can_approve_and_ship_stock_transfer()
    {
        // Create a transfer
        $transfer = StockTransfer::create([
            'from_warehouse_id' => $this->mainWarehouse->id,
            'to_warehouse_id' => $this->branchWarehouse->id,
            'reference_number' => 'TXN-002',
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'notes' => 'Test transfer',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 15,
        ]);

        // Approve the transfer
        $response = $this->actingAs($this->user)
            ->postJson("/api/stock-transfers/{$transfer->id}/approve");

        $response->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);

        // Ship the transfer
        $shipData = [
            'shipped_at' => now()->toISOString(),
            'tracking_number' => 'TRACK-001',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'shipped_quantity' => 15,
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/stock-transfers/{$transfer->id}/ship", $shipData);

        $response->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('shipped', $transfer->status);

        // Check source warehouse stock is reduced
        $mainStock = WarehouseStock::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertEquals(85, $mainStock->current_stock);
        $this->assertEquals(0, $mainStock->reserved_stock);
    }

    /** @test */
    public function can_receive_stock_transfer()
    {
        // Create and process transfer to shipped status
        $transfer = StockTransfer::create([
            'from_warehouse_id' => $this->mainWarehouse->id,
            'to_warehouse_id' => $this->branchWarehouse->id,
            'reference_number' => 'TXN-003',
            'status' => 'shipped',
            'requested_by' => $this->user->id,
            'approved_by' => $this->user->id,
            'shipped_by' => $this->user->id,
            'shipped_at' => now(),
        ]);

        $transferItem = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'shipped_quantity' => 25,
        ]);

        // Receive the transfer
        $receiveData = [
            'received_at' => now()->toISOString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'received_quantity' => 25,
                    'quality_status' => 'good',
                    'notes' => 'All items received in good condition',
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/stock-transfers/{$transfer->id}/receive", $receiveData);

        $response->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);

        // Check destination warehouse stock is increased
        $branchStock = WarehouseStock::where('warehouse_id', $this->branchWarehouse->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($branchStock);
        $this->assertEquals(25, $branchStock->current_stock);
    }

    /** @test */
    public function can_get_warehouse_analytics()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/warehouses/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_warehouses',
                    'active_warehouses',
                    'total_capacity',
                    'capacity_utilization',
                    'total_stock_value',
                    'pending_transfers',
                ]
            ]);
    }

    /** @test */
    public function can_get_individual_warehouse_analytics()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/warehouses/{$this->mainWarehouse->id}/analytics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'warehouse',
                    'total_products',
                    'total_stock_value',
                    'stock_movements_today',
                    'capacity_utilization',
                    'low_stock_products',
                    'zones',
                ]
            ]);
    }

    /** @test */
    public function handles_transfer_validation_errors()
    {
        $invalidTransferData = [
            'from_warehouse_id' => $this->mainWarehouse->id,
            'to_warehouse_id' => $this->mainWarehouse->id, // Same warehouse
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 200, // More than available
                ]
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/stock-transfers', $invalidTransferData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_warehouse_id']);
    }

    /** @test */
    public function can_search_warehouses_by_filters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/warehouses?search=Main&status=active');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Main Warehouse']);
    }

    /** @test */
    public function can_manage_warehouse_zones()
    {
        $zoneData = [
            'warehouse_id' => $this->mainWarehouse->id,
            'name' => 'Cold Storage',
            'code' => 'CS-01',
            'zone_type' => 'cold_storage',
            'capacity' => 500,
            'temperature_min' => -5,
            'temperature_max' => 5,
            'humidity_min' => 80,
            'humidity_max' => 90,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/warehouse-zones', $zoneData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('warehouse_zones', [
            'name' => 'Cold Storage',
            'code' => 'CS-01',
            'zone_type' => 'cold_storage',
        ]);
    }
}
