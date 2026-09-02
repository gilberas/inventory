<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User   $ownerA;
    private User   $ownerB;
    private int    $whA1;  // Tenant A — warehouse / branch 1
    private int    $whA2;  // Tenant A — warehouse / branch 2
    private int    $whB1;  // Tenant B — warehouse / branch 1

    protected function setUp(): void
    {
        parent::setUp();

        // Roles required by DashboardController::canSelectBranch check
        Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);

        // ── Tenant A ──────────────────────────────────────────
        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active']);

        $this->whA1 = DB::table('warehouses')->insertGetId([
            'name' => 'A Branch 1', 'code' => 'WH-A1',
            'tenant_id' => $this->tenantA->id, 'is_active' => true,
            'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->whA2 = DB::table('warehouses')->insertGetId([
            'name' => 'A Branch 2', 'code' => 'WH-A2',
            'tenant_id' => $this->tenantA->id, 'is_active' => true,
            'is_default' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->ownerA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->whA1,
            'status'    => 'active',
        ]);
        $this->ownerA->assignRole('business_owner');

        // ── Tenant B ──────────────────────────────────────────
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active']);

        $this->whB1 = DB::table('warehouses')->insertGetId([
            'name' => 'B Branch 1', 'code' => 'WH-B1',
            'tenant_id' => $this->tenantB->id, 'is_active' => true,
            'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->ownerB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'branch_id' => $this->whB1,
            'status'    => 'active',
        ]);
        $this->ownerB->assignRole('business_owner');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertProduct(int $tenantId, bool $active = true): int
    {
        static $seq = 0;
        $seq++;
        return DB::table('products')->insertGetId([
            'tenant_id'     => $tenantId,
            'sku'           => "SKU-{$seq}",
            'name'          => "Product {$seq}",
            'cost_price'    => 50.00,
            'selling_price' => 100.00,
            'minimum_stock' => 5,
            'is_active'     => $active,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function insertCustomer(int $tenantId): int
    {
        static $cseq = 0;
        $cseq++;
        return DB::table('customers')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => "Customer {$cseq}",
            'code'       => "CUS-{$cseq}",
            'is_active'  => true,
            'balance'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertDeliveredSale(int $tenantId, int $warehouseId, float $amount, ?string $date = null): int
    {
        static $sseq = 0;
        $sseq++;
        $cashierId = ($tenantId === $this->tenantA->id) ? $this->ownerA->id : $this->ownerB->id;
        $createdAt = $date ? now()->parse($date) : now();
        return DB::table('sales')->insertGetId([
            'tenant_id'      => $tenantId,
            'cashier_id'     => $cashierId,
            'warehouse_id'   => $warehouseId,
            'receipt_no'     => "RCP-T{$sseq}",
            'total'          => $amount,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => $amount,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'created_at'     => $createdAt,
            'updated_at'     => $createdAt,
        ]);
    }

    private function insertApprovedExpense(int $tenantId, int $warehouseId, float $amount): void
    {
        static $eseq = 0;
        $eseq++;
        DB::table('expenses')->insert([
            'tenant_id'    => $tenantId,
            'warehouse_id' => $warehouseId,
            'created_by'   => $this->ownerA->id,
            'reference_no' => "EXP-T{$eseq}",
            'category'     => 'Operations',
            'amount'       => $amount,
            'status'       => 'approved',
            'expense_date' => today()->toDateString(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** Dashboard must only show data belonging to the authenticated user's tenant */
    public function test_dashboard_only_shows_current_tenant_data(): void
    {
        // Tenant A has a sale worth 500
        $this->insertDeliveredSale($this->tenantA->id, $this->whA1, 500.00);

        // Tenant B has a sale worth 9999 — must NOT appear on Tenant A's dashboard
        $this->insertDeliveredSale($this->tenantB->id, $this->whB1, 9999.00);

        $response = $this->actingAs($this->ownerA)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('sales', function (array $sales) {
            // Tenant A sees only its 500, never Tenant B's 9999
            return $sales['salesToday'] == 500.00
                && $sales['salesThisMonth'] == 500.00;
        });
    }

    /** A branch_manager must only see metrics for their assigned warehouse */
    public function test_branch_manager_sees_only_own_branch(): void
    {
        Role::firstOrCreate(['name' => 'branch_manager', 'guard_name' => 'web']);

        $manager = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->whA1,
            'status'    => 'active',
        ]);
        $manager->assignRole('branch_manager');

        // Sale in manager's branch
        $this->insertDeliveredSale($this->tenantA->id, $this->whA1, 300.00);
        // Sale in the OTHER branch — manager must NOT see this
        $this->insertDeliveredSale($this->tenantA->id, $this->whA2, 700.00);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('sales', function (array $sales) {
            return $sales['salesToday'] == 300.00;
        });
        // Branch selector dropdown is hidden for non-owners
        $response->assertViewHas('can_select_branch', false);
    }

    /** Inventory counts must match the database state for that tenant */
    public function test_inventory_metrics_match_database(): void
    {
        $p1 = $this->insertProduct($this->tenantA->id); // active
        $p2 = $this->insertProduct($this->tenantA->id); // active
        $this->insertProduct($this->tenantA->id, false); // inactive — must NOT count

        // p1 has stock 3 (below minimum_stock 5 → low stock)
        DB::table('stock_balances')->insert([
            'tenant_id'          => $this->tenantA->id,
            'product_id'         => $p1,
            'warehouse_id'       => $this->whA1,
            'quantity_available' => 3,
            'quantity_reserved'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // p2 has stock 0 → out of stock
        DB::table('stock_balances')->insert([
            'tenant_id'          => $this->tenantA->id,
            'product_id'         => $p2,
            'warehouse_id'       => $this->whA1,
            'quantity_available' => 0,
            'quantity_reserved'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('inventory', function (array $inv) {
            return $inv['totalProducts'] === 2  // only active products
                && $inv['lowStockCount'] === 1
                && $inv['outOfStockCount'] === 1;
        });
    }

    /** Revenue, expenses, and gross profit must compute correctly */
    public function test_financial_metrics_correct(): void
    {
        // Revenue: 1 200.00
        $soId = $this->insertDeliveredSale($this->tenantA->id, $this->whA1, 1200.00);

        // COGS via sale item: qty=2, cost_price=50 → COGS=100
        $productId = $this->insertProduct($this->tenantA->id);
        DB::table('sale_items')->insert([
            'sale_id'    => $soId,
            'product_id' => $productId,
            'qty'        => 2,
            'unit_price' => 600.00,
            'cost_price' => 50.00,
            'discount'   => 0,
            'subtotal'   => 1200.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Approved expense: 200
        $this->insertApprovedExpense($this->tenantA->id, $this->whA1, 200.00);

        // Outstanding receivable: customer with balance 400
        DB::table('customers')->insert([
            'tenant_id'  => $this->tenantA->id,
            'name'       => 'Debtor',
            'code'       => 'CUS-DBT',
            'is_active'  => true,
            'balance'    => 400.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->ownerA)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('financial', function (array $fin) {
            return $fin['revenueThisMonth']      == 1200.00
                && $fin['expensesThisMonth']     == 200.00
                && $fin['grossProfitThisMonth']  == 1100.00 // 1200 - (2*50) = 1100
                && $fin['outstandingReceivables'] == 400.00;
        });
    }

    /** Consolidated view must sum metrics across all branches of the tenant */
    public function test_consolidated_view_sums_all_branches(): void
    {
        // Branch 1: 400, Branch 2: 600 → consolidated = 1000
        $this->insertDeliveredSale($this->tenantA->id, $this->whA1, 400.00);
        $this->insertDeliveredSale($this->tenantA->id, $this->whA2, 600.00);

        // Hit dashboard without branch_id → consolidated
        $response = $this->actingAs($this->ownerA)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('sales', function (array $sales) {
            return $sales['salesToday'] == 1000.00;
        });
        $response->assertViewHas('can_select_branch', true);
        $response->assertViewHas('selected_warehouse', null);
    }

    /** Logging out must invalidate the session and redirect to login */
    public function test_session_invalidated_on_logout(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
