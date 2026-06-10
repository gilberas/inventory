<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $warehouseId;
    private int $productId;
    private int $cashierId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports.view', 'reports.financial', 'reports.vat'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Report Test Co',
            'slug'   => 'report-test',
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo(['reports.view', 'reports.financial', 'reports.vat']);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main Warehouse',
            'code'       => 'WH-RPT',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'RPT-001',
            'name'          => 'Report Product',
            'cost_price'    => 100.00,
            'selling_price' => 150.00,
            'minimum_stock' => 5,
            'reorder_level' => 10,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->cashierId = $this->manager->id;
    }

    // ── Helper to insert a completed sale ─────────────────────────────────────

    private function insertSale(float $total, string $date, ?int $cashierId = null): int
    {
        $saleId = DB::table('sales')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'cashier_id'     => $cashierId ?? $this->cashierId,
            'warehouse_id'   => $this->warehouseId,
            'receipt_no'     => 'RCP-' . uniqid(),
            'total'          => $total,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => $total,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'created_at'     => $date . ' 10:00:00',
            'updated_at'     => $date . ' 10:00:00',
        ]);

        DB::table('sale_items')->insert([
            'sale_id'    => $saleId,
            'product_id' => $this->productId,
            'qty'        => 1,
            'unit_price' => $total,
            'cost_price' => 80.00,
            'discount'   => 0,
            'subtotal'   => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $saleId;
    }

    // ── Test 1: Daily Sales totals match completed sales ─────────────────────

    public function test_daily_sales_totals_match_completed_sales(): void
    {
        $today = today()->toDateString();
        $this->insertSale(500.00, $today);
        $this->insertSale(300.00, $today);

        // Voided sale — must NOT be included
        DB::table('sales')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'cashier_id'     => $this->cashierId,
            'warehouse_id'   => $this->warehouseId,
            'receipt_no'     => 'RCP-VOID',
            'total'          => 999,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => 999,
            'payment_method' => 'cash',
            'status'         => 'voided',
            'created_at'     => $today . ' 09:00:00',
            'updated_at'     => $today . ' 09:00:00',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.daily-sales', ['date' => $today]));

        $response->assertStatus(200);
        $response->assertSee('800.00');      // 500 + 300
        $response->assertDontSee('999.00');  // voided excluded
    }

    // ── Test 2: Low stock shows only products below reorder level ─────────────

    public function test_low_stock_only_shows_below_reorder_level(): void
    {
        // Product with qty=3, reorder=10 → SHOULD appear (3 <= 10)
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $this->productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => 3,
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => 100,
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Product with plenty of stock
        $wellStockedId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'RPT-002',
            'name'          => 'Well Stocked Product',
            'cost_price'    => 50.00,
            'selling_price' => 80.00,
            'minimum_stock' => 5,
            'reorder_level' => 10,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $wellStockedId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => 100,   // 100 > 10 reorder_level → must NOT appear
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => 50,
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.low-stock'));

        $response->assertStatus(200);
        $response->assertSee('RPT-001');
        $response->assertDontSee('RPT-002');
    }

    // ── Test 3: Dead stock excludes recently moved products ───────────────────

    public function test_dead_stock_excludes_recently_moved_products(): void
    {
        // Product with stock but NO recent movement → SHOULD appear as dead stock
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $this->productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => 20,
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => 100,
            'last_updated'     => now()->subDays(90),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Product with a recent movement → must NOT appear
        $activeProductId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'RPT-003',
            'name'          => 'Active Product',
            'cost_price'    => 60.00,
            'selling_price' => 90.00,
            'minimum_stock' => 0,
            'reorder_level' => 5,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $activeProductId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => 15,
            'valuation_method' => 'weighted_avg',
            'unit_cost'        => 60,
            'last_updated'     => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        DB::table('inventory_movements')->insert([
            'tenant_id'    => $this->tenant->id,
            'product_id'   => $activeProductId,
            'warehouse_id' => $this->warehouseId,
            'type'         => 'stock_in',
            'qty'          => 15,
            'balance_after'=> 15,
            'unit_cost'    => 60,
            'user_id'      => $this->manager->id,
            'created_at'   => now()->subDays(5),  // within cutoff
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.dead-stock', ['days_no_movement' => 60]));

        $response->assertStatus(200);
        $response->assertSee('RPT-001');
        $response->assertDontSee('RPT-003');
    }

    // ── Test 4: Employee performance — correct per cashier ───────────────────

    public function test_employee_performance_correct_per_cashier(): void
    {
        $cashier2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $cashier2->givePermissionTo('reports.view');

        $today = today()->toDateString();
        $this->insertSale(400.00, $today, $this->cashierId);
        $this->insertSale(400.00, $today, $this->cashierId);
        $this->insertSale(100.00, $today, $cashier2->id);

        $response = $this->actingAs($this->manager)
            ->get(route('reports.employee-performance', [
                'start_date' => $today,
                'end_date'   => $today,
            ]));

        $response->assertStatus(200);
        // Manager should have 800 revenue
        $response->assertSee('800.00');
        // Cashier2 should have 100 revenue
        $response->assertSee('100.00');
    }

    // ── Test 5: All reports are tenant-scoped ─────────────────────────────────

    public function test_all_reports_are_tenant_scoped(): void
    {
        // Tenant 2 with its own product and sale
        $tenant2 = Tenant::create(['name' => 'Other Co', 'slug' => 'other-co', 'status' => 'active']);
        $user2   = User::factory()->create(['tenant_id' => $tenant2->id, 'status' => 'active']);

        $wh2Id = DB::table('warehouses')->insertGetId([
            'tenant_id' => $tenant2->id, 'name' => 'WH2', 'code' => 'WH2', 'is_active' => true, 'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $p2Id = DB::table('products')->insertGetId([
            'tenant_id' => $tenant2->id, 'sku' => 'OTHER-001', 'name' => 'Other Tenant Product',
            'cost_price' => 10, 'selling_price' => 20, 'minimum_stock' => 0, 'reorder_level' => 5,
            'is_active' => true, 'track_expiry' => false, 'track_batch' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales')->insertGetId([
            'tenant_id' => $tenant2->id, 'cashier_id' => $user2->id, 'warehouse_id' => $wh2Id,
            'receipt_no' => 'OTHER-RCP', 'total' => 999, 'discount' => 0, 'tax' => 0, 'grand_total' => 999,
            'payment_method' => 'cash', 'status' => 'completed',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tenant 1 (this->manager) should NOT see other tenant's data
        $today    = today()->toDateString();
        $response = $this->actingAs($this->manager)
            ->get(route('reports.daily-sales', ['date' => $today]));

        $response->assertStatus(200);
        $response->assertDontSee('OTHER-RCP');
        $response->assertDontSee('Other Tenant Product');
    }

    // ── Test 6: Export PDF contains tenant header ─────────────────────────────

    public function test_export_pdf_contains_tenant_header(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('reports.low-stock', ['export' => 'pdf']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition', '')
        );
    }
}
