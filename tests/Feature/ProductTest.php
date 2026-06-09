<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $manager;
    private int    $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles & permissions
        Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view products',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create products', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'edit products',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete products', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name'   => 'Test Co',
            'slug'   => 'test-co',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->assignRole('business_owner');
        $this->manager->givePermissionTo([
            'view products', 'create products', 'edit products', 'delete products',
        ]);

        $this->categoryId = DB::table('product_categories')->insertGetId([
            'name'       => 'Electronics',
            'tenant_id'  => $this->tenant->id,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Test 1: SKU auto-generation ────────────────────────────────────────────

    public function test_sku_auto_generated_when_blank(): void
    {
        $this->actingAs($this->manager)
            ->post(route('products.store'), [
                'name'          => 'Auto SKU Widget',
                'category_id'   => $this->categoryId,
                'cost_price'    => 100,
                'selling_price' => 150,
            ]);

        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('name', 'Auto SKU Widget')
            ->first();

        $this->assertNotNull($product, 'Product was not created');
        $this->assertMatchesRegularExpression('/^SKU-[A-Z]{3}-[A-Z0-9]{6}$/', $product->sku);
        $this->assertEquals($this->tenant->id, $product->tenant_id);
    }

    // ── Test 2: Margin accessor ────────────────────────────────────────────────

    public function test_margin_calculated_correctly(): void
    {
        DB::table('products')->insert([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-MARGIN-001',
            'name'          => 'Margin Widget',
            'cost_price'    => 80.00,
            'selling_price' => 100.00,
            'minimum_stock' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $product = Product::where('sku', 'SKU-MARGIN-001')->first();

        $this->assertNotNull($product);
        $this->assertEquals(20.0, $product->margin_percent); // (100-80)/100 * 100 = 20 %
    }

    // ── Test 3: Plan product limit ─────────────────────────────────────────────

    public function test_plan_product_limit_enforced(): void
    {
        // starter = 500; bulk-insert 500 products for this tenant
        $rows = [];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = [
                'tenant_id'     => $this->tenant->id,
                'sku'           => "SKU-LIMIT-{$i}",
                'name'          => "Product {$i}",
                'cost_price'    => 10,
                'selling_price' => 20,
                'minimum_stock' => 0,
                'is_active'     => true,
                'track_expiry'  => false,
                'track_batch'   => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }
        DB::table('products')->insert($rows);

        $response = $this->actingAs($this->manager)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('products.store'), [
                'name'          => 'One Too Many',
                'category_id'   => $this->categoryId,
                'cost_price'    => 10,
                'selling_price' => 20,
            ]);

        $response->assertStatus(402);
        $response->assertJsonFragment(['limit' => 500]);
    }

    // ── Test 4: Instant search ─────────────────────────────────────────────────

    public function test_search_returns_by_sku_barcode_name(): void
    {
        DB::table('products')->insert([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-SEARCH-X1',
            'barcode'       => 'BAR-9999',
            'name'          => 'Findable Widget',
            'cost_price'    => 10,
            'selling_price' => 20,
            'minimum_stock' => 0,
            'status'        => 'active',
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // by SKU partial
        $this->actingAs($this->manager)
            ->getJson(route('products.search', ['q' => 'SEARCH-X1']))
            ->assertJsonCount(1)
            ->assertJsonFragment(['sku' => 'SKU-SEARCH-X1']);

        // by name
        $this->actingAs($this->manager)
            ->getJson(route('products.search', ['q' => 'Findable']))
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Findable Widget']);

        // by exact barcode
        $this->actingAs($this->manager)
            ->getJson(route('products.search', ['q' => 'BAR-9999']))
            ->assertJsonCount(1);
    }

    // ── Test 5: Bulk CSV import — creates new, updates existing ───────────────

    public function test_bulk_import_creates_and_updates_products(): void
    {
        DB::table('products')->insert([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-EXIST',
            'name'          => 'Old Name',
            'cost_price'    => 10,
            'selling_price' => 20,
            'minimum_stock' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $csv = "name,sku,barcode,cost_price,selling_price,category_name\n"
             . "Brand New Product,SKU-BRAND-NEW,,15,30,Electronics\n"
             . "Updated Name,SKU-EXIST,,10,20,Electronics\n";

        $file = $this->fakeCsv('products.csv', $csv);

        $response = $this->actingAs($this->manager)
            ->post(route('products.import'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku'       => 'SKU-BRAND-NEW',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('products', [
            'sku'  => 'SKU-EXIST',
            'name' => 'Updated Name',
        ]);
    }

    // ── Test 6: Import reports per-row errors ──────────────────────────────────

    public function test_import_returns_row_errors(): void
    {
        $csv = "name,sku,barcode,cost_price,selling_price,category_name\n"
             . ",BAD-ROW,,not-a-number,20,Electronics\n"; // missing name + bad cost

        $file = $this->fakeCsv('bad.csv', $csv);

        $response = $this->actingAs($this->manager)
            ->post(route('products.import'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('import_errors');
    }

    // ── Test 7: Tenant isolation ───────────────────────────────────────────────

    public function test_products_are_tenant_scoped(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active',
        ]);

        DB::table('products')->insert([
            ['tenant_id' => $tenantB->id,      'sku' => 'SKU-B-001', 'name' => 'Tenant B Exclusive',
             'cost_price' => 10, 'selling_price' => 20, 'minimum_stock' => 0,
             'is_active' => true, 'track_expiry' => false, 'track_batch' => false,
             'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->tenant->id, 'sku' => 'SKU-A-001', 'name' => 'Tenant A Exclusive',
             'cost_price' => 10, 'selling_price' => 20, 'minimum_stock' => 0,
             'is_active' => true, 'track_expiry' => false, 'track_batch' => false,
             'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs($this->manager)->get(route('products.index'));

        $response->assertOk();
        $response->assertViewHas('products', function ($products) {
            foreach ($products as $product) {
                if ($product->sku === 'SKU-B-001') return false;
            }
            return $products->contains('sku', 'SKU-A-001');
        });
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function fakeCsv(string $name, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_test');
        file_put_contents($tmp, $content);
        return new UploadedFile($tmp, $name, 'text/csv', null, true);
    }
}
