<?php

namespace Tests\Feature;

use App\Http\Controllers\FinancialController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinancialTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId;
    private int $warehouseId;
    private int $userId;
    private int $productId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reports.financial', 'reports.vat'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenantId = DB::table('tenants')->insertGetId([
            'name'       => 'Test Co',
            'slug'       => 'test-co',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'name'       => 'Main Warehouse',
            'code'       => 'WH-01',
            'is_default' => 1,
            'is_active'  => 1,
            'tenant_id'  => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->userId = User::factory()->create(['tenant_id' => $this->tenantId])->id;

        $this->productId = DB::table('products')->insertGetId([
            'sku'          => 'SKU-TEST-001',
            'name'         => 'Test Product',
            'cost_price'   => 100.00,
            'selling_price'=> 150.00,
            'tax_rate'     => 18.00,
            'is_active'    => 1,
            'tenant_id'    => $this->tenantId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertSale(float $grandTotal, string $status = 'completed'): int
    {
        return DB::table('sales')->insertGetId([
            'tenant_id'      => $this->tenantId,
            'cashier_id'     => $this->userId,
            'warehouse_id'   => $this->warehouseId,
            'status'         => $status,
            'grand_total'    => $grandTotal,
            'total'          => $grandTotal,
            'payment_method' => 'cash',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function insertSaleItem(int $saleId, float $qty, float $unitPrice, float $costPrice, float $subtotal, ?int $productId = null): void
    {
        DB::table('sale_items')->insert([
            'sale_id'    => $saleId,
            'product_id' => $productId ?? $this->productId,
            'qty'        => $qty,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
            'discount'   => 0,
            'subtotal'   => $subtotal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function controller(): FinancialController
    {
        return new FinancialController();
    }

    private function period(): array
    {
        return [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ];
    }

    // ── test_pnl_revenue_matches_completed_sales ──────────────────────────────

    public function test_pnl_revenue_matches_completed_sales(): void
    {
        $s1 = $this->insertSale(1000.00);
        $s2 = $this->insertSale(500.00);

        $this->insertSaleItem($s1, 2, 500, 300, 1000);
        $this->insertSaleItem($s2, 1, 500, 200, 500);

        // Voided sale must NOT be counted
        $void = $this->insertSale(999.00, 'voided');
        $this->insertSaleItem($void, 1, 999, 500, 999);

        [$from, $to] = $this->period();
        $data = $this->controller()->computeIncomeStatement($this->tenantId, null, $from, $to);

        $this->assertEquals(1500.00, $data['revenue']);
    }

    // ── test_cogs_matches_sale_item_cost_prices ───────────────────────────────

    public function test_cogs_matches_sale_item_cost_prices(): void
    {
        // 2 × 300 = 600, 1 × 200 = 200 → COGS = 800
        $s1 = $this->insertSale(1000.00);
        $s2 = $this->insertSale(500.00);
        $this->insertSaleItem($s1, 2, 500, 300, 1000);
        $this->insertSaleItem($s2, 1, 500, 200, 500);

        [$from, $to] = $this->period();
        $data = $this->controller()->computeIncomeStatement($this->tenantId, null, $from, $to);

        $this->assertEquals(800.00, $data['cogs']);
        $this->assertEquals(700.00, $data['grossProfit']); // 1500 - 800
    }

    // ── test_vat_collected_matches_sale_tax ───────────────────────────────────

    public function test_vat_collected_matches_sale_tax(): void
    {
        // tax_rate = 18%, subtotal = 500 → VAT = 90
        $saleId = $this->insertSale(590.00);
        $this->insertSaleItem($saleId, 1, 500, 100, 500.00);

        [$from, $to] = $this->period();
        $data = $this->controller()->computeVat($this->tenantId, null, $from, $to);

        // product has tax_rate 18 by default from setUp, subtotal 500 → 500*18/100 = 90
        $this->assertEquals(90.00, round($data['vatCollected'], 2));
    }

    // ── test_balance_sheet_assets_equal_liabilities_plus_equity ──────────────

    public function test_balance_sheet_assets_equal_liabilities_plus_equity(): void
    {
        // Stock: 10 units × cost 100 = 1 000
        DB::table('stock_balances')->insert([
            'product_id'          => $this->productId,
            'warehouse_id'        => $this->warehouseId,
            'quantity_available'  => 10,
            'quantity_reserved'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Supplier payable = 300
        DB::table('suppliers')->insert([
            'name'       => 'Acme Ltd',
            'tenant_id'  => $this->tenantId,
            'balance'    => 300.00,
            'status'     => 'active',
            'is_active'  => 1,
            'code'       => 'SUP-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$from, $to] = $this->period();
        $data = $this->controller()->computeBalanceSheet($this->tenantId, null, $from, $to);

        $this->assertEqualsWithDelta(
            $data['totalAssets'],
            $data['totalLiabilities'] + $data['equity'],
            0.01,
            'Balance sheet must balance: Assets = Liabilities + Equity'
        );
    }

    // ── test_reports_are_tenant_scoped ────────────────────────────────────────

    public function test_reports_are_tenant_scoped(): void
    {
        $otherTenantId = DB::table('tenants')->insertGetId([
            'name'       => 'Other Co',
            'slug'       => 'other-co',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherWarehouseId = DB::table('warehouses')->insertGetId([
            'name'       => 'Other WH',
            'code'       => 'WH-99',
            'is_default' => 0,
            'is_active'  => 1,
            'tenant_id'  => $otherTenantId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUserId = User::factory()->create(['tenant_id' => $otherTenantId])->id;

        $otherProductId = DB::table('products')->insertGetId([
            'sku'          => 'SKU-OTHER-001',
            'name'         => 'Other Product',
            'cost_price'   => 50000.00,
            'selling_price'=> 99999.00,
            'tax_rate'     => 18.00,
            'is_active'    => 1,
            'tenant_id'    => $otherTenantId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Other tenant's sale (grand_total = 99 999) must NOT appear
        $otherSaleId = DB::table('sales')->insertGetId([
            'tenant_id'      => $otherTenantId,
            'cashier_id'     => $otherUserId,
            'warehouse_id'   => $otherWarehouseId,
            'status'         => 'completed',
            'grand_total'    => 99999.00,
            'total'          => 99999.00,
            'payment_method' => 'cash',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        DB::table('sale_items')->insert([
            'sale_id'    => $otherSaleId,
            'product_id' => $otherProductId,
            'qty'        => 1,
            'unit_price' => 99999,
            'cost_price' => 50000,
            'discount'   => 0,
            'subtotal'   => 99999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Our tenant's sale
        $ourSaleId = $this->insertSale(200.00);
        $this->insertSaleItem($ourSaleId, 1, 200, 100, 200);

        [$from, $to] = $this->period();
        $data = $this->controller()->computeIncomeStatement($this->tenantId, null, $from, $to);

        $this->assertEquals(200.00, $data['revenue'],
            'Revenue must only include the current tenant\'s sales.');
    }

    // ── test_no_aggregate_tables_used_in_computation ──────────────────────────

    public function test_no_aggregate_tables_used_in_computation(): void
    {
        DB::enableQueryLog();

        [$from, $to] = $this->period();
        $ctrl = $this->controller();

        $ctrl->computeIncomeStatement($this->tenantId, null, $from, $to);
        $ctrl->computeCashFlow($this->tenantId, null, $from, $to);
        $ctrl->computeBalanceSheet($this->tenantId, null, $from, $to);
        $ctrl->computeVat($this->tenantId, null, $from, $to);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // These tables must never appear — all computation is from source tables
        $forbiddenTables = [
            'financial_summaries',
            'period_reports',
            'financial_aggregates',
            'report_caches',
        ];

        $allSql = implode(' ', array_column($queries, 'query'));

        foreach ($forbiddenTables as $table) {
            $this->assertStringNotContainsStringIgnoringCase(
                $table,
                $allSql,
                "Aggregate table '{$table}' must not be used."
            );
        }

        // Source tables must be present in the queries
        $this->assertStringContainsStringIgnoringCase('sales', $allSql);
        $this->assertStringContainsStringIgnoringCase('expenses', $allSql);
    }
}
