<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\LoyaltyTransaction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private int $warehouseId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['sales.view', 'sales.create', 'sales.manage'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Customer Test Co',
            'slug'   => 'customer-test',
            'status' => 'active',
            'config' => ['plan' => 'professional', 'loyalty_earn_rate' => 1, 'loyalty_redeem_rate' => 1],
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->user->givePermissionTo(['sales.view', 'sales.create', 'sales.manage']);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Customer Test WH',
            'code'       => 'WH-CT-01',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'CT-SKU-001',
            'name'          => 'Test Product',
            'cost_price'    => 3000,
            'selling_price' => 5000,
            'minimum_stock' => 0,
            'reorder_level' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Open an active POS session for the user so sale tests can proceed
        DB::table('pos_sessions')->insert([
            'tenant_id'    => $this->tenant->id,
            'cashier_id'   => $this->user->id,
            'branch_id'    => null,
            'opening_cash' => 50000,
            'status'       => 'active',
            'opened_at'    => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function seedStock(int $qty): void
    {
        DB::table('inventory')->insertOrIgnore([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $this->productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => $qty,
            'unit_cost'        => 3000,
            'valuation_method' => 'weighted_avg',
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // ── Test 1: Loyalty points earned after completed sale ────────────────────

    public function test_loyalty_points_earned_on_sale(): void
    {
        $this->seedStock(20);
        $this->actingAs($this->user);

        $customer = Customer::create([
            'name'  => 'Loyal Customer',
            'phone' => '0712345678',
        ]);

        $this->assertEquals(0, $customer->loyalty_points);

        // grand_total = 1 × 5000 TZS → floor(5000 / 1000 × 1) = 5 points
        $response = $this->postJson(route('pos.sales.store'), [
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'cash',
            'customer_id'    => $customer->id,
            'items'          => [[
                'product_id' => $this->productId,
                'qty'        => 1,
                'unit_price' => 5000,
                'cost_price' => 3000,
            ]],
        ]);

        $response->assertStatus(201);

        $customer->refresh();
        $this->assertEquals(5, $customer->loyalty_points, 'Customer should have earned 5 loyalty points.');

        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id'  => $customer->id,
            'type'         => LoyaltyTransaction::TYPE_EARN,
            'points'       => 5,
            'balance_after' => 5,
        ]);
    }

    // ── Test 2: Cannot redeem more points than current balance ────────────────

    public function test_cannot_redeem_more_points_than_balance(): void
    {
        $this->actingAs($this->user);

        $customer = Customer::create([
            'name'           => 'Low Points Customer',
            'phone'          => '0712345679',
            'loyalty_points' => 100,
        ]);

        $response = $this->postJson(route('pos.loyalty.redeem'), [
            'customer_id'      => $customer->id,
            'points_to_redeem' => 200,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Insufficient loyalty points', $response->json('message'));

        // Points unchanged
        $customer->refresh();
        $this->assertEquals(100, $customer->loyalty_points);
        $this->assertDatabaseEmpty('loyalty_transactions');
    }

    // ── Test 3: Credit limit blocks a sale that would exceed the limit ─────────

    public function test_credit_limit_blocks_over_limit_sale(): void
    {
        $this->seedStock(10);
        $this->actingAs($this->user);

        // credit_limit=1000, balance=900 → only 100 TZS available credit
        // sale grand_total = 2 × 100 = 200 → 900 + 200 = 1100 > 1000 → blocked
        $customer = Customer::create([
            'name'         => 'Credit Customer',
            'phone'        => '0712345680',
            'credit_limit' => 1000,
            'balance'      => 900,
        ]);

        $response = $this->postJson(route('pos.sales.store'), [
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'credit',
            'customer_id'    => $customer->id,
            'items'          => [[
                'product_id' => $this->productId,
                'qty'        => 2,
                'unit_price' => 100,
                'cost_price' => 80,
            ]],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Credit limit exceeded', $response->json('message'));

        // No sale created
        $this->assertDatabaseEmpty('sales');

        // Inventory unchanged
        $qty = (float) DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');
        $this->assertEqualsWithDelta(10.0, $qty, 0.001);
    }

    // ── Test 4: Customer history shows only the correct customer's sales ───────

    public function test_customer_history_shows_correct_sales(): void
    {
        $this->seedStock(30);
        $this->actingAs($this->user);

        $customer1 = Customer::create(['name' => 'Customer Alpha', 'phone' => '0712000001']);
        $customer2 = Customer::create(['name' => 'Customer Beta',  'phone' => '0712000002']);

        // 2 sales for customer1
        foreach ([100, 200] as $price) {
            $this->postJson(route('pos.sales.store'), [
                'warehouse_id'   => $this->warehouseId,
                'payment_method' => 'cash',
                'customer_id'    => $customer1->id,
                'items'          => [['product_id' => $this->productId, 'qty' => 1, 'unit_price' => $price]],
            ])->assertStatus(201);
        }

        // 1 sale for customer2
        $this->postJson(route('pos.sales.store'), [
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'cash',
            'customer_id'    => $customer2->id,
            'items'          => [['product_id' => $this->productId, 'qty' => 1, 'unit_price' => 150]],
        ])->assertStatus(201);

        $response = $this->getJson(route('customers.history', $customer1));
        $response->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(2, $items, 'History must show exactly 2 sales for customer1.');
    }

    // ── Test 5: Customers are scoped to the authenticated user's tenant ────────

    public function test_customers_are_tenant_scoped(): void
    {
        $tenant2 = Tenant::create([
            'name'   => 'Another Tenant',
            'slug'   => 'another-tenant',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        // Insert customer for tenant2 directly (bypasses TenantScope)
        DB::table('customers')->insert([
            'tenant_id'      => $tenant2->id,
            'name'           => 'Tenant2 Customer',
            'phone'          => '0799999999',
            'code'           => 'CUS-T2-001',
            'type'           => 'retail',
            'credit_limit'   => 0,
            'balance'        => 0,
            'loyalty_points' => 0,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->actingAs($this->user);

        // Create a customer for tenant1 (TenantModel auto-sets tenant_id)
        Customer::create(['name' => 'Tenant1 Customer', 'phone' => '0711111111']);

        // GET /customers as tenant1 user — must only return tenant1's customer
        $response = $this->getJson(route('customers.index'));
        $response->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items, 'TenantScope must hide tenant2 customer.');
        $this->assertEquals('Tenant1 Customer', $items[0]['name']);
    }
}
