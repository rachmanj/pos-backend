<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Unit;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseOrderApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $approverUser;
    private User $viewerUser;
    private Supplier $supplier;
    private Product $product;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view purchasing']);
        Permission::create(['name' => 'manage purchasing']);
        Permission::create(['name' => 'approve purchase orders']);

        // Create roles
        $managerRole = Role::create(['name' => 'purchasing-manager']);
        $managerRole->givePermissionTo(['view purchasing', 'manage purchasing', 'approve purchase orders']);

        $clerkRole = Role::create(['name' => 'purchasing-clerk']);
        $clerkRole->givePermissionTo(['view purchasing', 'manage purchasing']);

        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('view purchasing');

        // Create users
        $this->user = User::factory()->create();
        $this->user->assignRole('purchasing-clerk');

        $this->approverUser = User::factory()->create();
        $this->approverUser->assignRole('purchasing-manager');

        $this->viewerUser = User::factory()->create();
        $this->viewerUser->assignRole('viewer');

        // Create test data
        $this->supplier = Supplier::factory()->create(['status' => 'active']);

        $category = Category::factory()->create();
        $this->unit = Unit::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $this->unit->id,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function it_can_list_purchase_orders()
    {
        PurchaseOrder::factory()->count(3)->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'po_number',
                        'order_date',
                        'expected_delivery_date',
                        'status',
                        'subtotal',
                        'tax_amount',
                        'total_amount',
                        'supplier' => [
                            'id',
                            'name'
                        ],
                        'creator' => [
                            'id',
                            'name'
                        ],
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'total'
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_purchase_orders_by_status()
    {
        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);
        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-orders?status=draft');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('draft', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_filter_purchase_orders_by_supplier()
    {
        $anotherSupplier = Supplier::factory()->create();

        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id
        ]);
        PurchaseOrder::factory()->create([
            'supplier_id' => $anotherSupplier->id,
            'created_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/purchase-orders?supplier_id={$this->supplier->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->supplier->id, $response->json('data.0.supplier.id'));
    }

    /** @test */
    public function it_can_create_a_purchase_order()
    {
        $purchaseOrderData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Test purchase order',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity_ordered' => 10,
                    'unit_price' => 50000
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-orders', $purchaseOrderData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Purchase order created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'po_number',
                    'status',
                    'subtotal',
                    'tax_amount',
                    'total_amount',
                    'supplier' => [
                        'id',
                        'name'
                    ],
                    'items'
                ]
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 50000
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_purchase_order()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id', 'order_date', 'items']);
    }

    /** @test */
    public function it_validates_purchase_order_items()
    {
        $purchaseOrderData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity_ordered' => 0, // Invalid quantity
                    'unit_price' => -100 // Invalid price
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-orders', $purchaseOrderData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.quantity_ordered',
                'items.0.unit_price'
            ]);
    }

    /** @test */
    public function it_can_show_a_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                    'status' => $purchaseOrder->status
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'supplier',
                    'creator',
                    'items' => [
                        '*' => [
                            'product',
                            'unit'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        $updateData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->addDay()->toDateString(),
            'notes' => 'Updated notes',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                    'quantity_ordered' => 15,
                    'unit_price' => 60000
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase order updated successfully'
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function it_cannot_update_non_draft_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        $updateData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => []
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/purchase-orders/{$purchaseOrder->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Purchase order cannot be edited in current status'
            ]);
    }

    /** @test */
    public function it_can_submit_purchase_order_for_approval()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/submit-for-approval");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase order submitted for approval successfully'
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'pending_approval'
        ]);
    }

    /** @test */
    public function it_cannot_submit_empty_purchase_order_for_approval()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/submit-for-approval");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Purchase order must have at least one item'
            ]);
    }

    /** @test */
    public function it_can_approve_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'pending_approval'
        ]);

        Sanctum::actingAs($this->approverUser);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase order approved successfully'
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'approved',
            'approved_by' => $this->approverUser->id
        ]);
    }

    /** @test */
    public function user_without_approval_permission_cannot_approve()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'pending_approval'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_cancel_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase order cancelled successfully'
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'cancelled'
        ]);
    }

    /** @test */
    public function it_can_duplicate_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-orders/{$purchaseOrder->id}/duplicate");

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Purchase order duplicated successfully'
            ]);

        $this->assertEquals(2, PurchaseOrder::count());

        $newPurchaseOrder = PurchaseOrder::where('id', '!=', $purchaseOrder->id)->first();
        $this->assertEquals('draft', $newPurchaseOrder->status);
        $this->assertEquals($this->user->id, $newPurchaseOrder->created_by);
        $this->assertNull($newPurchaseOrder->approved_by);
    }

    /** @test */
    public function it_can_delete_draft_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'draft'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase order deleted successfully'
            ]);

        $this->assertDatabaseMissing('purchase_orders', [
            'id' => $purchaseOrder->id
        ]);
    }

    /** @test */
    public function it_cannot_delete_non_draft_purchase_order()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Purchase order cannot be deleted in current status'
            ]);
    }

    /** @test */
    public function it_can_get_purchase_order_analytics()
    {
        PurchaseOrder::factory()->count(5)->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-orders/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_orders',
                    'total_amount',
                    'pending_approval',
                    'pending_delivery',
                    'monthly_stats',
                    'top_suppliers',
                    'status_breakdown'
                ]
            ]);
    }

    /** @test */
    public function viewer_user_can_view_purchase_orders()
    {
        PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/purchase-orders');

        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_purchase_order()
    {
        $purchaseOrderData = [
            'supplier_id' => $this->supplier->id,
            'order_date' => now()->toDateString(),
            'items' => []
        ];

        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/purchase-orders', $purchaseOrderData);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_purchase_orders()
    {
        $response = $this->getJson('/api/purchase-orders');

        $response->assertStatus(401);
    }
}
