<?php

namespace Tests\Feature;

use App\Events\SaleCompleted;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class POSTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private User $manager;
    private int $warehouseId;
    private int $productId;
    private int $product2Id;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['sales.process'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'POS Test Co',
            'slug'   => 'pos-test',
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);

        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->cashier->givePermissionTo(['sales.process']);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo(['sales.process']);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'POS Warehouse',
            'code'       => 'WH-POS-01',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'POS-SKU-001',
            'name'          => 'POS Product 1',
            'cost_price'    => 80,
            'selling_price' => 100,
            'minimum_stock' => 0,
            'reorder_level' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->product2Id = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'POS-SKU-002',
            'name'          => 'POS Product 2',
            'cost_price'    => 50,
            'selling_price' => 75,
            'minimum_stock' => 0,
            'reorder_level' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Open a POS session for the cashier so all sale tests can proceed
        $this->openSession($this->cashier);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function openSession(User $cashier): void
    {
        DB::table('pos_sessions')->insert([
            'tenant_id'    => $this->tenant->id,
            'cashier_id'   => $cashier->id,
            'branch_id'    => null,
            'opening_cash' => 50000,
            'status'       => 'active',
            'opened_at'    => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function seedStock(int $productId, int $qty): void
    {
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => $qty,
            'unit_cost'        => 80,
            'valuation_method' => 'weighted_avg',
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'cash',
            'items'          => [[
                'product_id' => $this->productId,
                'qty'        => 2,
                'unit_price' => 100,
                'cost_price' => 80,
            ]],
        ], $overrides);
    }

    // ── Test 1: Atomic sale ───────────────────────────────────────────────────

    public function test_sale_is_fully_atomic(): void
    {
        Event::fake([SaleCompleted::class]);
        $this->seedStock($this->productId, 10);

        $this->actingAs($this->cashier);

        $response = $this->postJson(route('pos.sales.store'), $this->salePayload());

        $response->assertStatus(201);

        // Sale record
        $this->assertDatabaseHas('sales', [
            'cashier_id'     => $this->cashier->id,
            'payment_method' => 'cash',
            'status'         => Sale::STATUS_COMPLETED,
        ]);

        $sale = Sale::first();

        // SaleItem records
        $this->assertDatabaseHas('sale_items', [
            'sale_id'    => $sale->id,
            'product_id' => $this->productId,
            'qty'        => 2,
        ]);

        // Payment record
        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id'   => $sale->id,
            'method'       => 'cash',
            'status'       => Payment::STATUS_COMPLETED,
        ]);

        // Stock decremented: 10 - 2 = 8
        $inventory = Inventory::where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->first();
        $this->assertEqualsWithDelta(8.0, (float) $inventory->quantity, 0.001);

        Event::assertDispatched(SaleCompleted::class);
    }

    // ── Test 2: Failed payment rolls back inventory ───────────────────────────

    public function test_failed_payment_rolls_back_inventory(): void
    {
        $this->seedStock($this->productId, 5);
        $this->actingAs($this->cashier);

        // Mock MpesaService to throw during STK push — forces rollback of the whole transaction
        $this->mock(MpesaService::class, function ($mock) {
            $mock->shouldReceive('stkPush')->andThrow(new \RuntimeException('Gateway unavailable'));
        });

        $response = $this->postJson(route('pos.sales.store'), $this->salePayload([
            'payment_method' => 'mpesa',
            'mpesa_phone'    => '+255700000001',
        ]));

        // Should fail (500 or 422 depending on exception handling)
        $this->assertTrue($response->status() >= 400);

        // No sale written
        $this->assertDatabaseEmpty('sales');
        $this->assertDatabaseEmpty('sale_items');

        // Stock unchanged at 5
        $inventory = Inventory::where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->first();
        $this->assertEqualsWithDelta(5.0, (float) $inventory->quantity, 0.001);
    }

    // ── Test 3: Out-of-stock blocked at cart ─────────────────────────────────

    public function test_out_of_stock_blocked_at_cart(): void
    {
        // Seed only 1 unit, but request 5
        $this->seedStock($this->productId, 1);
        $this->actingAs($this->cashier);

        $response = $this->postJson(route('pos.sales.store'), $this->salePayload([
            'items' => [[
                'product_id' => $this->productId,
                'qty'        => 5,
                'unit_price' => 100,
                'cost_price' => 80,
            ]],
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseEmpty('sales');

        // Stock still at 1
        $inventory = Inventory::where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->first();
        $this->assertEqualsWithDelta(1.0, (float) $inventory->quantity, 0.001);
    }

    // ── Test 4: M-Pesa sale stays pending until callback ─────────────────────

    public function test_mpesa_sale_stays_pending_until_callback(): void
    {
        Event::fake([SaleCompleted::class]);
        $this->seedStock($this->productId, 10);
        $this->actingAs($this->cashier);

        $checkoutId = 'ws_CO_TEST_' . rand(1000, 9999);

        $this->mock(MpesaService::class, function ($mock) use ($checkoutId) {
            $mock->shouldReceive('stkPush')->andReturn([
                'CheckoutRequestID' => $checkoutId,
                'ResponseCode'      => '0',
                'CustomerMessage'   => 'Success',
            ]);
        });

        $response = $this->postJson(route('pos.sales.store'), $this->salePayload([
            'payment_method' => 'mpesa',
            'mpesa_phone'    => '+255700000002',
        ]));

        $response->assertStatus(201);

        $sale = Sale::first();
        $this->assertEquals(Sale::STATUS_PENDING, $sale->status, 'Sale should be pending awaiting M-Pesa confirmation.');

        $this->assertDatabaseHas('payments', [
            'payable_id' => $sale->id,
            'method'     => 'mpesa',
            'reference'  => $checkoutId,
            'status'     => Payment::STATUS_PENDING,
        ]);

        Event::assertNotDispatched(SaleCompleted::class);

        // Simulate M-Pesa callback
        $callbackResponse = $this->postJson(route('mpesa.callback'), [
            'Body' => ['stkCallback' => ['CheckoutRequestID' => $checkoutId, 'ResultCode' => 0]],
        ]);

        $callbackResponse->assertOk();

        $sale->refresh();
        $this->assertEquals(Sale::STATUS_COMPLETED, $sale->status, 'Sale should be completed after M-Pesa callback.');

        $this->assertDatabaseHas('payments', [
            'reference' => $checkoutId,
            'status'    => Payment::STATUS_COMPLETED,
        ]);

        Event::assertDispatched(SaleCompleted::class);
    }

    // ── Test 5: Split payment amounts must equal grand total ─────────────────

    public function test_split_payment_amounts_must_equal_grand_total(): void
    {
        $this->seedStock($this->productId, 10);
        $this->actingAs($this->cashier);

        // Grand total = 2 × 100 = 200, but split sums to 150 (mismatch)
        $response = $this->postJson(route('pos.sales.store'), $this->salePayload([
            'payment_method' => 'split',
            'payments'       => [
                ['method' => 'cash',  'amount' => 100],
                ['method' => 'mpesa', 'amount' => 50],   // 100+50=150, not 200
            ],
        ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('grand total', $response->json('message'));
        $this->assertDatabaseEmpty('sales');
    }

    // ── Test 6: Sales return restocks inventory ───────────────────────────────

    public function test_sales_return_restocks_inventory(): void
    {
        $this->seedStock($this->productId, 10);
        $this->actingAs($this->cashier);

        // Create a completed sale of 3 units
        $saleResponse = $this->postJson(route('pos.sales.store'), $this->salePayload([
            'items' => [[
                'product_id' => $this->productId,
                'qty'        => 3,
                'unit_price' => 100,
                'cost_price' => 80,
            ]],
        ]));
        $saleResponse->assertStatus(201);

        // Stock should now be 7
        $inventory = Inventory::where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->first();
        $this->assertEqualsWithDelta(7.0, (float) $inventory->quantity, 0.001);

        $sale     = Sale::first();
        $saleItem = SaleItem::where('sale_id', $sale->id)->first();

        // Return 2 units
        $returnResponse = $this->postJson(route('pos.sales.return', $sale), [
            'reason' => 'Customer changed mind',
            'items'  => [['sale_item_id' => $saleItem->id, 'qty' => 2]],
        ]);

        $returnResponse->assertStatus(201);

        // Stock should be back to 9 (7 + 2 returned)
        $inventory->refresh();
        $this->assertEqualsWithDelta(9.0, (float) $inventory->quantity, 0.001);

        $this->assertDatabaseHas('sale_returns', ['sale_id' => $sale->id]);
        $this->assertDatabaseHas('sale_return_items', ['product_id' => $this->productId, 'qty' => 2]);
    }

    // ── Test 7: POS terminal limit enforced ──────────────────────────────────

    public function test_pos_terminal_limit_enforced(): void
    {
        // Starter plan: limit = 1
        $this->tenant->update(['config' => ['plan' => 'starter']]);
        $this->actingAs($this->cashier);

        // Remove the setUp session so we control the exact session count here
        DB::table('pos_sessions')->where('cashier_id', $this->cashier->id)->delete();

        // Insert an existing active session to fill the limit
        DB::table('pos_sessions')->insert([
            'tenant_id'  => $this->tenant->id,
            'cashier_id' => $this->manager->id,
            'status'     => PosSession::STATUS_ACTIVE,
            'opened_at'  => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Now cashier tries to open another session — should be blocked
        $response = $this->postJson(route('pos.session.open'));

        $response->assertStatus(403);
        $this->assertStringContainsString('terminal limit', $response->json('message'));

        // Exactly 1 session should still exist
        $this->assertEquals(1, DB::table('pos_sessions')->where('tenant_id', $this->tenant->id)->count());
    }
}
