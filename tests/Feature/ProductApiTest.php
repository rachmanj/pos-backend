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

class ProductApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $viewerUser;
    private Category $category;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view inventory']);
        Permission::create(['name' => 'manage inventory']);
        Permission::create(['name' => 'process sales']);

        // Create roles
        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo(['view inventory', 'manage inventory']);

        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('view inventory');

        // Create users
        $this->user = User::factory()->create();
        $this->user->assignRole('manager');

        $this->viewerUser = User::factory()->create();
        $this->viewerUser->assignRole('viewer');

        // Create test data
        $this->category = Category::factory()->active()->create();
        $this->unit = Unit::factory()->piece()->create();
    }

    /** @test */
    public function it_can_list_products()
    {
        Product::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'sku',
                        'barcode',
                        'selling_price',
                        'status',
                        'current_stock',
                        'category',
                        'unit'
                    ]
                ],
                'meta'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_products_by_category()
    {
        $otherCategory = Category::factory()->create();

        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Product::factory()->create([
            'category_id' => $otherCategory->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/products?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->category->id, $response->json('data.0.category.id'));
    }

    /** @test */
    public function it_can_search_products()
    {
        Product::factory()->create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IPH15PRO',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Product::factory()->create([
            'name' => 'Samsung Galaxy',
            'sku' => 'SAM-GAL',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/products?search=iPhone');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('iPhone', $response->json('data.0.name'));
    }

    /** @test */
    public function it_can_create_a_product()
    {
        $productData = [
            'name' => 'Test Product',
            'description' => 'This is a test product',
            'sku' => 'TEST-001',
            'barcode' => '1234567890123',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'cost_price' => 50.00,
            'selling_price' => 99.99,
            'min_stock_level' => 10,
            'max_stock_level' => 100,
            'tax_rate' => 10.0,
            'status' => 'active'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Product created successfully',
                'data' => [
                    'name' => 'Test Product',
                    'sku' => 'TEST-001',
                    'selling_price' => 99.99,
                    'status' => 'active'
                ]
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001'
        ]);

        // Check that ProductStock was created
        $product = Product::where('sku', 'TEST-001')->first();
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'current_stock' => 0
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_product()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'sku',
                'unit_id',
                'selling_price'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_sku()
    {
        Product::factory()->create([
            'sku' => 'DUPLICATE-SKU',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/products', [
            'name' => 'Another Product',
            'sku' => 'DUPLICATE-SKU',
            'unit_id' => $this->unit->id,
            'selling_price' => 50.00,
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('sku');
    }

    /** @test */
    public function it_can_show_a_product_with_stock_information()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        // Update stock
        $product->stock->update(['current_stock' => 50]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'current_stock' => 50,
                    'category' => [
                        'id' => $this->category->id
                    ],
                    'unit' => [
                        'id' => $this->unit->id
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        $updateData = [
            'name' => 'Updated Product Name',
            'selling_price' => 199.99,
            'status' => 'inactive'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/products/{$product->id}", array_merge([
            'sku' => $product->sku,
            'unit_id' => $this->unit->id,
            'selling_price' => 199.99,
            'status' => 'inactive'
        ], $updateData));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Product updated successfully',
                'data' => [
                    'name' => 'Updated Product Name',
                    'selling_price' => 199.99,
                    'status' => 'inactive'
                ]
            ]);
    }

    /** @test */
    public function it_can_delete_a_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Product deleted successfully'
            ]);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_stocks', ['product_id' => $product->id]);
    }

    /** @test */
    public function it_can_find_product_by_barcode()
    {
        $product = Product::factory()->create([
            'barcode' => '1234567890123',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/products/barcode/1234567890123');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'barcode' => '1234567890123'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_barcode()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/products/barcode/9999999999999');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_get_low_stock_products()
    {
        // Create product with low stock
        $lowStockProduct = Product::factory()->create([
            'min_stock_level' => 10,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);
        $lowStockProduct->stock->update(['current_stock' => 5]);

        // Create product with normal stock
        $normalStockProduct = Product::factory()->create([
            'min_stock_level' => 10,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);
        $normalStockProduct->stock->update(['current_stock' => 20]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($lowStockProduct->id, $response->json('data.0.id'));
    }

    /** @test */
    public function it_can_get_product_stock_history()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        // Create stock movements
        StockMovement::factory()->count(3)->create([
            'product_id' => $product->id,
            'user_id' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/products/{$product->id}/stock-history");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function viewer_user_can_access_product_endpoints()
    {
        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/products');
        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_product()
    {
        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'unit_id' => $this->unit->id,
            'selling_price' => 99.99,
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_update_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Product',
            'sku' => $product->sku,
            'unit_id' => $this->unit->id,
            'selling_price' => 199.99,
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_delete_product()
    {
        $product = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_products()
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(401);
    }
}
