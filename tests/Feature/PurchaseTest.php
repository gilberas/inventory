<?php

namespace Tests\Feature;

use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\InvoiceDiscrepancy;
use App\Notifications\RequisitionSubmitted;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private User $buyer;
    private int $supplierId;
    private int $warehouseId;
    private int $productId;
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'purchase_orders.manage',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchase_orders.receive', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.audit',   'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.adjust',  'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name'   => 'PO Test Co',
            'slug'   => 'po-test',
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);

        // Manager — can approve requisitions and POs
        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo([
            'purchase_orders.manage', 'purchase_orders.receive',
            'inventory.audit', 'inventory.adjust',
        ]);

        // Buyer — can submit requisitions but not approve
        $this->buyer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->buyer->givePermissionTo(['purchase_orders.receive']);

        $this->supplierId = DB::table('suppliers')->insertGetId([
            'tenant_id'   => $this->tenant->id,
            'name'        => 'Test Supplier',
            'code'        => 'SUP-TEST',
            'is_active'   => true,
            'balance'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main Warehouse',
            'code'       => 'WH-MAIN',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-PO-001',
            'name'          => 'Test Product',
            'cost_price'    => 50,
            'selling_price' => 80,
            'minimum_stock' => 2,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->service = app(InventoryService::class);
    }

    // ── Helper ──────────────────────────────────────────────────────────────────

    private function createApprovedPoWithItem(int $qty = 10): array
    {
        $poId = DB::table('purchase_orders')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'reference_no' => 'PO-TEST-' . rand(1000, 9999),
            'supplier_id'  => $this->supplierId,
            'warehouse_id' => $this->warehouseId,
            'created_by'   => $this->manager->id,
            'status'       => PurchaseOrder::STATUS_APPROVED,
            'order_date'   => now()->toDateString(),
            'total_amount' => $qty * 50,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $poItemId = DB::table('purchase_order_items')->insertGetId([
            'purchase_order_id' => $poId,
            'product_id'        => $this->productId,
            'quantity_ordered'  => $qty,
            'quantity_received' => 0,
            'unit_cost'         => 50,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return ['po_id' => $poId, 'po_item_id' => $poItemId];
    }

    private function createConfirmedGrn(int $poId, int $qty, float $unitCost = 50.0): GoodsReceivedNote
    {
        $this->actingAs($this->manager);

        $this->post(route('grn.store'), [
            'purchase_order_id' => $poId,
            'warehouse_id'      => $this->warehouseId,
            'items'             => [[
                'product_id'  => $this->productId,
                'qty_received' => $qty,
                'unit_cost'   => $unitCost,
            ]],
        ]);

        $grn = GoodsReceivedNote::latest()->first();

        $this->post(route('grn.confirm', $grn));

        return $grn->fresh();
    }

    // ── Test 1 ──────────────────────────────────────────────────────────────────

    public function test_requisition_notifies_managers_on_submit(): void
    {
        Notification::fake();

        // Buyer creates a draft requisition
        $this->actingAs($this->buyer)->post(route('requisitions.store'), [
            'notes' => 'Need 10 units for Q3',
            'items' => [[
                'product_id'    => $this->productId,
                'qty_requested' => 10,
            ]],
        ]);

        $requisition = \App\Models\PurchaseRequisition::latest()->first();
        $this->assertNotNull($requisition);
        $this->assertEquals('draft', $requisition->status);

        // Buyer submits it
        $this->actingAs($this->buyer)
            ->post(route('requisitions.submit', $requisition))
            ->assertRedirect();

        $requisition->refresh();
        $this->assertEquals('pending', $requisition->status);

        // Manager should be notified (queued notification captured by fake)
        Notification::assertSentTo($this->manager, RequisitionSubmitted::class);
    }

    // ── Test 2 ──────────────────────────────────────────────────────────────────

    public function test_only_manager_can_approve_requisition(): void
    {
        // Create a pending requisition
        $this->actingAs($this->buyer)->post(route('requisitions.store'), [
            'items' => [['product_id' => $this->productId, 'qty_requested' => 5]],
        ]);
        $requisition = \App\Models\PurchaseRequisition::latest()->first();
        $this->actingAs($this->buyer)->post(route('requisitions.submit', $requisition));

        // Buyer (no purchases.manage) tries to approve → 403
        $this->actingAs($this->buyer)
            ->post(route('requisitions.approve', $requisition))
            ->assertStatus(403);

        // Manager approves → redirect (2xx/3xx)
        $this->actingAs($this->manager)
            ->post(route('requisitions.approve', $requisition))
            ->assertRedirect();

        $requisition->refresh();
        $this->assertEquals('approved', $requisition->status);
    }

    // ── Test 3 ──────────────────────────────────────────────────────────────────

    public function test_grn_triggers_stock_in_for_each_item(): void
    {
        ['po_id' => $poId] = $this->createApprovedPoWithItem(10);

        $this->actingAs($this->manager);

        // Create GRN
        $this->post(route('grn.store'), [
            'purchase_order_id' => $poId,
            'warehouse_id'      => $this->warehouseId,
            'items'             => [[
                'product_id'  => $this->productId,
                'qty_received' => 10,
                'unit_cost'   => 50,
            ]],
        ])->assertRedirect();

        $grn = GoodsReceivedNote::latest()->first();
        $this->assertNotNull($grn);
        $this->assertEquals(GoodsReceivedNote::STATUS_DRAFT, $grn->status);

        // Confirm GRN — triggers InventoryService::stockIn
        $this->post(route('grn.confirm', $grn))->assertRedirect();

        $grn->refresh();
        $this->assertEquals(GoodsReceivedNote::STATUS_CONFIRMED, $grn->status);

        // Inventory balance must reflect received qty
        $balance = $this->service->getBalance($this->productId, $this->warehouseId);
        $this->assertEqualsWithDelta(10.0, $balance, 0.001);
    }

    // ── Test 4 ──────────────────────────────────────────────────────────────────

    public function test_partial_grn_leaves_po_open(): void
    {
        ['po_id' => $poId] = $this->createApprovedPoWithItem(20);
        $this->actingAs($this->manager);

        // Receive only 10 of 20 ordered
        $this->post(route('grn.store'), [
            'purchase_order_id' => $poId,
            'warehouse_id'      => $this->warehouseId,
            'items'             => [[
                'product_id'  => $this->productId,
                'qty_received' => 10,
                'unit_cost'   => 50,
            ]],
        ]);

        $grn = GoodsReceivedNote::latest()->first();
        $this->post(route('grn.confirm', $grn));

        $po = PurchaseOrder::find($poId);
        $this->assertEquals(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->status);
    }

    // ── Test 5 ──────────────────────────────────────────────────────────────────

    public function test_full_receipt_closes_po(): void
    {
        ['po_id' => $poId] = $this->createApprovedPoWithItem(10);
        $this->actingAs($this->manager);

        // Receive all 10 ordered
        $this->post(route('grn.store'), [
            'purchase_order_id' => $poId,
            'warehouse_id'      => $this->warehouseId,
            'items'             => [[
                'product_id'  => $this->productId,
                'qty_received' => 10,
                'unit_cost'   => 50,
            ]],
        ]);

        $grn = GoodsReceivedNote::latest()->first();
        $this->post(route('grn.confirm', $grn));

        $po = PurchaseOrder::find($poId);
        $this->assertEquals(PurchaseOrder::STATUS_RECEIVED, $po->status);
    }

    // ── Test 6 ──────────────────────────────────────────────────────────────────

    public function test_invoice_discrepancy_flag_set_when_over_1_percent(): void
    {
        Notification::fake();

        // GRN total = 10 units × 100/unit = 1000
        ['po_id' => $poId] = $this->createApprovedPoWithItem(10);
        $grn = $this->createConfirmedGrn($poId, 10, 100.0);

        $this->actingAs($this->manager);

        // Invoice amount = 1015 (1.5% above GRN total of 1000 — should trigger flag)
        $this->post(route('invoices.store'), [
            'supplier_id'    => $this->supplierId,
            'grn_id'         => $grn->id,
            'invoice_number' => 'INV-TEST-001',
            'amount'         => 1015,
            'due_date'       => now()->addDays(30)->toDateString(),
        ])->assertRedirect();

        $invoice = SupplierInvoice::latest()->first();
        $this->assertNotNull($invoice);

        // Match invoice against GRN
        $this->post(route('invoices.match', $invoice))->assertRedirect();

        $invoice->refresh();
        $this->assertTrue((bool) $invoice->discrepancy_flag);

        // InvoiceDiscrepancy notification sent to manager
        Notification::assertSentTo($this->manager, InvoiceDiscrepancy::class);
    }

    // ── Test 7 ──────────────────────────────────────────────────────────────────

    public function test_purchase_return_reverses_stock_in(): void
    {
        // Stock in 20 units via GRN confirm
        ['po_id' => $poId] = $this->createApprovedPoWithItem(20);
        $grn = $this->createConfirmedGrn($poId, 20, 50.0);

        $balanceAfterReceipt = $this->service->getBalance($this->productId, $this->warehouseId);
        $this->assertEqualsWithDelta(20.0, $balanceAfterReceipt, 0.001);

        $this->actingAs($this->manager);

        // Return 5 units
        $this->post(route('purchase-returns.store'), [
            'supplier_id' => $this->supplierId,
            'grn_id'      => $grn->id,
            'reason'      => 'Damaged goods received',
            'items'       => [[
                'product_id' => $this->productId,
                'qty'        => 5,
                'unit_cost'  => 50,
            ]],
        ])->assertRedirect();

        // Balance should drop from 20 to 15
        $balanceAfterReturn = $this->service->getBalance($this->productId, $this->warehouseId);
        $this->assertEqualsWithDelta(15.0, $balanceAfterReturn, 0.001);
    }
}
