<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view purchasing']);
        Permission::create(['name' => 'manage purchasing']);
        Permission::create(['name' => 'manage inventory']);

        // Create roles
        $managerRole = Role::create(['name' => 'manager']);
        $managerRole->givePermissionTo(['view purchasing', 'manage purchasing']);

        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('view purchasing');

        // Create users
        $this->user = User::factory()->create();
        $this->user->assignRole('manager');

        $this->viewerUser = User::factory()->create();
        $this->viewerUser->assignRole('viewer');
    }

    /** @test */
    public function it_can_list_suppliers()
    {
        Supplier::factory()->count(3)->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/suppliers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'contact_person',
                        'email',
                        'phone',
                        'address',
                        'city',
                        'country',
                        'tax_number',
                        'payment_terms',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'pagination'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_filter_suppliers_by_status()
    {
        Supplier::factory()->create(['status' => 'active']);
        Supplier::factory()->create(['status' => 'inactive']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/suppliers?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_search_suppliers()
    {
        Supplier::factory()->create(['name' => 'Tech Solutions Inc']);
        Supplier::factory()->create(['name' => 'Global Supplies']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/suppliers?search=Tech');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('Tech', $response->json('data.0.name'));
    }

    /** @test */
    public function it_can_create_a_supplier()
    {
        $supplierData = [
            'name' => 'Test Supplier Ltd',
            'code' => 'SUP001',
            'contact_person' => 'John Doe',
            'email' => 'john@testsupplier.com',
            'phone' => '+1234567890',
            'address' => '123 Test Street, Test City',
            'tax_number' => 'TAX123456789',
            'payment_terms' => 30,
            'status' => 'active'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suppliers', $supplierData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Supplier created successfully',
                'data' => [
                    'name' => 'Test Supplier Ltd',
                    'contact_person' => 'John Doe',
                    'email' => 'john@testsupplier.com',
                    'payment_terms' => 30,
                    'status' => 'active'
                ]
            ]);

        $this->assertDatabaseHas('suppliers', $supplierData);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_supplier()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suppliers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'payment_terms', 'status']);
    }

    /** @test */
    public function it_validates_email_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'email' => 'invalid-email',
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** @test */
    public function it_validates_unique_email()
    {
        Supplier::factory()->create(['email' => 'test@example.com']);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/suppliers', [
            'name' => 'Another Supplier',
            'email' => 'test@example.com',
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /** @test */
    public function it_validates_payment_terms_field()
    {
        Sanctum::actingAs($this->user);

        // Test missing payment_terms
        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP002',
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('payment_terms');

        // Test invalid payment_terms (negative)
        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP003',
            'payment_terms' => -1,
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('payment_terms');

        // Test invalid payment_terms (too large)
        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP004',
            'payment_terms' => 400,
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('payment_terms');

        // Test valid payment_terms
        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'code' => 'SUP005',
            'payment_terms' => 30,
            'status' => 'active'
        ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function it_can_show_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'contact_person' => $supplier->contact_person,
                    'status' => $supplier->status
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        $updateData = [
            'name' => 'Updated Supplier Name',
            'code' => 'SUP999',
            'contact_person' => 'Jane Smith',
            'payment_terms' => 45,
            'status' => 'inactive'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/suppliers/{$supplier->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Supplier updated successfully',
                'data' => [
                    'name' => 'Updated Supplier Name',
                    'contact_person' => 'Jane Smith',
                    'payment_terms' => 45,
                    'status' => 'inactive'
                ]
            ]);

        $this->assertDatabaseHas('suppliers', array_merge(['id' => $supplier->id], $updateData));
    }

    /** @test */
    public function it_can_delete_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Supplier deleted successfully'
            ]);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    /** @test */
    public function it_can_get_supplier_performance_metrics()
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/suppliers/{$supplier->id}/performance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'supplier' => [
                        'id',
                        'name',
                        'status'
                    ],
                    'metrics' => [
                        'total_orders',
                        'total_value',
                        'on_time_delivery_rate',
                        'quality_rating',
                        'last_order_date'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_get_suppliers_with_active_status_only()
    {
        Supplier::factory()->create(['status' => 'active']);
        Supplier::factory()->create(['status' => 'active']);
        Supplier::factory()->create(['status' => 'inactive']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/suppliers/active');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $supplier) {
            $this->assertEquals('active', $supplier['status']);
        }
    }

    /** @test */
    public function viewer_user_can_view_suppliers()
    {
        Supplier::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/suppliers');
        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_supplier()
    {
        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/suppliers', [
            'name' => 'Test Supplier',
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_update_supplier()
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->putJson("/api/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier',
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_delete_supplier()
    {
        $supplier = Supplier::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_suppliers()
    {
        $response = $this->getJson('/api/suppliers');

        $response->assertStatus(401);
    }
}
