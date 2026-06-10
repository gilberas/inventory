<?php

namespace Tests\Feature;

use App\Exceptions\AuditInProgressException;
use App\Models\InventoryAudit;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private User $storekeeper;
    private int $warehouseId;
    private int $productId;
    private int $productId2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        foreach (['inventory.audit', 'inventory.audit_count', 'inventory.adjust'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Audit Test Co',
            'slug'   => 'audit-test',
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo(['inventory.audit', 'inventory.audit_count', 'inventory.adjust']);

        $this->storekeeper = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->storekeeper->givePermissionTo('inventory.audit_count');

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main WH',
            'code'       => 'WH-AUDIT',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'AUD-001',
            'name'          => 'Audit Product A',
            'cost_price'    => 100.00,
            'selling_price' => 150.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->productId2 = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'AUD-002',
            'name'          => 'Audit Product B',
            'cost_price'    => 50.00,
            'selling_price' => 80.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seedInventory(int $productId, float $qty, float $cost = 100.0): void
    {
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => $qty,
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => $cost,
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function initiateAudit(): InventoryAudit
    {
        $response = $this->actingAs($this->manager)
            ->post(route('audits.store'), [
                '_token'       => csrf_token(),
                'warehouse_id' => $this->warehouseId,
                'audit_date'   => date('Y-m-d'),
            ]);
        $response->assertRedirect();

        return InventoryAudit::withoutGlobalScopes()->latest()->first();
    }

    // ── Test 1: snapshot taken at initiation ─────────────────────────────────

    public function test_audit_snapshots_quantities_at_initiation(): void
    {
        $this->seedInventory($this->productId,  20.0);
        $this->seedInventory($this->productId2, 35.0);

        $audit = $this->initiateAudit();

        $this->assertNotNull($audit);
        $this->assertEquals('initiated', $audit->status);

        $items = DB::table('inventory_audit_items')
            ->where('audit_id', $audit->id)
            ->get()
            ->keyBy('product_id');

        $this->assertCount(2, $items);
        $this->assertEquals(20.0, (float) $items[$this->productId]->system_qty);
        $this->assertEquals(35.0, (float) $items[$this->productId2]->system_qty);
        $this->assertNull($items[$this->productId]->physical_qty, 'physical_qty must be null until counted');
    }

    // ── Test 2: adjustments blocked during active audit ───────────────────────

    public function test_adjustments_blocked_during_active_audit(): void
    {
        $this->seedInventory($this->productId, 10.0);
        $this->initiateAudit();

        $service = app(InventoryService::class);

        // All three mutation methods must throw AuditInProgressException
        $this->expectException(AuditInProgressException::class);

        $this->actingAs($this->manager);
        $service->adjust($this->productId, $this->warehouseId, 5.0, 'Test adjust', $this->manager->id);
    }

    // ── Test 3: count sheet hides system_qty ──────────────────────────────────

    public function test_count_sheet_hides_system_qty(): void
    {
        $this->seedInventory($this->productId, 25.0);
        $audit = $this->initiateAudit();

        $response = $this->actingAs($this->storekeeper)
            ->get(route('audits.sheet', $audit));

        $response->assertStatus(200);

        // The page must NOT contain the system quantity (25) anywhere in a context
        // that reveals it to the storekeeper — check the view passes null for system_qty
        $response->assertSee('Hidden');
        $response->assertDontSee('25.00');
    }

    // ── Test 4: variance calculated correctly ─────────────────────────────────

    public function test_variance_calculated_correctly(): void
    {
        $this->seedInventory($this->productId, 20.0);
        $audit = $this->initiateAudit();
        $item = DB::table('inventory_audit_items')->where('audit_id', $audit->id)->first();

        $this->actingAs($this->storekeeper)
            ->post(route('audits.counts', $audit), [
                '_token' => csrf_token(),
                'items'  => [
                    ['id' => $item->id, 'physical_qty' => 17.0, 'notes' => 'Found 3 missing'],
                ],
            ])
            ->assertRedirect();

        $fresh = DB::table('inventory_audit_items')->find($item->id);
        $this->assertEquals(17.0, (float) $fresh->physical_qty);
        $this->assertEquals(-3.0, (float) $fresh->variance, 'variance = physical - system = 17 - 20 = -3');
    }

    // ── Test 5: posting applies inventory adjustments for each variance ────────

    public function test_posting_audit_calls_inventory_adjust_for_each_variance(): void
    {
        $this->seedInventory($this->productId,  20.0, 100.0);
        $this->seedInventory($this->productId2, 10.0,  50.0);
        $audit = $this->initiateAudit();

        $items = DB::table('inventory_audit_items')
            ->where('audit_id', $audit->id)
            ->get()
            ->keyBy('product_id');

        // Submit counts: product A short by 3, product B overage by 5
        $this->actingAs($this->storekeeper)
            ->post(route('audits.counts', $audit), [
                '_token' => csrf_token(),
                'items'  => [
                    ['id' => $items[$this->productId]->id,  'physical_qty' => 17.0, 'notes' => ''],
                    ['id' => $items[$this->productId2]->id, 'physical_qty' => 15.0, 'notes' => ''],
                ],
            ]);

        // Post the audit
        $this->actingAs($this->manager)
            ->post(route('audits.post', $audit), ['_token' => csrf_token()])
            ->assertRedirect();

        $freshAudit = InventoryAudit::withoutGlobalScopes()->find($audit->id);
        $this->assertEquals('posted', $freshAudit->status);

        // Inventory should reflect the adjustments
        $qtyA = DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');
        $qtyB = DB::table('inventory')
            ->where('product_id', $this->productId2)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');

        $this->assertEquals(17.0, (float) $qtyA, 'Product A: 20 - 3 = 17');
        $this->assertEquals(15.0, (float) $qtyB, 'Product B: 10 + 5 = 15');

        // Verify adjustment movements recorded
        $movements = DB::table('inventory_movements')
            ->where('reference_type', 'audit')
            ->where('reference_id', $audit->id)
            ->get();
        $this->assertCount(2, $movements, 'Two audit-type inventory movements must be recorded');
    }

    // ── Test 6: warehouse unblocked after posting ─────────────────────────────

    public function test_warehouse_unblocked_after_posting(): void
    {
        $this->seedInventory($this->productId, 10.0);
        $audit = $this->initiateAudit();
        $item  = DB::table('inventory_audit_items')->where('audit_id', $audit->id)->first();

        // Submit count
        $this->actingAs($this->storekeeper)
            ->post(route('audits.counts', $audit), [
                '_token' => csrf_token(),
                'items'  => [['id' => $item->id, 'physical_qty' => 10.0, 'notes' => '']],
            ]);

        // Post it
        $this->actingAs($this->manager)
            ->post(route('audits.post', $audit), ['_token' => csrf_token()]);

        // After posting, adjustments must succeed without exception
        $service = app(InventoryService::class);

        $this->actingAs($this->manager);
        $service->adjust($this->productId, $this->warehouseId, 3.0, 'Post-audit adjustment', $this->manager->id);

        $newQty = DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');

        $this->assertEquals(13.0, (float) $newQty, 'Warehouse must accept adjustments after audit is posted');
    }
}
