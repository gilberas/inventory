<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Events\OutOfStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $productId;
    private int $warehouseId;
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.adjust', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.view',   'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name' => 'Inv Test Co', 'slug' => 'inv-test', 'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->assignRole('business_owner');
        $this->manager->givePermissionTo(['inventory.adjust', 'inventory.view']);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main WH',
            'code'       => 'WH-INV-TEST',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-INV-001',
            'name'          => 'Test Product',
            'cost_price'    => 100,
            'selling_price' => 150,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->service = new InventoryService();
    }

    // ── Test 1 ───────────────────────────────────────────────────────────────

    public function test_stock_in_increments_and_records_movement(): void
    {
        $this->actingAs($this->manager);

        $this->service->stockIn(
            $this->productId, $this->warehouseId,
            10, 100.0,
            'test', 1, $this->manager->id
        );

        $this->assertEqualsWithDelta(10.0, $this->service->getBalance($this->productId, $this->warehouseId), 0.001);

        $this->assertDatabaseHas('inventory', [
            'product_id'   => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'quantity'     => 10,
            'tenant_id'    => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id'   => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'type'         => 'stock_in',
            'qty'          => 10,
            'balance_after'=> 10,
        ]);
    }

    // ── Test 2 ───────────────────────────────────────────────────────────────

    public function test_stock_out_decrements_and_records_movement(): void
    {
        $this->actingAs($this->manager);

        $this->service->stockIn($this->productId, $this->warehouseId, 20, 100.0, 'test', 1, $this->manager->id);
        $this->service->stockOut($this->productId, $this->warehouseId, 8, 'test', 1, $this->manager->id);

        $this->assertEqualsWithDelta(12.0, $this->service->getBalance($this->productId, $this->warehouseId), 0.001);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id'   => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'type'         => 'stock_out',
            'balance_after'=> 12,
        ]);
    }

    // ── Test 3 ───────────────────────────────────────────────────────────────

    public function test_stock_out_below_zero_throws_exception(): void
    {
        $this->actingAs($this->manager);

        $this->service->stockIn($this->productId, $this->warehouseId, 5, 100.0, 'test', 1, $this->manager->id);

        $this->expectException(InsufficientStockException::class);

        $this->service->stockOut($this->productId, $this->warehouseId, 10, 'test', 1, $this->manager->id);
    }

    // ── Test 4 ───────────────────────────────────────────────────────────────

    public function test_adjustment_requires_reason(): void
    {
        $this->actingAs($this->manager);

        $this->service->stockIn($this->productId, $this->warehouseId, 10, 100.0, 'test', 1, $this->manager->id);

        $this->expectException(ValidationException::class);

        $this->service->adjust($this->productId, $this->warehouseId, 5, '', $this->manager->id);
    }

    // ── Test 5 ───────────────────────────────────────────────────────────────

    public function test_movement_table_has_no_update_route(): void
    {
        // inventory_movements is append-only: no PUT/PATCH route must be registered
        $response = $this->actingAs($this->manager)->put('/inventory/movements/1');

        // 404 (no such route) or 405 (method not allowed) — either confirms no update route
        $this->assertContains($response->status(), [404, 405],
            "Expected 404 or 405 for PUT /inventory/movements/1, got {$response->status()}."
        );

        $response2 = $this->actingAs($this->manager)->patch('/inventory/movements/1');
        $this->assertContains($response2->status(), [404, 405],
            "Expected 404 or 405 for PATCH /inventory/movements/1, got {$response2->status()}."
        );
    }

    // ── Test 6 ───────────────────────────────────────────────────────────────

    public function test_low_stock_event_fired_correctly(): void
    {
        Event::fake([LowStockDetected::class, OutOfStockDetected::class]);

        $this->actingAs($this->manager);

        // reorder_level = 5; stockIn 10, then stockOut 6 → balance 4 ≤ reorder_level
        $this->service->stockIn($this->productId, $this->warehouseId, 10, 100.0, 'test', 1, $this->manager->id);
        $this->service->stockOut($this->productId, $this->warehouseId, 6, 'test', 1, $this->manager->id);

        Event::assertDispatched(LowStockDetected::class, function (LowStockDetected $e) {
            return $e->product->id === $this->productId
                && (float) $e->currentQty === 4.0;
        });

        Event::assertNotDispatched(OutOfStockDetected::class);
    }

    // ── Test 7 ───────────────────────────────────────────────────────────────

    public function test_weighted_average_valuation_correct(): void
    {
        $this->actingAs($this->manager);

        // Buy 10 @ 100 → weighted avg = 100
        $this->service->stockIn($this->productId, $this->warehouseId, 10, 100.0, 'test', 1, $this->manager->id);
        // Buy 5 @ 200 → weighted avg = (10×100 + 5×200) / 15 = 2000/15 ≈ 133.33
        $this->service->stockIn($this->productId, $this->warehouseId, 5, 200.0, 'test', 2, $this->manager->id);

        // Total valuation = 15 × 133.33... = 2000
        $valuation = $this->service->recalculateValuation($this->productId, $this->warehouseId);

        $this->assertEqualsWithDelta(2000.0, $valuation, 0.01);
    }

    // ── Test 8 ───────────────────────────────────────────────────────────────

    public function test_fifo_valuation_correct(): void
    {
        $this->actingAs($this->manager);

        // Layer 1: 10 @ 100
        $this->service->stockIn($this->productId, $this->warehouseId, 10, 100.0, 'test', 1, $this->manager->id);

        // Switch to FIFO for this inventory position
        Inventory::where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->update(['valuation_method' => 'fifo']);

        // Layer 2: 5 @ 200
        $this->service->stockIn($this->productId, $this->warehouseId, 5, 200.0, 'test', 2, $this->manager->id);

        // stockOut 8 → FIFO consumes layer 1 first: 8 from [10@100]
        // Remaining: 2 from layer 1 @ 100, 5 from layer 2 @ 200
        $this->service->stockOut($this->productId, $this->warehouseId, 8, 'test', 3, $this->manager->id);

        // Expected: (2 × 100) + (5 × 200) = 200 + 1000 = 1200
        $valuation = $this->service->recalculateValuation($this->productId, $this->warehouseId);

        $this->assertEqualsWithDelta(1200.0, $valuation, 0.01);
    }
}
