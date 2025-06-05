<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $viewerUser;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view inventory']);
        Permission::create(['name' => 'manage inventory']);
        Permission::create(['name' => 'adjust stock']);

        // Create roles
        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo(['view inventory', 'manage inventory', 'adjust stock']);

        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('view inventory');

        // Create users
        $this->user = User::factory()->create();
        $this->user->assignRole('manager');

        $this->viewerUser = User::factory()->create();
        $this->viewerUser->assignRole('viewer');

        // Create test product
        $category = Category::factory()->active()->create();
        $unit = Unit::factory()->piece()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'min_stock_level' => 10
        ]);
    }

    /** @test */
    public function it_can_list_stock_movements()
    {
        StockMovement::factory()->count(3)->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/stock-movements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'movement_type',
                        'quantity',
                        'unit_cost',
                        'reference_type',
                        'reference_id',
                        'notes',
                        'user_id',
                        'created_at'
                    ]
                ],
                'meta'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_stock_movements_by_product()
    {
        $otherProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'unit_id' => $this->product->unit_id
        ]);

        StockMovement::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);

        StockMovement::factory()->create([
            'product_id' => $otherProduct->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/stock-movements?product_id={$this->product->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->product->id, $response->json('data.0.product_id'));
    }

    /** @test */
    public function it_can_create_stock_in_movement()
    {
        $movementData = [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => 50,
            'unit_cost' => 25.00,
            'reference_type' => 'purchase',
            'reference_id' => 123,
            'notes' => 'Initial stock from supplier'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', $movementData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Stock movement recorded successfully',
                'data' => [
                    'movement_type' => 'in',
                    'quantity' => 50,
                    'unit_cost' => 25.00
                ]
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => 50
        ]);

        // Check that product stock was updated
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $this->product->id,
            'current_stock' => 50
        ]);
    }

    /** @test */
    public function it_can_create_stock_out_movement()
    {
        // First add stock
        $this->product->stock->update(['current_stock' => 100]);

        $movementData = [
            'product_id' => $this->product->id,
            'movement_type' => 'out',
            'quantity' => 30,
            'reference_type' => 'sale',
            'reference_id' => 456,
            'notes' => 'Sale to customer'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', $movementData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'out',
            'quantity' => 30
        ]);

        // Check that product stock was updated (100 - 30 = 70)
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $this->product->id,
            'current_stock' => 70
        ]);
    }

    /** @test */
    public function it_can_create_stock_adjustment()
    {
        // Current stock is 0, adjust to 25
        $adjustmentData = [
            'product_id' => $this->product->id,
            'movement_type' => 'adjustment',
            'quantity' => 25,
            'notes' => 'Physical count adjustment'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements/adjustment', $adjustmentData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Stock adjustment completed successfully'
            ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'adjustment',
            'quantity' => 25
        ]);

        // Check that product stock was set to the adjustment quantity
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $this->product->id,
            'current_stock' => 25
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_stock_movement()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'product_id',
                'movement_type',
                'quantity'
            ]);
    }

    /** @test */
    public function it_validates_movement_type()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'invalid',
            'quantity' => 10
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('movement_type');
    }

    /** @test */
    public function it_validates_positive_quantity()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => -10
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    /** @test */
    public function it_can_show_stock_movement_details()
    {
        $movement = StockMovement::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/stock-movements/{$movement->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $movement->id,
                    'product_id' => $this->product->id,
                    'movement_type' => $movement->movement_type,
                    'quantity' => $movement->quantity
                ]
            ]);
    }

    /** @test */
    public function it_can_get_stock_movements_by_product()
    {
        StockMovement::factory()->count(2)->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);

        // Create movement for different product
        $otherProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'unit_id' => $this->product->unit_id
        ]);
        StockMovement::factory()->create([
            'product_id' => $otherProduct->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/stock-movements/product/{$this->product->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $movement) {
            $this->assertEquals($this->product->id, $movement['product_id']);
        }
    }

    /** @test */
    public function it_can_perform_bulk_stock_adjustments()
    {
        $otherProduct = Product::factory()->create([
            'category_id' => $this->product->category_id,
            'unit_id' => $this->product->unit_id
        ]);

        $adjustments = [
            [
                'product_id' => $this->product->id,
                'quantity' => 100,
                'notes' => 'Bulk adjustment 1'
            ],
            [
                'product_id' => $otherProduct->id,
                'quantity' => 50,
                'notes' => 'Bulk adjustment 2'
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements/bulk-adjustment', [
            'adjustments' => $adjustments
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Bulk stock adjustments completed successfully',
                'processed' => 2
            ]);

        // Check both products had their stock adjusted
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $this->product->id,
            'current_stock' => 100
        ]);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $otherProduct->id,
            'current_stock' => 50
        ]);

        // Check stock movements were created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'adjustment',
            'quantity' => 100
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $otherProduct->id,
            'movement_type' => 'adjustment',
            'quantity' => 50
        ]);
    }

    /** @test */
    public function it_prevents_negative_stock_on_out_movements()
    {
        // Product has 0 stock by default
        $movementData = [
            'product_id' => $this->product->id,
            'movement_type' => 'out',
            'quantity' => 10,
            'notes' => 'Should fail - not enough stock'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', $movementData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Insufficient stock. Available: 0, Requested: 10'
            ]);
    }

    /** @test */
    public function viewer_user_can_view_stock_movements()
    {
        StockMovement::factory()->create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/stock-movements');
        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_stock_movements()
    {
        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/stock-movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => 10
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_stock_movements()
    {
        $response = $this->getJson('/api/stock-movements');

        $response->assertStatus(401);
    }

    /** @test */
    public function stock_movement_records_user_who_made_change()
    {
        $movementData = [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => 25
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/stock-movements', $movementData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'user_id' => $this->user->id
        ]);
    }
}
