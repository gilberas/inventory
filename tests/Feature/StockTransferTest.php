<?php

namespace Tests\Feature;

use App\Models\BranchTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $fromBranchId;
    private int $toBranchId;
    private int $fromWarehouseId;
    private int $toWarehouseId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles & permissions
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        foreach ([
            'transfers.view', 'transfers.create',
            'transfers.approve', 'transfers.dispatch', 'transfers.receive',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Tenant on Professional plan (passes CheckStockTransferFeature)
        $this->tenant = Tenant::create([
            'name'   => 'Transfer Test Co',
            'slug'   => 'transfer-test-co',
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->assignRole('Manager');
        $this->manager->givePermissionTo([
            'transfers.view', 'transfers.create',
            'transfers.approve', 'transfers.dispatch', 'transfers.receive',
        ]);

        // Two branches (raw SQL — bypasses Branch::created observer)
        $this->fromBranchId = DB::table('branches')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Branch A',
            'code'       => 'BRA',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->toBranchId = DB::table('branches')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Branch B',
            'code'       => 'BRB',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // One default warehouse per branch (raw SQL — bypasses Warehouse::booted is_default logic)
        $this->fromWarehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'branch_id'  => $this->fromBranchId,
            'name'       => 'Warehouse A',
            'code'       => 'WH-A',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->toWarehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'branch_id'  => $this->toBranchId,
            'name'       => 'Warehouse B',
            'code'       => 'WH-B',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Product
        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'TRN-001',
            'name'          => 'Transfer Product',
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

    private function seedSourceStock(float $qty): void
    {
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $this->productId,
            'warehouse_id'     => $this->fromWarehouseId,
            'quantity'         => $qty,
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => 50.00,
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function createApprovedTransfer(float $qty = 10): array
    {
        $transferId = DB::table('branch_transfers')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'from_branch_id' => $this->fromBranchId,
            'to_branch_id'   => $this->toBranchId,
            'requested_by'   => $this->manager->id,
            'status'         => 'approved',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $itemId = DB::table('branch_transfer_items')->insertGetId([
            'transfer_id'   => $transferId,
            'product_id'    => $this->productId,
            'qty_requested' => $qty,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return ['transferId' => $transferId, 'itemId' => $itemId];
    }

    private function createDispatchedTransfer(float $qtyDispatched = 10): array
    {
        $transferId = DB::table('branch_transfers')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'from_branch_id' => $this->fromBranchId,
            'to_branch_id'   => $this->toBranchId,
            'requested_by'   => $this->manager->id,
            'status'         => 'dispatched',
            'dispatched_at'  => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $itemId = DB::table('branch_transfer_items')->insertGetId([
            'transfer_id'    => $transferId,
            'product_id'     => $this->productId,
            'qty_requested'  => $qtyDispatched,
            'qty_dispatched' => $qtyDispatched,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return ['transferId' => $transferId, 'itemId' => $itemId];
    }

    // ── Test 1: inventory NOT changed at request time ─────────────────────────

    public function test_inventory_adjusted_at_dispatch_not_at_request(): void
    {
        $this->seedSourceStock(20);

        $this->actingAs($this->manager)
             ->post(route('transfers.store'), [
                 '_token'         => csrf_token(),
                 'from_branch_id' => $this->fromBranchId,
                 'to_branch_id'   => $this->toBranchId,
                 'items'          => [
                     ['product_id' => $this->productId, 'qty_requested' => 5],
                 ],
             ]);

        // Inventory must NOT have changed after the store (still 20)
        $qty = DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->fromWarehouseId)
            ->value('quantity');

        $this->assertEquals(20.0, (float) $qty, 'Inventory must not change at request time.');
    }

    // ── Test 2: dispatch decrements source warehouse ──────────────────────────

    public function test_dispatch_decrements_source_warehouse(): void
    {
        $this->seedSourceStock(20);
        ['transferId' => $transferId, 'itemId' => $itemId] = $this->createApprovedTransfer(8);

        $this->actingAs($this->manager)
             ->post(route('transfers.dispatch', $transferId), [
                 '_token' => csrf_token(),
                 'items'  => [
                     ['id' => $itemId, 'qty_dispatched' => 8],
                 ],
             ]);

        $qty = DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->fromWarehouseId)
            ->value('quantity');

        $this->assertEquals(12.0, (float) $qty, 'Source warehouse stock should decrease by dispatched qty.');
    }

    // ── Test 3: receive increments destination warehouse ─────────────────────

    public function test_receive_increments_destination_warehouse(): void
    {
        ['transferId' => $transferId, 'itemId' => $itemId] = $this->createDispatchedTransfer(10);

        $this->actingAs($this->manager)
             ->post(route('transfers.receive', $transferId), [
                 '_token' => csrf_token(),
                 'items'  => [
                     ['id' => $itemId, 'qty_received' => 10],
                 ],
             ]);

        $qty = DB::table('inventory')
            ->where('product_id', $this->productId)
            ->where('warehouse_id', $this->toWarehouseId)
            ->value('quantity');

        $this->assertEquals(10.0, (float) $qty, 'Destination warehouse stock should increase by received qty.');
    }

    // ── Test 4: starter plan cannot create transfer ───────────────────────────

    public function test_starter_plan_cannot_create_transfer(): void
    {
        $starterTenant = Tenant::create([
            'name'   => 'Starter Co',
            'slug'   => 'starter-co',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        $starterUser = User::factory()->create([
            'tenant_id' => $starterTenant->id,
            'status'    => 'active',
        ]);
        $starterUser->givePermissionTo('transfers.create');

        $response = $this->actingAs($starterUser)
            ->post(route('transfers.store'), [
                '_token'         => csrf_token(),
                'from_branch_id' => $this->fromBranchId,
                'to_branch_id'   => $this->toBranchId,
                'items'          => [
                    ['product_id' => $this->productId, 'qty_requested' => 5],
                ],
            ]);

        // Middleware redirects back with plan error
        $response->assertSessionHasErrors('plan');
    }

    // ── Test 5: discrepancy flagged when qty_received differs ─────────────────

    public function test_discrepancy_flagged_when_qty_differs(): void
    {
        ['transferId' => $transferId, 'itemId' => $itemId] = $this->createDispatchedTransfer(10);

        $this->actingAs($this->manager)
             ->post(route('transfers.receive', $transferId), [
                 '_token' => csrf_token(),
                 'items'  => [
                     ['id' => $itemId, 'qty_received' => 7],  // 3 units short
                 ],
             ]);

        $fresh = BranchTransfer::withoutGlobalScopes()->with('items')->find($transferId);

        $this->assertTrue($fresh->hasDiscrepancy(), 'Discrepancy should be detected when qty_received != qty_dispatched.');
        $this->assertEquals(7.0, (float) $fresh->items->first()->qty_received);
    }

    // ── Test 6: same-branch transfer is rejected ──────────────────────────────

    public function test_same_branch_transfer_rejected(): void
    {
        $response = $this->actingAs($this->manager)
            ->post(route('transfers.store'), [
                '_token'         => csrf_token(),
                'from_branch_id' => $this->fromBranchId,
                'to_branch_id'   => $this->fromBranchId,   // same as from
                'items'          => [
                    ['product_id' => $this->productId, 'qty_requested' => 5],
                ],
            ]);

        $response->assertSessionHasErrors('to_branch_id');
    }
}
