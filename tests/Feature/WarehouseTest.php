<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'business_owner', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.audit',  'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.adjust', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name'   => 'WH Test Co',
            'slug'   => 'wh-test',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->assignRole('business_owner');
        $this->manager->givePermissionTo(['inventory.audit', 'inventory.adjust']);
    }

    // ── Test 1 ────────────────────────────────────────────────────────────────

    public function test_warehouse_auto_created_with_branch(): void
    {
        $this->actingAs($this->manager);

        $branch = Branch::create(['name' => 'Downtown', 'code' => 'DT', 'is_active' => true]);

        $this->assertDatabaseHas('warehouses', [
            'branch_id' => $branch->id,
            'name'      => 'Main Warehouse',
            'tenant_id' => $this->tenant->id,
            'is_default'=> true,
        ]);

        $warehouse = Warehouse::where('branch_id', $branch->id)->first();
        $this->assertNotNull($warehouse);
        $this->assertEquals($this->tenant->id, $warehouse->tenant_id);
    }

    // ── Test 2 ────────────────────────────────────────────────────────────────

    public function test_starter_plan_blocked_at_second_warehouse(): void
    {
        // tenant config: plan = starter (1 warehouse per branch)
        $this->actingAs($this->manager);

        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA', 'is_active' => true]);
        // auto-created warehouse = 1 (the limit for starter)

        $response = $this->actingAs($this->manager)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('warehouses.store'), [
                'branch_id' => $branch->id,
                'name'      => 'Second Warehouse',
                'code'      => 'WH-BRA-002',
            ]);

        $response->assertStatus(402);
        $response->assertJsonFragment(['limit' => 1]);
    }

    // ── Test 3 ────────────────────────────────────────────────────────────────

    public function test_professional_plan_allows_3_warehouses(): void
    {
        $this->tenant->update(['config' => ['plan' => 'professional']]);
        $this->actingAs($this->manager);

        $branch = Branch::create(['name' => 'Branch B', 'code' => 'BRB', 'is_active' => true]);
        // auto-created = warehouse #1

        // Add warehouses #2 and #3 — should succeed
        for ($i = 2; $i <= 3; $i++) {
            $this->actingAs($this->manager)
                ->post(route('warehouses.store'), [
                    'branch_id' => $branch->id,
                    'name'      => "Warehouse $i",
                    'code'      => "WH-BRB-00{$i}",
                ])
                ->assertRedirect();
        }

        // Attempt warehouse #4 — must be blocked (limit = 3)
        $response = $this->actingAs($this->manager)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('warehouses.store'), [
                'branch_id' => $branch->id,
                'name'      => 'Warehouse 4',
                'code'      => 'WH-BRB-004',
            ]);

        $response->assertStatus(402);
        $response->assertJsonFragment(['limit' => 3]);
    }

    // ── Test 4 ────────────────────────────────────────────────────────────────

    public function test_warehouse_transfer_adjusts_both_balances(): void
    {
        // Allow 2 warehouses on this branch (professional plan)
        $this->tenant->update(['config' => ['plan' => 'professional']]);
        $this->actingAs($this->manager);

        // Source warehouse (auto-created with branch)
        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'MB', 'is_active' => true]);
        $srcWarehouse = Warehouse::where('branch_id', $branch->id)->first();

        // Destination warehouse
        $this->actingAs($this->manager)->post(route('warehouses.store'), [
            'branch_id' => $branch->id,
            'name'      => 'Dest Warehouse',
            'code'      => 'WH-MB-002',
        ])->assertRedirect();

        $destWarehouse = Warehouse::where('code', 'WH-MB-002')->first();
        $this->assertNotNull($destWarehouse);

        // Create a product
        $productId = DB::table('products')->insertGetId([
            'tenant_id'     => $this->tenant->id,
            'sku'           => 'SKU-XFER-001',
            'name'          => 'Transfer Product',
            'cost_price'    => 50,
            'selling_price' => 80,
            'minimum_stock' => 0,
            'reorder_level' => 0,
            'is_active'     => true,
            'track_expiry'  => false,
            'track_batch'   => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Seed 20 units in source warehouse
        $service = app(InventoryService::class);
        $service->stockIn($productId, $srcWarehouse->id, 20, 50.0, 'purchase', 1, $this->manager->id);

        // Create transfer request (5 units)
        $this->actingAs($this->manager)->post(route('warehouses.transfers.store'), [
            'from_warehouse_id' => $srcWarehouse->id,
            'to_warehouse_id'   => $destWarehouse->id,
            'items'             => [['product_id' => $productId, 'qty' => 5]],
        ])->assertRedirect();

        $transfer = WarehouseTransfer::latest()->first();
        $this->assertNotNull($transfer);
        $this->assertEquals('pending', $transfer->status);

        // Approve
        $this->actingAs($this->manager)
            ->post(route('warehouses.transfers.approve', $transfer))
            ->assertRedirect();
        $transfer->refresh();
        $this->assertEquals('approved', $transfer->status);

        // Dispatch — calls InventoryService::stockOut on source
        $this->actingAs($this->manager)
            ->post(route('warehouses.transfers.dispatch', $transfer))
            ->assertRedirect();
        $transfer->refresh();
        $this->assertEquals('dispatched', $transfer->status);

        // Receive — calls InventoryService::stockIn on destination
        $this->actingAs($this->manager)
            ->post(route('warehouses.transfers.receive', $transfer))
            ->assertRedirect();
        $transfer->refresh();
        $this->assertEquals('received', $transfer->status);

        // Source: 20 - 5 = 15
        $this->assertEqualsWithDelta(15.0, $service->getBalance($productId, $srcWarehouse->id), 0.001);
        // Dest: 0 + 5 = 5
        $this->assertEqualsWithDelta(5.0, $service->getBalance($productId, $destWarehouse->id), 0.001);
    }

    // ── Test 5 ────────────────────────────────────────────────────────────────

    public function test_warehouses_are_tenant_scoped(): void
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active',
        ]);

        // Insert a warehouse belonging to tenant B directly
        DB::table('warehouses')->insert([
            'tenant_id'  => $tenantB->id,
            'name'       => 'Tenant B Warehouse',
            'code'       => 'WH-TB-001',
            'is_default' => false,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert a warehouse belonging to our tenant
        $this->actingAs($this->manager);
        Branch::create(['name' => 'Our Branch', 'code' => 'OUR', 'is_active' => true]);
        // Branch::created auto-creates "Main Warehouse" for tenant A

        $response = $this->actingAs($this->manager)->get(route('warehouses.index'));

        $response->assertOk();
        $response->assertViewHas('warehouses', function ($warehouses) {
            foreach ($warehouses as $wh) {
                if ($wh->name === 'Tenant B Warehouse') return false;
            }
            return true; // Tenant B's warehouse is not visible
        });
    }
}
