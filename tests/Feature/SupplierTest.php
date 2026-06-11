<?php

namespace Tests\Feature;

use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $warehouseId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'purchases.view',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.create',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.manage',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'purchases.receive', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.view',    'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.adjust',  'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name'   => 'Supplier Test Co',
            'slug'   => 'sup-test',
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo([
            'purchases.view', 'purchases.create', 'purchases.manage', 'purchases.receive',
            'inventory.view', 'inventory.adjust',
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Test Warehouse',
            'code'       => 'WH-SUP-TEST',
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-SUP-001',
            'name'          => 'Supplier Test Product',
            'cost_price'    => 100,
            'selling_price' => 150,
            'minimum_stock' => 0,
            'reorder_level' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createSupplier(string $name = 'Test Supplier', string $status = 'active'): int
    {
        return DB::table('suppliers')->insertGetId([
            'tenant_id'   => $this->tenant->id,
            'name'        => $name,
            'code'        => 'SUP-' . strtoupper(substr($name, 0, 3)) . '-' . rand(100, 999),
            'is_active'   => $status === 'active',
            'status'      => $status,
            'balance'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function createApprovedPo(int $supplierId, int $qty = 10, ?string $expectedDate = null, ?string $createdAt = null): array
    {
        $poId = DB::table('purchase_orders')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'reference_no'  => 'PO-SUP-' . rand(1000, 9999),
            'supplier_id'   => $supplierId,
            'warehouse_id'  => $this->warehouseId,
            'created_by'    => $this->manager->id,
            'status'        => PurchaseOrder::STATUS_APPROVED,
            'order_date'    => now()->toDateString(),
            'expected_date' => $expectedDate,
            'total_amount'  => $qty * 100,
            'created_at'    => $createdAt ?? now(),
            'updated_at'    => now(),
        ]);

        $poItemId = DB::table('purchase_order_items')->insertGetId([
            'purchase_order_id' => $poId,
            'product_id'        => $this->productId,
            'quantity_ordered'  => $qty,
            'quantity_received' => 0,
            'unit_cost'         => 100,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return ['po_id' => $poId, 'po_item_id' => $poItemId];
    }

    private function insertConfirmedGrn(int $poId, int $qty, string $receivedAt): int
    {
        $grnId = DB::table('goods_received_notes')->insertGetId([
            'tenant_id'         => $this->tenant->id,
            'purchase_order_id' => $poId,
            'received_by'       => $this->manager->id,
            'warehouse_id'      => $this->warehouseId,
            'status'            => GoodsReceivedNote::STATUS_CONFIRMED,
            'reference_no'      => 'GRN-SUP-' . rand(1000, 9999),
            'received_at'       => $receivedAt,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('grn_items')->insert([
            'grn_id'      => $grnId,
            'product_id'  => $this->productId,
            'qty_received' => $qty,
            'unit_cost'   => 100,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return $grnId;
    }

    // ── Test 1 ───────────────────────────────────────────────────────────────

    public function test_supplier_balance_increases_on_grn_and_decreases_on_payment(): void
    {
        $supplierId = $this->createSupplier('Balance Supplier');
        ['po_id' => $poId] = $this->createApprovedPo($supplierId, qty: 10);

        $this->actingAs($this->manager);

        // Create and confirm GRN — 10 units × 50/unit = 500
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

        $supplier = Supplier::find($supplierId);
        $this->assertEqualsWithDelta(500.0, (float) $supplier->balance, 0.01,
            'Balance should increase by GRN total (10 × 50 = 500) after GRN confirmation.');

        // Create invoice and pay it
        $this->post(route('invoices.store'), [
            'supplier_id'    => $supplierId,
            'grn_id'         => $grn->id,
            'invoice_number' => 'INV-BAL-001',
            'amount'         => 300,
            'due_date'       => now()->addDays(30)->toDateString(),
        ]);

        $invoice = SupplierInvoice::latest()->first();
        $this->post(route('invoices.pay', $invoice));

        $supplier->refresh();
        $this->assertEqualsWithDelta(200.0, (float) $supplier->balance, 0.01,
            'Balance should decrease by paid invoice amount (500 - 300 = 200).');
    }

    // ── Test 2 ───────────────────────────────────────────────────────────────

    public function test_aging_buckets_correctly_by_due_date(): void
    {
        $supplierId = $this->createSupplier('Aging Supplier');

        $this->actingAs($this->manager);

        $base = [
            'tenant_id'      => $this->tenant->id,
            'supplier_id'    => $supplierId,
            'invoice_number' => 'INV-AGING-',
            'tax_amount'     => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        // current — due 5 days from now
        DB::table('supplier_invoices')->insert(array_merge($base, [
            'invoice_number' => 'INV-AGING-01',
            'amount'         => 1000,
            'due_date'       => now()->addDays(5)->toDateString(),
            'status'         => 'pending',
        ]));

        // 30d bucket — 15 days overdue
        DB::table('supplier_invoices')->insert(array_merge($base, [
            'invoice_number' => 'INV-AGING-02',
            'amount'         => 500,
            'due_date'       => now()->subDays(15)->toDateString(),
            'status'         => 'pending',
        ]));

        // 60d bucket — 45 days overdue
        DB::table('supplier_invoices')->insert(array_merge($base, [
            'invoice_number' => 'INV-AGING-03',
            'amount'         => 300,
            'due_date'       => now()->subDays(45)->toDateString(),
            'status'         => 'pending',
        ]));

        // 90d_plus bucket — 75 days overdue
        DB::table('supplier_invoices')->insert(array_merge($base, [
            'invoice_number' => 'INV-AGING-04',
            'amount'         => 200,
            'due_date'       => now()->subDays(75)->toDateString(),
            'status'         => 'pending',
        ]));

        // Paid — must be excluded from aging
        DB::table('supplier_invoices')->insert(array_merge($base, [
            'invoice_number' => 'INV-AGING-PAID',
            'amount'         => 999,
            'due_date'       => now()->subDays(20)->toDateString(),
            'status'         => 'paid',
        ]));

        $supplier = Supplier::find($supplierId);
        $aging    = $supplier->getAgingAnalysis();

        $this->assertEqualsWithDelta(1000.0, $aging['current'],      0.01);
        $this->assertEqualsWithDelta(500.0,  $aging['days_30'],      0.01);
        $this->assertEqualsWithDelta(300.0,  $aging['days_60'],      0.01);
        $this->assertEqualsWithDelta(200.0,  $aging['days_90_plus'], 0.01);
    }

    // ── Test 3 ───────────────────────────────────────────────────────────────

    public function test_on_time_delivery_rate_calculated(): void
    {
        $supplierId = $this->createSupplier('Delivery Supplier');
        $this->actingAs($this->manager);

        // PO1: expected 5 days ago. GRN received 7 days ago → ON TIME (received before expected)
        ['po_id' => $po1Id] = $this->createApprovedPo(
            $supplierId,
            expectedDate: now()->subDays(5)->toDateString(),
            createdAt: now()->subDays(15),
        );
        $this->insertConfirmedGrn($po1Id, 10, now()->subDays(7)->toDateString());

        // PO2: expected 15 days ago. GRN received 5 days ago → LATE (received after expected)
        ['po_id' => $po2Id] = $this->createApprovedPo(
            $supplierId,
            expectedDate: now()->subDays(15)->toDateString(),
            createdAt: now()->subDays(30),
        );
        $this->insertConfirmedGrn($po2Id, 10, now()->subDays(5)->toDateString());

        $response = $this->get(route('suppliers.show', $supplierId));
        $response->assertOk();

        $rate = $response->json('metrics.on_time_delivery_rate');
        $this->assertEqualsWithDelta(50.0, $rate, 0.01,
            'On-time rate should be 50% (1 of 2 GRNs delivered before expected_date).');
    }

    // ── Test 4 ───────────────────────────────────────────────────────────────

    public function test_average_lead_time_calculated(): void
    {
        $supplierId = $this->createSupplier('Lead Time Supplier');
        $this->actingAs($this->manager);

        // PO1: created 15 days ago, received 10 days ago → lead time = 5 days
        ['po_id' => $po1Id] = $this->createApprovedPo(
            $supplierId,
            createdAt: now()->subDays(15),
        );
        $this->insertConfirmedGrn($po1Id, 5, now()->subDays(10)->toDateString());

        // PO2: created 20 days ago, received 5 days ago → lead time = 15 days
        ['po_id' => $po2Id] = $this->createApprovedPo(
            $supplierId,
            createdAt: now()->subDays(20),
        );
        $this->insertConfirmedGrn($po2Id, 5, now()->subDays(5)->toDateString());

        $response = $this->get(route('suppliers.show', $supplierId));
        $response->assertOk();

        $avgLead = $response->json('metrics.average_lead_time');
        $this->assertEqualsWithDelta(10.0, $avgLead, 0.5,
            'Average lead time should be (5 + 15) / 2 = 10 days.');
    }

    // ── Test 5 ───────────────────────────────────────────────────────────────

    public function test_inactive_supplier_excluded_from_default_list(): void
    {
        $this->actingAs($this->manager);

        // Create active supplier
        $this->post(route('suppliers.store'), [
            'name'  => 'Active Supplier',
            'phone' => '0700000001',
        ]);

        // Create second supplier, then deactivate it via destroy
        $this->post(route('suppliers.store'), [
            'name'  => 'Soon Inactive Supplier',
            'phone' => '0700000002',
        ]);
        $toDeactivate = Supplier::where('name', 'Soon Inactive Supplier')->first();
        $this->delete(route('suppliers.destroy', $toDeactivate));

        // Default index (no ?status param) shows only active
        $response = $this->getJson(route('suppliers.index'));
        $response->assertOk();

        $names = collect($response->json('data.data'))->pluck('name')->toArray();
        $this->assertContains('Active Supplier', $names);
        $this->assertNotContains('Soon Inactive Supplier', $names);

        // Verify it wasn't hard-deleted — still in DB
        $this->assertDatabaseHas('suppliers', [
            'name'   => 'Soon Inactive Supplier',
            'status' => 'inactive',
        ]);
    }

    // ── Test 6 ───────────────────────────────────────────────────────────────

    public function test_suppliers_are_tenant_scoped(): void
    {
        $tenantB = Tenant::create([
            'name'   => 'Other Tenant',
            'slug'   => 'other-tenant',
            'status' => 'active',
        ]);

        // Insert a supplier directly for tenant B
        DB::table('suppliers')->insert([
            'tenant_id'  => $tenantB->id,
            'name'       => 'Tenant B Supplier',
            'code'       => 'SUP-TB-001',
            'is_active'  => true,
            'status'     => 'active',
            'balance'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a supplier for our tenant
        $this->actingAs($this->manager);
        $this->post(route('suppliers.store'), ['name' => 'Our Supplier']);

        // Index must not return tenant B's supplier
        $response = $this->getJson(route('suppliers.index', ['status' => 'all']));
        $response->assertOk();

        $names = collect($response->json('data.data'))->pluck('name')->toArray();
        $this->assertContains('Our Supplier', $names);
        $this->assertNotContains('Tenant B Supplier', $names);
    }
}
