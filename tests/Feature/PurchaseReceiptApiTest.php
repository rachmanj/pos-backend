<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Unit;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseReceiptApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $approverUser;
    private User $viewerUser;
    private Supplier $supplier;
    private Product $product;
    private Unit $unit;
    private PurchaseOrder $purchaseOrder;
    private PurchaseOrderItem $purchaseOrderItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view purchasing']);
        Permission::create(['name' => 'manage purchasing']);
        Permission::create(['name' => 'receive goods']);
        Permission::create(['name' => 'approve purchase receipts']);
        Permission::create(['name' => 'manage inventory']);

        // Create roles
        $managerRole = Role::create(['name' => 'purchasing-manager']);
        $managerRole->givePermissionTo(['view purchasing', 'receive goods', 'approve purchase receipts']);

        $supervisorRole = Role::create(['name' => 'warehouse-supervisor']);
        $supervisorRole->givePermissionTo(['view purchasing', 'receive goods', 'approve purchase receipts', 'manage inventory']);

        $clerkRole = Role::create(['name' => 'purchasing-clerk']);
        $clerkRole->givePermissionTo(['view purchasing', 'receive goods']);

        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('view purchasing');

        // Create users
        $this->user = User::factory()->create();
        $this->user->assignRole('purchasing-clerk');

        $this->approverUser = User::factory()->create();
        $this->approverUser->assignRole('warehouse-supervisor');

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

        // Create product stock (or use existing)
        ProductStock::firstOrCreate([
            'product_id' => $this->product->id,
        ], [
            'current_stock' => 100,
            'reserved_stock' => 0
        ]);

        // Create purchase order and item
        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->user->id,
            'status' => 'approved'
        ]);

        $this->purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity_ordered' => 50,
            'unit_price' => 10000
        ]);
    }

    /** @test */
    public function it_can_list_purchase_receipts()
    {
        PurchaseReceipt::factory()->count(3)->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-receipts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'receipt_number',
                        'purchase_order_id',
                        'receipt_date',
                        'status',
                        'notes',
                        'quality_check_notes',
                        'stock_updated',
                        'purchase_order',
                        'receiver',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_purchase_receipts_by_status()
    {
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'draft'
        ]);
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-receipts?status=draft');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('draft', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_get_receivable_items_for_purchase_order()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/purchase-orders/{$this->purchaseOrder->id}/receivable-items");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'product',
                        'unit',
                        'quantity_ordered',
                        'quantity_received',
                        'remaining_quantity',
                        'unit_price',
                        'can_receive'
                    ]
                ]
            ]);

        $receivableItems = $response->json('data');
        $this->assertCount(1, $receivableItems);
        $this->assertEquals(50, $receivableItems[0]['remaining_quantity']);
        $this->assertTrue($receivableItems[0]['can_receive']);
    }

    /** @test */
    public function it_can_create_a_purchase_receipt()
    {
        $receiptData = [
            'purchase_order_id' => $this->purchaseOrder->id,
            'receipt_date' => now()->toDateString(),
            'notes' => 'Test receipt',
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'quantity_received' => 30,
                    'quantity_accepted' => 30,
                    'quantity_rejected' => 0,
                    'quality_notes' => 'Good quality items'
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-receipts', $receiptData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Purchase receipt created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'receipt_number',
                    'purchase_order_id',
                    'status',
                    'items'
                ]
            ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'complete'
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_order_item_id' => $this->purchaseOrderItem->id,
            'quantity_received' => 30,
            'quantity_accepted' => 30
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_purchase_receipt()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-receipts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_order_id', 'receipt_date', 'items']);
    }

    /** @test */
    public function it_validates_receipt_quantities()
    {
        $receiptData = [
            'purchase_order_id' => $this->purchaseOrder->id,
            'receipt_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'quantity_received' => 30,
                    'quantity_accepted' => 20,
                    'quantity_rejected' => 5 // Total 25, less than received 30
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-receipts', $receiptData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Quantity accepted + rejected must equal quantity received for all items'
            ]);
    }

    /** @test */
    public function it_validates_accepted_rejected_quantities()
    {
        $receiptData = [
            'purchase_order_id' => $this->purchaseOrder->id,
            'receipt_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $this->purchaseOrderItem->id,
                    'quantity_received' => 30,
                    'quantity_accepted' => 25,
                    'quantity_rejected' => 10 // Total 35, more than received 30
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/purchase-receipts', $receiptData);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_can_show_a_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id
        ]);

        PurchaseReceiptItem::factory()->passed()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $this->purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/purchase-receipts/{$receipt->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'status' => 'complete'
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'purchase_order',
                    'receiver',
                    'items' => [
                        '*' => [
                            'product',
                            'unit',
                            'purchase_order_item'
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'draft'
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $this->purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity_received' => 20
        ]);

        // Manually override the status back to draft since creating items might change it
        $receipt->update(['status' => 'draft']);

        $updateData = [
            'receipt_date' => now()->addDay()->toDateString(),
            'notes' => 'Updated notes',
            'items' => [
                [
                    'id' => $receiptItem->id,
                    'quantity_received' => 25,
                    'quantity_accepted' => 25,
                    'quantity_rejected' => 0
                ]
            ]
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/purchase-receipts/{$receipt->id}", $updateData);

        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase receipt updated successfully'
            ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'notes' => 'Updated notes'
        ]);

        // Verify the receipt was updated successfully (already done above)
        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function it_cannot_update_approved_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'approved'
        ]);

        $updateData = [
            'purchase_order_id' => $this->purchaseOrder->id,
            'receipt_date' => now()->toDateString(),
            'items' => []
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/purchase-receipts/{$receipt->id}", $updateData);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Purchase receipt cannot be edited in current status'
            ]);
    }

    /** @test */
    public function it_can_approve_purchase_receipt_and_update_stock()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'complete'
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $this->purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity_received' => 30,
            'quantity_accepted' => 30,
            'quality_status' => 'passed'
        ]);

        $initialStock = $this->product->stock->current_stock;

        Sanctum::actingAs($this->approverUser);

        $response = $this->postJson("/api/purchase-receipts/{$receipt->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase receipt approved and stock updated successfully'
            ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'status' => 'approved',
            'stock_updated' => true
        ]);

        // Check stock was updated
        $updatedStock = $this->product->stock->fresh()->current_stock;
        $this->assertEquals($initialStock + 30, $updatedStock);

        // Check stock movement was created
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'movement_type' => 'in',
            'quantity' => 30,
            'reference_type' => 'purchase_receipt'
        ]);
    }

    /** @test */
    public function it_can_manually_update_stock_for_approved_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'approved',
            'stock_updated' => false
        ]);

        PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $this->purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'quantity_accepted' => 25
        ]);

        $initialStock = $this->product->stock->current_stock;

        Sanctum::actingAs($this->approverUser);

        $response = $this->postJson("/api/purchase-receipts/{$receipt->id}/update-stock");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Stock updated successfully'
            ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'stock_updated' => true
        ]);

        $updatedStock = $this->product->stock->fresh()->current_stock;
        $this->assertEquals($initialStock + 25, $updatedStock);
    }

    /** @test */
    public function user_without_approval_permission_cannot_approve_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'complete'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/purchase-receipts/{$receipt->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_delete_draft_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'draft'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/purchase-receipts/{$receipt->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Purchase receipt deleted successfully'
            ]);

        $this->assertDatabaseMissing('purchase_receipts', [
            'id' => $receipt->id
        ]);
    }

    /** @test */
    public function it_cannot_delete_approved_purchase_receipt()
    {
        $receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id,
            'status' => 'approved'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/purchase-receipts/{$receipt->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Purchase receipt cannot be deleted in current status'
            ]);
    }

    /** @test */
    public function it_can_get_purchase_receipt_analytics()
    {
        PurchaseReceipt::factory()->count(5)->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/purchase-receipts/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_receipts',
                    'draft_receipts',
                    'pending_quality_check',
                    'completed_receipts',
                    'approved_receipts',
                    'stock_updated_receipts'
                ]
            ]);
    }

    /** @test */
    public function viewer_user_can_view_purchase_receipts()
    {
        PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_by' => $this->user->id
        ]);

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/purchase-receipts');

        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_purchase_receipt()
    {
        $receiptData = [
            'purchase_order_id' => $this->purchaseOrder->id,
            'received_date' => now()->toDateString(),
            'items' => []
        ];

        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/purchase-receipts', $receiptData);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_purchase_receipts()
    {
        $response = $this->getJson('/api/purchase-receipts');

        $response->assertStatus(401);
    }
}
