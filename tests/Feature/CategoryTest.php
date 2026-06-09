<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view categories',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create categories', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit categories',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete categories', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name' => 'Test Co', 'slug' => 'test-co', 'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->assignRole('business_owner');
        $this->manager->givePermissionTo([
            'view categories', 'create categories', 'edit categories', 'delete categories',
        ]);
    }

    // ── Test 1 ────────────────────────────────────────────────────────────────

    public function test_can_create_root_category(): void
    {
        $response = $this->actingAs($this->manager)
            ->post(route('categories.store'), [
                'name'        => 'Electronics',
                'description' => 'All electronics',
            ]);

        $response->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('product_categories', [
            'name'      => 'Electronics',
            'parent_id' => null,
            'tenant_id' => $this->tenant->id,
        ]);

        $cat = Category::where('name', 'Electronics')->first();
        $this->assertNotNull($cat);
        $this->assertEquals(0, $cat->depth);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $cat->slug);
    }

    // ── Test 2 ────────────────────────────────────────────────────────────────

    public function test_can_create_child_and_grandchild(): void
    {
        $this->actingAs($this->manager);

        // Root (depth 0)
        $this->post(route('categories.store'), ['name' => 'Root'])->assertRedirect();
        $root = Category::where('name', 'Root')->first();
        $this->assertEquals(0, $root->depth);

        // Child (depth 1)
        $this->post(route('categories.store'), ['name' => 'Child', 'parent_id' => $root->id])->assertRedirect();
        $child = Category::where('name', 'Child')->first();
        $this->assertEquals(1, $child->depth);
        $this->assertEquals($root->id, $child->parent_id);

        // Grandchild (depth 2)
        $this->post(route('categories.store'), ['name' => 'Grandchild', 'parent_id' => $child->id])->assertRedirect();
        $grandchild = Category::where('name', 'Grandchild')->first();
        $this->assertNotNull($grandchild);
        $this->assertEquals(2, $grandchild->depth);
        $this->assertEquals($child->id, $grandchild->parent_id);
    }

    // ── Test 3 ────────────────────────────────────────────────────────────────

    public function test_cannot_create_4th_level(): void
    {
        // Create 3 levels via HTTP so tenant_id is auto-set from the auth user
        $this->actingAs($this->manager);

        $this->post(route('categories.store'), ['name' => 'Root L1'])->assertRedirect();
        $root = Category::where('name', 'Root L1')->first();

        $this->post(route('categories.store'), ['name' => 'Child L2', 'parent_id' => $root->id])->assertRedirect();
        $child = Category::where('name', 'Child L2')->first();

        $this->post(route('categories.store'), ['name' => 'Grand L3', 'parent_id' => $child->id])->assertRedirect();
        $grandchild = Category::where('name', 'Grand L3')->first();

        // Attempt 4th level — must be rejected
        $response = $this->post(route('categories.store'), [
            'name'      => 'Too Deep L4',
            'parent_id' => $grandchild->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('product_categories', ['name' => 'Too Deep L4']);
    }

    // ── Test 4 ────────────────────────────────────────────────────────────────

    public function test_cannot_delete_category_with_products(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('categories.store'), ['name' => 'Has Products'])->assertRedirect();
        $category = Category::where('name', 'Has Products')->first();

        DB::table('products')->insert([
            'tenant_id'     => $this->tenant->id,
            'category_id'   => $category->id,
            'sku'           => 'SKU-CAT-BLOCK',
            'name'          => 'Blocked Product',
            'cost_price'    => 10,
            'selling_price' => 20,
            'minimum_stock' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $response = $this->delete(route('categories.destroy', $category));

        $response->assertRedirect();
        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    // ── Test 5 ────────────────────────────────────────────────────────────────

    public function test_stock_value_aggregates_through_hierarchy(): void
    {
        $this->actingAs($this->manager);

        // Root → Child → Grandchild (3 levels)
        $this->post(route('categories.store'), ['name' => 'Root Val'])->assertRedirect();
        $root = Category::where('name', 'Root Val')->first();

        $this->post(route('categories.store'), ['name' => 'Child Val', 'parent_id' => $root->id])->assertRedirect();
        $child = Category::where('name', 'Child Val')->first();

        $this->post(route('categories.store'), ['name' => 'Grand Val', 'parent_id' => $child->id])->assertRedirect();
        $grandchild = Category::where('name', 'Grand Val')->first();

        // Need a warehouse for stock_balances FK
        $warehouseId = DB::table('warehouses')->insertGetId([
            'name'       => 'Test WH',
            'code'       => 'WH-CATTEST',
            'tenant_id'  => $this->tenant->id,
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Product in child: cost=50, qty=4 → value=200
        $pChild = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'category_id'   => $child->id,
            'sku'           => 'SKU-CHILD-STOCK',
            'name'          => 'Child Product',
            'cost_price'    => 50,
            'selling_price' => 80,
            'minimum_stock' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('stock_balances')->insert([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $pChild,
            'warehouse_id'       => $warehouseId,
            'quantity_available' => 4,
            'quantity_reserved'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Product in grandchild: cost=30, qty=10 → value=300
        $pGrand = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'category_id'   => $grandchild->id,
            'sku'           => 'SKU-GRAND-STOCK',
            'name'          => 'Grand Product',
            'cost_price'    => 30,
            'selling_price' => 50,
            'minimum_stock' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('stock_balances')->insert([
            'tenant_id'          => $this->tenant->id,
            'product_id'         => $pGrand,
            'warehouse_id'       => $warehouseId,
            'quantity_available' => 10,
            'quantity_reserved'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Expected: root covers child (200) + grandchild (300) = 500
        $this->assertEquals(500.0, $root->stock_value);
        $this->assertEquals(500.0, $child->stock_value);  // child (200) + grandchild (300)
        $this->assertEquals(300.0, $grandchild->stock_value);
    }

    // ── Test 6 ────────────────────────────────────────────────────────────────

    public function test_categories_are_tenant_scoped(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);

        DB::table('product_categories')->insert([
            'name'       => 'Tenant B Category',
            'tenant_id'  => $tenantB->id,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_categories')->insert([
            'name'       => 'Tenant A Category',
            'tenant_id'  => $this->tenant->id,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('categories.index'));

        $response->assertOk();
        $response->assertViewHas('categories', function ($categories) {
            foreach ($categories as $cat) {
                if ($cat->name === 'Tenant B Category') return false;
            }
            return $categories->contains('name', 'Tenant A Category');
        });
    }
}
