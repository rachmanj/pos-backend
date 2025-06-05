<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $unauthorizedUser;

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

        // Create authorized user
        $this->user = User::factory()->create();
        $this->user->assignRole('manager');

        // Create unauthorized user
        $this->unauthorizedUser = User::factory()->create();
        $this->unauthorizedUser->assignRole('viewer');
    }

    /** @test */
    public function it_can_list_categories()
    {
        // Create test categories
        $parentCategory = Category::factory()->create([
            'name' => 'Electronics',
            'status' => 'active'
        ]);

        $childCategory = Category::factory()->create([
            'name' => 'Smartphones',
            'parent_id' => $parentCategory->id,
            'status' => 'active'
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'parent_id',
                        'status',
                        'full_name',
                        'is_root',
                        'has_children',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total'
                ]
            ]);

        $this->assertSame(2, count($response->json('data')));
    }

    /** @test */
    public function it_can_filter_categories_by_status()
    {
        Category::factory()->create(['status' => 'active']);
        Category::factory()->create(['status' => 'inactive']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/categories?status=active');

        $response->assertStatus(200);
        $this->assertSame(1, count($response->json('data')));
        $this->assertSame('active', $response->json('data.0.status'));
    }

    /** @test */
    public function it_can_filter_categories_by_parent_id()
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        Category::factory()->create(); // Root category

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/categories?parent_id={$parent->id}");

        $response->assertStatus(200);
        $this->assertSame(1, count($response->json('data')));
        $this->assertSame($parent->id, $response->json('data.0.parent_id'));
    }

    /** @test */
    public function it_can_search_categories()
    {
        Category::factory()->create(['name' => 'Electronics']);
        Category::factory()->create(['name' => 'Clothing']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/categories?search=Elect');

        $response->assertStatus(200);
        $this->assertSame(1, count($response->json('data')));
        $this->assertTrue(str_contains($response->json('data.0.name'), 'Electronics'));
    }

    /** @test */
    public function it_can_create_a_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'This is a test category',
            'status' => 'active'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Category created successfully',
                'data' => [
                    'name' => 'Test Category',
                    'description' => 'This is a test category',
                    'status' => 'active'
                ]
            ]);

        $this->assertDatabaseHas('categories', $categoryData);
    }

    /** @test */
    public function it_can_create_a_child_category()
    {
        $parent = Category::factory()->create();

        $categoryData = [
            'name' => 'Child Category',
            'parent_id' => $parent->id,
            'status' => 'active'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/categories', $categoryData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', $categoryData);
        $this->assertSame($parent->id, $response->json('data.parent_id'));
    }

    /** @test */
    public function it_validates_required_fields_when_creating_category()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'status']);
    }

    /** @test */
    public function it_prevents_circular_reference_when_creating_category()
    {
        $category = Category::factory()->create();

        $categoryData = [
            'name' => 'Self Parent',
            'parent_id' => $category->id,
            'status' => 'active'
        ];

        Sanctum::actingAs($this->user);

        // First create a child
        $child = $this->postJson('/api/categories', $categoryData)->json('data');

        // Now try to make the parent a child of its own child
        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => $category->name,
            'parent_id' => $child['id'],
            'status' => 'active'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    /** @test */
    public function it_can_show_a_category()
    {
        $category = Category::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'status' => $category->status
                ]
            ]);
    }

    /** @test */
    public function it_can_update_a_category()
    {
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Updated Category',
            'description' => 'Updated description',
            'status' => 'inactive'
        ];

        Sanctum::actingAs($this->user);

        $response = $this->putJson("/api/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category updated successfully',
                'data' => [
                    'name' => 'Updated Category',
                    'description' => 'Updated description',
                    'status' => 'inactive'
                ]
            ]);

        $this->assertDatabaseHas('categories', array_merge(['id' => $category->id], $updateData));
    }

    /** @test */
    public function it_can_delete_a_category_without_children_or_products()
    {
        $category = Category::factory()->create();

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully'
            ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /** @test */
    public function it_cannot_delete_category_with_children()
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/categories/{$parent->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete category with subcategories. Please delete or move subcategories first.'
            ]);

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    /** @test */
    public function it_can_get_category_children()
    {
        $parent = Category::factory()->create();
        $child1 = Category::factory()->create(['parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['parent_id' => $parent->id]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/categories/{$parent->id}/children");

        $response->assertStatus(200);
        $this->assertSame(2, count($response->json('data')));

        $childIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertTrue(in_array($child1->id, $childIds));
        $this->assertTrue(in_array($child2->id, $childIds));
    }

    /** @test */
    public function it_can_get_category_tree()
    {
        // Create hierarchical structure
        $electronics = Category::factory()->create(['name' => 'Electronics']);
        $smartphones = Category::factory()->create([
            'name' => 'Smartphones',
            'parent_id' => $electronics->id
        ]);
        $clothing = Category::factory()->create(['name' => 'Clothing']);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/categories/tree');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertSame(2, count($data)); // Should have 2 root categories

        // Find electronics category in response
        $electronicsData = collect($data)->firstWhere('name', 'Electronics');
        $this->assertNotNull($electronicsData);
        $this->assertSame(1, count($electronicsData['children'])); // Should have 1 child
        $this->assertSame('Smartphones', $electronicsData['children'][0]['name']);
    }

    /** @test */
    public function unauthorized_user_cannot_create_category()
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthorized_user_cannot_update_category()
    {
        $category = Category::factory()->create();

        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->putJson("/api/categories/{$category->id}", [
            'name' => 'Updated Category',
            'status' => 'active'
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthorized_user_cannot_delete_category()
    {
        $category = Category::factory()->create();

        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthorized_user_can_view_categories()
    {
        Category::factory()->create();

        Sanctum::actingAs($this->unauthorizedUser);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_categories()
    {
        $response = $this->getJson('/api/categories');

        $response->assertStatus(401);
    }
}
