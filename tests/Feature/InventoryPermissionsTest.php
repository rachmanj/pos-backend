<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $manager;
    private User $inventoryManager;
    private User $salesPerson;
    private User $cashier;
    private User $unauthorized;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'process sales',
            'view sales',
            'manage sales',
            'view reports',
            'manage reports',
            'adjust stock',
            'transfer stock'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles with specific permissions
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $superAdminRole->givePermissionTo($permissions);

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo([
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'process sales',
            'view sales',
            'manage sales',
            'view reports',
            'adjust stock',
            'transfer stock'
        ]);

        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo([
            'view inventory',
            'manage inventory',
            'view purchasing',
            'manage purchasing',
            'view sales',
            'view reports',
            'adjust stock'
        ]);

        $inventoryManagerRole = Role::create(['name' => 'inventory-manager']);
        $inventoryManagerRole->givePermissionTo([
            'view inventory',
            'manage inventory',
            'view purchasing',
            'view reports',
            'adjust stock',
            'transfer stock'
        ]);

        $salesPersonRole = Role::create(['name' => 'sales-person']);
        $salesPersonRole->givePermissionTo([
            'view inventory',
            'process sales',
            'view sales'
        ]);

        $cashierRole = Role::create(['name' => 'cashier']);
        $cashierRole->givePermissionTo(['view inventory', 'process sales']);

        // Create users and assign roles
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->inventoryManager = User::factory()->create();
        $this->inventoryManager->assignRole('inventory-manager');

        $this->salesPerson = User::factory()->create();
        $this->salesPerson->assignRole('sales-person');

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('cashier');

        $this->unauthorized = User::factory()->create();
        // No role assigned to unauthorized user
    }

    /** @test */
    public function super_admin_can_access_all_inventory_endpoints()
    {
        Sanctum::actingAs($this->superAdmin);

        // Test category endpoints
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'status' => 'active'
        ]);
        $response->assertStatus(201);

        // Test product endpoints
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        $response = $this->getJson('/api/products');
        $response->assertStatus(200);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'cost_price' => 50.00,
            'selling_price' => 99.99,
            'min_stock_level' => 5,
            'tax_rate' => 10.00,
            'status' => 'active'
        ]);
        $response->assertStatus(201);

        // Test unit endpoints
        $response = $this->getJson('/api/units');
        $response->assertStatus(200);

        $response = $this->postJson('/api/units', [
            'name' => 'Test Unit',
            'symbol' => 'tu'
        ]);
        $response->assertStatus(201);

        // Test supplier endpoints
        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(200);

        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP001',
            'email' => 'test@supplier.com',
            'payment_terms' => 30,
            'status' => 'active'
        ]);
        $response->assertStatus(201);
    }

    /** @test */
    public function inventory_manager_can_manage_inventory_but_not_purchasing()
    {
        Sanctum::actingAs($this->inventoryManager);

        // Can access inventory endpoints
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'status' => 'active'
        ]);
        $response->assertStatus(201);

        // Can view suppliers but not manage
        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(200);

        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP002',
            'email' => 'test2@supplier.com',
            'payment_terms' => 30,
            'status' => 'active'
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function sales_person_can_view_inventory_but_not_modify()
    {
        Sanctum::actingAs($this->salesPerson);

        // Can view inventory
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);

        $response = $this->getJson('/api/products');
        $response->assertStatus(200);

        // Cannot create/modify inventory
        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'status' => 'active'
        ]);
        $response->assertStatus(403);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'selling_price' => 99.99,
            'status' => 'active'
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function cashier_has_limited_inventory_access()
    {
        Sanctum::actingAs($this->cashier);

        // Can view basic inventory for POS
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);

        $response = $this->getJson('/api/products');
        $response->assertStatus(200);

        // Cannot access supplier information
        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(403);

        // Cannot modify inventory
        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'status' => 'active'
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function unauthorized_user_cannot_access_any_inventory_endpoints()
    {
        Sanctum::actingAs($this->unauthorized);

        // Cannot access any inventory endpoints
        $response = $this->getJson('/api/categories');
        $response->assertStatus(403);

        $response = $this->getJson('/api/products');
        $response->assertStatus(403);

        $response = $this->getJson('/api/units');
        $response->assertStatus(403);

        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(403);

        $response = $this->getJson('/api/stock-movements');
        $response->assertStatus(403);
    }

    /** @test */
    public function stock_adjustment_permissions_are_properly_enforced()
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'unit_id' => Unit::factory()->create()->id
        ]);

        // Inventory manager can adjust stock
        Sanctum::actingAs($this->inventoryManager);

        $response = $this->postJson('/api/stock-movements/adjustment', [
            'product_id' => $product->id,
            'adjustment_quantity' => 100,
            'reason' => 'Initial stock setup',
            'notes' => 'Initial stock'
        ]);
        $response->assertStatus(201);

        // Sales person cannot adjust stock
        Sanctum::actingAs($this->salesPerson);

        $response = $this->postJson('/api/stock-movements/adjustment', [
            'product_id' => $product->id,
            'adjustment_quantity' => 50,
            'reason' => 'Unauthorized attempt',
            'notes' => 'Should fail'
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function product_barcode_lookup_permissions()
    {
        $product = Product::factory()->create([
            'barcode' => '1234567890123',
            'category_id' => Category::factory()->create()->id,
            'unit_id' => Unit::factory()->create()->id
        ]);

        // Sales person and cashier can lookup products by barcode
        Sanctum::actingAs($this->salesPerson);
        $response = $this->getJson('/api/products/barcode/1234567890123');
        $response->assertStatus(200);

        Sanctum::actingAs($this->cashier);
        $response = $this->getJson('/api/products/barcode/1234567890123');
        $response->assertStatus(200);

        // Unauthorized user cannot lookup products
        Sanctum::actingAs($this->unauthorized);
        $response = $this->getJson('/api/products/barcode/1234567890123');
        $response->assertStatus(403);
    }

    /** @test */
    public function low_stock_alerts_are_accessible_to_inventory_roles()
    {
        Product::factory()->create([
            'min_stock_level' => 10,
            'category_id' => Category::factory()->create()->id,
            'unit_id' => Unit::factory()->create()->id
        ]);

        // Inventory manager can view low stock
        Sanctum::actingAs($this->inventoryManager);
        $response = $this->getJson('/api/products/low-stock');
        $response->assertStatus(200);

        // Manager can view low stock
        Sanctum::actingAs($this->manager);
        $response = $this->getJson('/api/products/low-stock');
        $response->assertStatus(200);

        // Sales person cannot view low stock reports
        Sanctum::actingAs($this->salesPerson);
        $response = $this->getJson('/api/products/low-stock');
        $response->assertStatus(403);
    }

    /** @test */
    public function unit_conversion_is_accessible_to_all_authorized_users()
    {
        $baseUnit = Unit::factory()->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'base_unit_id' => null,
            'conversion_factor' => 1.0
        ]);

        $gramUnit = Unit::factory()->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => 0.001
        ]);

        // All roles with inventory access can use unit conversion
        $users = [$this->superAdmin, $this->admin, $this->manager, $this->inventoryManager, $this->salesPerson, $this->cashier];

        foreach ($users as $user) {
            Sanctum::actingAs($user);

            $response = $this->postJson('/api/units/convert', [
                'from_unit_id' => $baseUnit->id,
                'to_unit_id' => $gramUnit->id,
                'quantity' => 1
            ]);

            $response->assertStatus(200);
        }

        // Unauthorized user cannot convert units
        Sanctum::actingAs($this->unauthorized);
        $response = $this->postJson('/api/units/convert', [
            'from_unit_id' => $baseUnit->id,
            'to_unit_id' => $gramUnit->id,
            'quantity' => 1
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function category_tree_access_permissions()
    {
        Category::factory()->create(['name' => 'Electronics']);

        // All users with inventory view can access category tree
        $users = [$this->superAdmin, $this->admin, $this->manager, $this->inventoryManager, $this->salesPerson, $this->cashier];

        foreach ($users as $user) {
            Sanctum::actingAs($user);

            $response = $this->getJson('/api/categories/tree');
            $response->assertStatus(200);
        }

        // Unauthorized user cannot access category tree
        Sanctum::actingAs($this->unauthorized);
        $response = $this->getJson('/api/categories/tree');
        $response->assertStatus(403);
    }
}
