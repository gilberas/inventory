<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the seeder so all roles/permissions exist for every test
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    }

    // ── Seeder correctness ────────────────────────────────────────────────────

    public function test_all_6_roles_exist_in_database(): void
    {
        $expected = ['super_admin', 'business_owner', 'branch_manager', 'cashier', 'storekeeper', 'accountant'];
        foreach ($expected as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName, 'guard_name' => 'web']);
        }
        $this->assertSame(6, Role::count());
    }

    public function test_all_permissions_exist_in_database(): void
    {
        $this->assertGreaterThanOrEqual(49, Permission::count());
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $role = Role::findByName('super_admin');
        $this->assertSame(Permission::count(), $role->permissions()->count());
    }

    public function test_business_owner_does_not_have_platform_manage(): void
    {
        $role = Role::findByName('business_owner');
        $this->assertFalse($role->hasPermissionTo('platform.manage'));
    }

    public function test_cashier_has_correct_permissions(): void
    {
        $role = Role::findByName('cashier');
        $this->assertTrue($role->hasPermissionTo('sales.process'));
        $this->assertTrue($role->hasPermissionTo('sales.view'));
        $this->assertTrue($role->hasPermissionTo('sales.create'));
        $this->assertTrue($role->hasPermissionTo('products.view'));
        $this->assertTrue($role->hasPermissionTo('customers.manage_own'));
        $this->assertFalse($role->hasPermissionTo('inventory.adjust'));
        $this->assertFalse($role->hasPermissionTo('reports.financial'));
    }

    public function test_storekeeper_has_correct_permissions(): void
    {
        $role = Role::findByName('storekeeper');
        $this->assertTrue($role->hasPermissionTo('inventory.view'));
        $this->assertTrue($role->hasPermissionTo('inventory.adjust'));
        $this->assertTrue($role->hasPermissionTo('purchases.receive'));
        $this->assertTrue($role->hasPermissionTo('transfers.view'));
        $this->assertFalse($role->hasPermissionTo('sales.process'));
        $this->assertFalse($role->hasPermissionTo('reports.financial'));
    }

    public function test_accountant_has_correct_permissions(): void
    {
        $role = Role::findByName('accountant');
        $this->assertTrue($role->hasPermissionTo('reports.financial'));
        $this->assertTrue($role->hasPermissionTo('reports.vat'));
        $this->assertTrue($role->hasPermissionTo('reports.view'));
        $this->assertTrue($role->hasPermissionTo('expenses.manage'));
        $this->assertFalse($role->hasPermissionTo('sales.process'));
        $this->assertFalse($role->hasPermissionTo('inventory.adjust'));
    }

    // ── Dashboard routing ─────────────────────────────────────────────────────

    private function makeUser(string $role, ?int $tenantId = null, ?int $branchId = null): User
    {
        $user = User::factory()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'status'    => 'active',
        ]);
        $user->syncRoles([$role]);
        return $user;
    }

    private function makeTenant(string $slug = 'test'): Tenant
    {
        return Tenant::create([
            'name'   => 'Test Co',
            'slug'   => $slug,
            'status' => 'active',
            'config' => ['plan' => 'professional'],
        ]);
    }

    private function makeWarehouse(int $tenantId): int
    {
        return DB::table('warehouses')->insertGetId([
            'tenant_id'  => $tenantId,
            'name'       => 'Main WH',
            'code'       => 'WH-' . uniqid(),
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_super_admin_dashboard_returns_200(): void
    {
        $admin = $this->makeUser('super_admin', null, null);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.super_admin');
    }

    public function test_super_admin_dashboard_shows_tenant_count(): void
    {
        $this->makeTenant('demo-a');
        $this->makeTenant('demo-b');
        $admin = $this->makeUser('super_admin', null, null);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('total_tenants', 2);
    }

    public function test_business_owner_dashboard_shows_all_branches(): void
    {
        $tenant = $this->makeTenant('biz-owner');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $owner = $this->makeUser('business_owner', $tenant->id, null);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.business_owner');
        $response->assertViewHas('can_select_branch', true);
        $response->assertViewHas('selected_warehouse', null); // consolidated by default
    }

    public function test_branch_manager_dashboard_scoped_to_own_branch(): void
    {
        $tenant = $this->makeTenant('bm-test');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $manager = $this->makeUser('branch_manager', $tenant->id, $warehouseId);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.branch_manager');
        $response->assertViewHas('can_select_branch', false);
        $response->assertViewHas('selected_warehouse', $warehouseId);
    }

    public function test_cashier_dashboard_shows_only_own_sales(): void
    {
        $tenant = $this->makeTenant('cashier-test');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $cashier = $this->makeUser('cashier', $tenant->id, $warehouseId);

        $response = $this->actingAs($cashier)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.cashier');
        $response->assertViewHas('my_sales_today');
        $response->assertViewHas('my_transactions_today');
    }

    public function test_storekeeper_dashboard_shows_stock_alerts(): void
    {
        $tenant = $this->makeTenant('sk-test');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $storekeeper = $this->makeUser('storekeeper', $tenant->id, $warehouseId);

        $response = $this->actingAs($storekeeper)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.storekeeper');
        $response->assertViewHas('low_stock_products');
        $response->assertViewHas('pending_grns');
    }

    public function test_accountant_dashboard_shows_financial_metrics(): void
    {
        $tenant = $this->makeTenant('acc-test');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $accountant = $this->makeUser('accountant', $tenant->id, $warehouseId);

        $response = $this->actingAs($accountant)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.accountant');
        $response->assertViewHas('vat_collected');
        $response->assertViewHas('vat_paid');
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_cashier_cannot_access_financial_reports(): void
    {
        $tenant = $this->makeTenant('ac-cashier');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $cashier = $this->makeUser('cashier', $tenant->id, $warehouseId);

        $response = $this->actingAs($cashier)->get(route('reports.financial.vat'));

        // 403 from permission middleware
        $response->assertStatus(403);
    }

    public function test_cashier_cannot_access_inventory_adjustment(): void
    {
        $tenant = $this->makeTenant('ac-inv');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $cashier = $this->makeUser('cashier', $tenant->id, $warehouseId);

        $response = $this->actingAs($cashier)->get(route('inventory.adjust'));

        $response->assertStatus(403);
    }

    public function test_storekeeper_cannot_access_financial_reports(): void
    {
        $tenant = $this->makeTenant('sk-fin');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $storekeeper = $this->makeUser('storekeeper', $tenant->id, $warehouseId);

        $response = $this->actingAs($storekeeper)->get(route('reports.financial.vat'));

        $response->assertStatus(403);
    }

    public function test_accountant_cannot_process_sale(): void
    {
        $tenant = $this->makeTenant('acc-sale');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $accountant = $this->makeUser('accountant', $tenant->id, $warehouseId);

        $response = $this->actingAs($accountant)->postJson(route('pos.sales.store'), [
            'warehouse_id'   => $warehouseId,
            'payment_method' => 'cash',
            'items'          => [['product_id' => 1, 'qty' => 1, 'unit_price' => 100]],
        ]);

        $response->assertStatus(403);
    }

    public function test_accountant_cannot_adjust_inventory(): void
    {
        $tenant = $this->makeTenant('acc-inv');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $accountant = $this->makeUser('accountant', $tenant->id, $warehouseId);

        $response = $this->actingAs($accountant)->get(route('inventory.adjust'));

        $response->assertStatus(403);
    }

    public function test_branch_manager_cannot_see_other_branch_data(): void
    {
        $tenant = $this->makeTenant('bm-scope');
        $wh1 = $this->makeWarehouse($tenant->id);
        $wh2 = $this->makeWarehouse($tenant->id);
        $manager = $this->makeUser('branch_manager', $tenant->id, $wh1);

        // Insert a sale for wh2 (a different branch) — must NOT appear in manager's metrics
        DB::table('sales')->insert([
            'tenant_id'      => $tenant->id,
            'cashier_id'     => $manager->id,
            'warehouse_id'   => $wh2,
            'receipt_no'     => 'RCP-OTHER-001',
            'total'          => 5000.00,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => 5000.00,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        // Manager is on wh1 — salesToday should be 0, not 5000
        $response->assertViewHas('sales', fn ($sales) => $sales['salesToday'] == 0.0);
    }

    public function test_super_admin_dashboard_has_no_tenant_data(): void
    {
        $admin = $this->makeUser('super_admin', null, null);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.super_admin');
        // Super admin view has tenant metrics, NOT sales/inventory arrays
        $response->assertViewMissing('sales');
        $response->assertViewMissing('inventory');
    }

    // ── Navigation rendering ──────────────────────────────────────────────────

    public function test_cashier_nav_does_not_render_reports_link(): void
    {
        $tenant = $this->makeTenant('nav-cashier');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $cashier = $this->makeUser('cashier', $tenant->id, $warehouseId);

        $response = $this->actingAs($cashier)->get(route('dashboard'));

        $response->assertOk();
        // Cashier has no reports.* permission — the reports section must not render
        $response->assertDontSeeText('VAT Report');
        $response->assertDontSeeText('Movement History');
        $response->assertDontSeeText('Valuation');
    }

    public function test_storekeeper_nav_does_not_render_pos_link(): void
    {
        $tenant = $this->makeTenant('nav-sk');
        $warehouseId = $this->makeWarehouse($tenant->id);
        $storekeeper = $this->makeUser('storekeeper', $tenant->id, $warehouseId);

        $response = $this->actingAs($storekeeper)->get(route('dashboard'));

        $response->assertOk();
        // Storekeeper has no sales.process — Point of Sale must not render
        $response->assertDontSeeText('Point of Sale');
        $response->assertDontSeeText('Sales History');
    }

    public function test_super_admin_nav_shows_platform_section(): void
    {
        $admin = $this->makeUser('super_admin', null, null);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // Super admin sees Platform section label in nav
        $response->assertSeeText('Platform');
    }
}
