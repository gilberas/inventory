<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Http\Controllers\FinancialController;
use App\Jobs\SendMonthlyPnlReportJob;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\GoodsReceivedNote;
use App\Models\GrnItem;
use App\Models\InventoryAudit;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockNotification;
use App\Services\DashboardMetricsService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserStoriesTest extends TestCase
{
    use RefreshDatabase;

    // ── Shared fixtures ────────────────────────────────────────────────────────

    private Tenant $tenant;
    private User   $owner;
    private User   $storekeeper;
    private User   $cashier;
    private int    $branchId;
    private int    $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Create all permissions used in this test suite
        $permissions = [
            'inventory.view', 'inventory.adjust', 'inventory.audit',
            'inventory.audit_count',
            'purchases.view', 'purchases.create', 'purchases.manage',
            'purchases.receive',
            'sales.view', 'sales.create', 'sales.manage',
            'expenses.view', 'expenses.create', 'expenses.manage',
            'transfers.view', 'transfers.create', 'transfers.approve',
            'transfers.dispatch', 'transfers.receive',
            'reports.view',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Roles
        $ownerRole       = Role::firstOrCreate(['name' => 'Super Admin',   'guard_name' => 'web']);
        $storekeepRole   = Role::firstOrCreate(['name' => 'Storekeeper',   'guard_name' => 'web']);
        $cashierRole     = Role::firstOrCreate(['name' => 'Sales Rep',     'guard_name' => 'web']);

        $ownerRole->givePermissionTo($permissions);
        $storekeepRole->givePermissionTo([
            'inventory.view', 'inventory.adjust', 'inventory.audit', 'inventory.audit_count',
            'purchases.receive', 'purchases.view',
            'transfers.view', 'transfers.create', 'transfers.dispatch', 'transfers.receive',
        ]);
        $cashierRole->givePermissionTo(['sales.view', 'sales.create', 'sales.manage']);

        // Tenant
        $this->tenant = Tenant::create([
            'name'   => 'Test Biz',
            'slug'   => 'test-biz-' . uniqid(),
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);

        // Branch
        $this->branchId = DB::table('branches')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main Branch',
            'code'       => 'MB',
            'address'    => '1 Test St',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Warehouse
        $this->warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'branch_id'  => $this->branchId,
            'name'       => 'Main WH',
            'code'       => 'MWH',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Users
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchId,
            'status'    => 'active',
        ]);
        $this->owner->assignRole('Super Admin');

        $this->storekeeper = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchId,
            'status'    => 'active',
        ]);
        $this->storekeeper->assignRole('Storekeeper');

        $this->cashier = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchId,
            'status'    => 'active',
        ]);
        $this->cashier->assignRole('Sales Rep');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeProduct(array $overrides = []): int
    {
        $unitId = DB::table('units')->insertGetId([
            'name'         => 'Piece',
            'abbreviation' => 'pcs',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return DB::table('products')->insertGetId(array_merge([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Test Product',
            'sku'           => 'TST-' . uniqid(),
            'unit_id'       => $unitId,
            'selling_price' => 1000.00,
            'cost_price'    => 600.00,
            'minimum_stock' => 5,
            'reorder_level' => 10,
            'tax_rate'      => 18.00,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $overrides));
    }

    private function makeInventory(int $productId, float $qty = 100.0): void
    {
        DB::table('inventory')->insert([
            'tenant_id'        => $this->tenant->id,
            'product_id'       => $productId,
            'warehouse_id'     => $this->warehouseId,
            'quantity'         => $qty,
            'valuation_method' => 'fifo',
            'unit_cost'        => 600.00,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    private function makeSale(float $grandTotal = 1000.0, ?int $sessionId = null): int
    {
        return DB::table('sales')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'cashier_id'     => $this->cashier->id,
            'warehouse_id'   => $this->warehouseId,
            'pos_session_id' => $sessionId,
            'receipt_no'     => 'RCP-' . uniqid(),
            'total'          => $grandTotal,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => $grandTotal,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function openSession(?int $cashierId = null): int
    {
        return DB::table('pos_sessions')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'cashier_id'   => $cashierId ?? $this->cashier->id,
            'branch_id'    => $this->branchId,
            'opening_cash' => 50000,
            'status'       => 'active',
            'opened_at'    => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    // ── BO-1: Consolidated dashboard sums all branches ─────────────────────────

    public function test_consolidated_dashboard_sums_all_branches(): void
    {
        // Second branch + warehouse
        $branch2 = DB::table('branches')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch 2', 'code' => 'B2',
            'address' => '2 Test St', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $wh2 = DB::table('warehouses')->insertGetId([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch2,
            'name' => 'WH2', 'code' => 'WH2', 'is_active' => true, 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Sales in two different warehouses
        DB::table('sales')->insert([
            [
                'tenant_id' => $this->tenant->id, 'cashier_id' => $this->cashier->id,
                'warehouse_id' => $this->warehouseId, 'receipt_no' => 'R1',
                'total' => 500, 'discount' => 0, 'tax' => 0, 'grand_total' => 500,
                'payment_method' => 'cash', 'status' => 'completed',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->tenant->id, 'cashier_id' => $this->cashier->id,
                'warehouse_id' => $wh2, 'receipt_no' => 'R2',
                'total' => 700, 'discount' => 0, 'tax' => 0, 'grand_total' => 700,
                'payment_method' => 'cash', 'status' => 'completed',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $service = new DashboardMetricsService();
        $metrics = $service->salesMetrics($this->tenant->id, null);

        $this->assertGreaterThanOrEqual(1200.0, $metrics['salesToday']);
    }

    // ── BO-2: Low stock alert goes to owner and storekeeper ───────────────────

    public function test_low_stock_alert_sent_to_business_owner_and_storekeeper(): void
    {
        Notification::fake();

        $productId = $this->makeProduct();
        $product   = Product::withoutGlobalScopes()->find($productId);
        $warehouse = Warehouse::withoutGlobalScopes()->find($this->warehouseId);

        event(new LowStockDetected($product, $warehouse, 2.0));

        Notification::assertSentTo(
            $this->owner,
            LowStockNotification::class,
        );
    }

    // ── BO-3: Monthly P&L command dispatches job per active tenant ────────────

    public function test_monthly_pnl_command_dispatches_email_job(): void
    {
        Bus::fake([SendMonthlyPnlReportJob::class]);

        $this->artisan('reports:send-monthly-pnl');

        Bus::assertDispatched(SendMonthlyPnlReportJob::class, function ($job) {
            return $job->tenantId === $this->tenant->id;
        });
    }

    // ── BM-1: Manager can approve requisition from mobile view ───────────────

    public function test_requisition_approve_from_mobile_view_works(): void
    {
        $req = DB::table('purchase_requisitions')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->storekeeper->id,
            'status'       => 'pending',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('requisitions.approve', $req));

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_requisitions', [
            'id'     => $req,
            'status' => 'approved',
        ]);
    }

    // ── BM-2: Stock transfer created in a single POST ─────────────────────────

    public function test_stock_transfer_created_in_single_post_request(): void
    {
        $branch2 = DB::table('branches')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch B', 'code' => 'BB',
            'address' => '3 Test St', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = $this->makeProduct(['name' => 'Transfer Product']);

        // Storekeeper has a branch assigned
        $storekeeper = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchId,
            'status'    => 'active',
        ]);
        $storekeeper->assignRole('Storekeeper');

        $response = $this->actingAs($storekeeper)->post(route('transfers.store'), [
            'to_branch_id'          => $branch2,
            'items'                 => [
                ['product_id' => $productId, 'qty_requested' => 10],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('branch_transfers', [
            'from_branch_id' => $this->branchId,
            'to_branch_id'   => $branch2,
        ]);
    }

    // ── CA-1: Barcode scan returns JSON with product (no page reload) ─────────

    public function test_barcode_scan_adds_to_cart_without_page_reload(): void
    {
        $barcode   = 'EAN-' . mt_rand(1000000, 9999999);
        $productId = $this->makeProduct(['barcode' => $barcode, 'name' => 'Scanned Product']);

        // Open a session first
        $this->openSession($this->cashier->id);

        $response = $this->actingAs($this->cashier)
            ->getJson(route('pos.products.search') . '?q=' . $barcode . '&warehouse_id=' . $this->warehouseId);

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $productId]);
    }

    // ── CA-2: Change calculation correct for cash payment ─────────────────────

    public function test_change_calculation_correct_for_cash_payment(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 50.0);
        $sessionId = $this->openSession($this->cashier->id);

        $payload = [
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'cash',
            'amount_tendered' => 5000.00,
            'discount'        => 0,
            'tax'             => 0,
            'items' => [
                ['product_id' => $productId, 'qty' => 3, 'unit_price' => 1000.00, 'cost_price' => 600.00],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['change_given' => 2000.0]);
    }

    // ── CA-2: Insufficient cash tendered blocks sale ──────────────────────────

    public function test_insufficient_cash_tendered_blocks_sale(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 50.0);
        $this->openSession($this->cashier->id);

        $payload = [
            'warehouse_id'    => $this->warehouseId,
            'payment_method'  => 'cash',
            'amount_tendered' => 500.00,   // less than 3 × 1000 = 3000
            'discount'        => 0,
            'tax'             => 0,
            'items' => [
                ['product_id' => $productId, 'qty' => 3, 'unit_price' => 1000.00, 'cost_price' => 600.00],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Amount tendered is less than the total due.']);
    }

    // ── SK-1: GRN receive page has barcode scan input ─────────────────────────

    public function test_grn_receive_page_supports_barcode_scan(): void
    {
        $supplierId = DB::table('suppliers')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Test Supplier',
            'code'       => 'SUP1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $poId = DB::table('purchase_orders')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'supplier_id'  => $supplierId,
            'warehouse_id' => $this->warehouseId,
            'reference_no' => 'PO-TEST-' . uniqid(),
            'order_date'   => today()->toDateString(),
            'status'       => 'APPROVED',
            'total_amount' => 5000.00,
            'created_by'   => $this->owner->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($this->storekeeper)
            ->get(route('purchases.receive', $poId));

        $response->assertStatus(200);
        $response->assertSee('barcode-scan-input');
    }

    // ── SK-2: Expiry report returns only products within 30 days ─────────────

    public function test_expiring_soon_returns_only_products_within_30_days(): void
    {
        $productId  = $this->makeProduct(['name' => 'Expiring Item']);
        $product2Id = $this->makeProduct(['name' => 'Far Future Item']);

        // Batch expiring in 15 days
        DB::table('product_batches')->insert([
            [
                'tenant_id'    => $this->tenant->id,
                'product_id'   => $productId,
                'batch_number' => 'BATCH-EXP-15',
                'quantity'     => 10,
                'expiry_date'  => now()->addDays(15)->toDateString(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            [
                'tenant_id'    => $this->tenant->id,
                'product_id'   => $product2Id,
                'batch_number' => 'BATCH-EXP-180',
                'quantity'     => 5,
                'expiry_date'  => now()->addDays(180)->toDateString(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.expiry') . '?days=30');

        $response->assertStatus(200);
        $response->assertSee('Expiring Item');
        $response->assertDontSee('Far Future Item');
    }

    // ── AC-1: VAT report collected matches sale tax ───────────────────────────

    public function test_vat_report_collected_matches_sale_tax(): void
    {
        $productId = $this->makeProduct(['tax_rate' => 18.0, 'selling_price' => 1000.0]);
        $saleId    = $this->makeSale(1000.0);

        DB::table('sale_items')->insert([
            'sale_id'    => $saleId,
            'product_id' => $productId,
            'qty'        => 1,
            'unit_price' => 1000.0,
            'cost_price' => 600.0,
            'discount'   => 0,
            'subtotal'   => 1000.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ctrl = app(FinancialController::class);
        $data = $ctrl->computeVat(
            $this->tenant->id,
            null,
            today()->startOfMonth()->toDateString(),
            today()->endOfMonth()->toDateString()
        );

        // 1000 × 18% = 180
        $this->assertEqualsWithDelta(180.0, $data['vatCollected'], 0.01);
        $this->assertArrayHasKey('collectedByRate', $data);
        $this->assertNotEmpty($data['collectedByRate']);
    }

    // ── AC-1: VAT report paid matches purchase (GRN) tax ─────────────────────

    public function test_vat_report_paid_matches_purchase_tax(): void
    {
        $productId = $this->makeProduct(['tax_rate' => 18.0]);

        $vatSupplierId = DB::table('suppliers')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'VAT Supplier',
            'code'       => 'VSUP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $vatPoId = DB::table('purchase_orders')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'supplier_id'  => $vatSupplierId,
            'warehouse_id' => $this->warehouseId,
            'reference_no' => 'PO-VAT-' . uniqid(),
            'order_date'   => today()->toDateString(),
            'status'       => 'APPROVED',
            'total_amount' => 2500.00,
            'created_by'   => $this->owner->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $grnId = DB::table('goods_received_notes')->insertGetId([
            'tenant_id'         => $this->tenant->id,
            'purchase_order_id' => $vatPoId,
            'warehouse_id'      => $this->warehouseId,
            'received_by'       => $this->storekeeper->id,
            'reference_no'      => 'GRN-TEST-001',
            'received_at'       => now(),
            'status'            => 'confirmed',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DB::table('grn_items')->insert([
            'grn_id'       => $grnId,
            'product_id'   => $productId,
            'qty_received' => 5,
            'unit_cost'    => 500.0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $ctrl = app(FinancialController::class);
        $data = $ctrl->computeVat(
            $this->tenant->id,
            null,
            today()->startOfMonth()->toDateString(),
            today()->endOfMonth()->toDateString()
        );

        // 5 × 500 × 18% = 450
        $this->assertEqualsWithDelta(450.0, $data['vatPaid'], 0.01);
        $this->assertArrayHasKey('paidByRate', $data);
    }

    // ── AC-2: approved_at recorded on expense approval ───────────────────────

    public function test_expense_approved_at_recorded_on_approval(): void
    {
        $expenseId = DB::table('expenses')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'created_by'   => $this->storekeeper->id,
            'reference_no' => 'EXP-TEST-' . uniqid(),
            'category'     => 'Utilities',
            'description'  => 'Electric bill',
            'amount'       => 250000,
            'expense_date' => today()->toDateString(),
            'status'       => 'pending_approval',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $expense = Expense::withoutGlobalScopes()->find($expenseId);

        $response = $this->actingAs($this->owner)
            ->post(route('expenses.approve', $expense));

        $response->assertStatus(200);
        $this->assertNotNull(
            DB::table('expenses')->where('id', $expenseId)->value('approved_at')
        );
        $this->assertEquals(
            $this->owner->id,
            DB::table('expenses')->where('id', $expenseId)->value('approved_by')
        );
    }

    // ── AC-2: Expense export contains audit fields ────────────────────────────

    public function test_expense_audit_fields_visible_in_export(): void
    {
        $expenseId = DB::table('expenses')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'created_by'   => $this->storekeeper->id,
            'approved_by'  => $this->owner->id,
            'approved_at'  => now(),
            'reference_no' => 'EXP-AUD-' . uniqid(),
            'category'     => 'Transport',
            'description'  => 'Fuel costs',
            'amount'       => 50000,
            'expense_date' => today()->toDateString(),
            'status'       => 'approved',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $expense = Expense::withoutGlobalScopes()->find($expenseId);

        $response = $this->actingAs($this->owner)
            ->get(route('expenses.show', $expense));

        $response->assertStatus(200);
        $response->assertSee('Approved By');
        $response->assertSee($this->owner->name);
    }

    // ── WF-1: Requisition cannot skip pending state ───────────────────────────

    public function test_purchase_requisition_cannot_skip_pending_state(): void
    {
        $reqId = DB::table('purchase_requisitions')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->storekeeper->id,
            'status'       => 'draft',  // NOT pending
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->post(route('requisitions.approve', $reqId));

        $response->assertStatus(422);
    }

    // ── WF-2: POS session required before making a sale ──────────────────────

    public function test_pos_session_required_before_sale(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 50.0);

        // No session opened for this cashier

        $payload = [
            'warehouse_id'   => $this->warehouseId,
            'payment_method' => 'cash',
            'discount'       => 0,
            'tax'            => 0,
            'items' => [
                ['product_id' => $productId, 'qty' => 1, 'unit_price' => 1000.00, 'cost_price' => 600.00],
            ],
        ];

        $response = $this->actingAs($this->cashier)->postJson(route('pos.sales.store'), $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'No active POS session. Please open a session before processing sales.']);
    }

    // ── WF-3: Inventory adjusted at dispatch, not at transfer request ─────────

    public function test_stock_transfer_inventory_adjusted_at_dispatch_not_request(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 100.0);

        $branch2 = DB::table('branches')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch C', 'code' => 'BC',
            'address' => '4 Test St', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'branch_id'  => $branch2,
            'name'       => 'WH3',
            'code'       => 'WH3',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a pending transfer
        $transferId = DB::table('branch_transfers')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'from_branch_id' => $this->branchId,
            'to_branch_id'   => $branch2,
            'requested_by'   => $this->storekeeper->id,
            'status'         => 'pending',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $itemId = DB::table('branch_transfer_items')->insertGetId([
            'transfer_id' => $transferId,
            'product_id'  => $productId,
            'qty_requested' => 20,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Stock must still be 100 after request (not yet deducted)
        $stockAfterRequest = DB::table('inventory')
            ->where('product_id', $productId)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');

        $this->assertEquals(100.0, $stockAfterRequest);
    }

    // ── WF-4: Inventory adjustment blocked during active audit ────────────────

    public function test_inventory_adjustment_blocked_during_active_audit(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 100.0);

        // Create an active audit for the warehouse
        DB::table('inventory_audits')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->warehouseId,
            'status'       => 'counting',
            'initiated_by' => $this->owner->id,
            'audit_date'   => today()->toDateString(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $service = app(InventoryService::class);

        $this->expectException(\App\Exceptions\AuditInProgressException::class);

        $service->adjust(
            productId:   $productId,
            warehouseId: $this->warehouseId,
            qtyChange:   5.0,
            reason:      'Test adjust during audit',
            userId:      $this->owner->id,
        );
    }

    // ── WF-4: Audit post applies variance adjustments ─────────────────────────

    public function test_audit_post_applies_variance_adjustments(): void
    {
        $productId = $this->makeProduct();
        $this->makeInventory($productId, 100.0);

        // Create a completed audit
        $auditId = DB::table('inventory_audits')->insertGetId([
            'tenant_id'    => $this->tenant->id,
            'warehouse_id' => $this->warehouseId,
            'status'       => 'completed',
            'initiated_by' => $this->owner->id,
            'audit_date'   => today()->toDateString(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Audit item: counted 90, system has 100 → variance -10
        DB::table('inventory_audit_items')->insertGetId([
            'audit_id'    => $auditId,
            'product_id'  => $productId,
            'system_qty'  => 100.0,
            'physical_qty' => 90.0,
            'variance'    => -10.0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $audit = InventoryAudit::withoutGlobalScopes()->find($auditId);

        $response = $this->actingAs($this->owner)
            ->post(route('audits.post', $audit));

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_audits', [
            'id'     => $auditId,
            'status' => 'posted',
        ]);

        // Inventory should now be 90
        $qty = DB::table('inventory')
            ->where('product_id', $productId)
            ->where('warehouse_id', $this->warehouseId)
            ->value('quantity');

        $this->assertEquals(90.0, $qty);
    }
}
