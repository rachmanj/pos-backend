<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view inventory']);
        Permission::create(['name' => 'manage inventory']);

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
    }

    /** @test */
    public function it_can_list_units()
    {
        Unit::factory()->count(5)->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/units');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'symbol',
                        'base_unit_id',
                        'conversion_factor',
                        'is_base_unit',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta'
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    /** @test */
    public function it_can_create_a_base_unit()
    {
        $unitData = [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'base_unit_id' => null,
            'conversion_factor' => 1.0
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units', $unitData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Unit created successfully',
                'data' => [
                    'name' => 'Kilogram',
                    'symbol' => 'kg',
                    'is_base_unit' => true
                ]
            ]);

        $this->assertDatabaseHas('units', [
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'base_unit_id' => null,
            'conversion_factor' => 1.0
        ]);
    }

    /** @test */
    public function it_can_create_a_derived_unit()
    {
        $baseUnit = Unit::factory()->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'base_unit_id' => null,
            'conversion_factor' => 1.0
        ]);

        $unitData = [
            'name' => 'Gram',
            'symbol' => 'g',
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => 0.001
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units', $unitData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Unit created successfully',
                'data' => [
                    'name' => 'Gram',
                    'symbol' => 'g',
                    'is_base_unit' => false,
                    'base_unit_id' => $baseUnit->id
                ]
            ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_unit()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'symbol']);
    }

    /** @test */
    public function it_prevents_duplicate_unit_names()
    {
        Unit::factory()->create(['name' => 'Kilogram']);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units', [
            'name' => 'Kilogram',
            'symbol' => 'kg2'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** @test */
    public function it_prevents_duplicate_unit_symbols()
    {
        Unit::factory()->create(['symbol' => 'kg']);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units', [
            'name' => 'Kilogram 2',
            'symbol' => 'kg'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('symbol');
    }

    /** @test */
    public function it_can_show_a_unit()
    {
        $unit = Unit::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'symbol' => $unit->symbol
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_unit()
    {
        $unit = Unit::factory()->create();

        $updateData = [
            'name' => 'Updated Unit',
            'symbol' => 'upd'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/units/{$unit->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Unit updated successfully',
                'data' => [
                    'name' => 'Updated Unit',
                    'symbol' => 'upd'
                ]
            ]);

        $this->assertDatabaseHas('units', array_merge(['id' => $unit->id], $updateData));
    }

    /** @test */
    public function it_can_delete_a_unit_without_products()
    {
        $unit = Unit::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/units/{$unit->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Unit deleted successfully'
            ]);

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    /** @test */
    public function it_can_get_conversion_chart()
    {
        $baseUnit = Unit::factory()->create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'base_unit_id' => null,
            'conversion_factor' => 1.0
        ]);

        Unit::factory()->create([
            'name' => 'Gram',
            'symbol' => 'g',
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => 0.001
        ]);

        Unit::factory()->create([
            'name' => 'Pound',
            'symbol' => 'lb',
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => 0.453592
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/units/conversions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'base_unit' => [
                            'id',
                            'name',
                            'symbol'
                        ],
                        'derived_units' => [
                            '*' => [
                                'id',
                                'name',
                                'symbol',
                                'conversion_factor'
                            ]
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_convert_between_units()
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

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/units/convert', [
            'from_unit_id' => $baseUnit->id,
            'to_unit_id' => $gramUnit->id,
            'quantity' => 2.5
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'original_quantity' => 2.5,
                'converted_quantity' => 2500.0,
                'from_unit' => 'kg',
                'to_unit' => 'g'
            ]);
    }

    /** @test */
    public function viewer_user_can_view_units()
    {
        Unit::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->getJson('/api/units');
        $response->assertStatus(200);
    }

    /** @test */
    public function viewer_user_cannot_create_unit()
    {
        Sanctum::actingAs($this->viewerUser);

        $response = $this->postJson('/api/units', [
            'name' => 'Test Unit',
            'symbol' => 'test'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_update_unit()
    {
        $unit = Unit::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->putJson("/api/units/{$unit->id}", [
            'name' => 'Updated Unit',
            'symbol' => 'upd'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function viewer_user_cannot_delete_unit()
    {
        $unit = Unit::factory()->create();

        Sanctum::actingAs($this->viewerUser);

        $response = $this->deleteJson("/api/units/{$unit->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_units()
    {
        $response = $this->getJson('/api/units');

        $response->assertStatus(401);
    }
}
