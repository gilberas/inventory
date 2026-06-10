<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $perms = [
            'employees.view', 'employees.create', 'employees.edit',
            'employees.delete', 'attendance.manage',
        ];
        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->tenant = Tenant::create([
            'name'   => 'Emp Test Co',
            'slug'   => 'emp-test',
            'status' => 'active',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $this->manager->givePermissionTo($perms);

        $this->branchId = DB::table('branches')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Head Office',
            'code'       => 'HO',
            'address'    => '123 Main St',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function makeEmployee(array $overrides = []): int
    {
        return DB::table('employees')->insertGetId(array_merge([
            'tenant_id'  => $this->tenant->id,
            'branch_id'  => $this->branchId,
            'user_id'    => null,
            'name'       => 'Jane Doe',
            'department' => 'Sales',
            'position'   => 'Cashier',
            'phone'      => '+255700000001',
            'join_date'  => '2024-01-15',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ── Test 1: Create employee ────────────────────────────────────────────────

    public function test_manager_can_create_employee(): void
    {
        $response = $this->actingAs($this->manager)->post(route('employees.store'), [
            'branch_id'  => $this->branchId,
            'name'       => 'John Smith',
            'department' => 'Warehouse',
            'position'   => 'Storekeeper',
            'phone'      => '+255700000099',
            'join_date'  => '2025-01-01',
            'status'     => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('employees', [
            'tenant_id' => $this->tenant->id,
            'name'      => 'John Smith',
        ]);
    }

    // ── Test 2: Employee list is tenant-scoped ─────────────────────────────────

    public function test_employee_list_is_tenant_scoped(): void
    {
        $this->makeEmployee(['name' => 'Tenant1 Employee']);

        $tenant2  = Tenant::create(['name' => 'Other Co', 'slug' => 'other-emp', 'status' => 'active']);
        $branch2  = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant2->id, 'name' => 'Other Branch', 'code' => 'OB',
            'address' => 'Somewhere', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('employees')->insertGetId([
            'tenant_id' => $tenant2->id, 'branch_id' => $branch2,
            'name' => 'Other Tenant Employee', 'phone' => '+255700000002',
            'join_date' => '2024-01-01', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->manager)->get(route('employees.index'));

        $response->assertStatus(200);
        $response->assertSee('Tenant1 Employee');
        $response->assertDontSee('Other Tenant Employee');
    }

    // ── Test 3: Clock in creates attendance record ─────────────────────────────

    public function test_clock_in_creates_attendance_record(): void
    {
        $empId = $this->makeEmployee();

        // Use withoutGlobalScopes to load the employee bypassing TenantScope
        $employee = Employee::withoutGlobalScopes()->findOrFail($empId);

        $response = $this->actingAs($this->manager)
            ->post(route('attendance.clock-in', $employee));

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance', [
            'employee_id' => $empId,
            'status'      => 'present',
        ]);
    }

    // ── Test 4: Cannot clock in twice on the same day ──────────────────────────

    public function test_cannot_clock_in_twice_on_same_day(): void
    {
        $empId = $this->makeEmployee();

        DB::table('attendance')->insertGetId([
            'tenant_id'   => $this->tenant->id,
            'employee_id' => $empId,
            'date'        => today()->toDateString(),
            'clock_in'    => now()->subHours(2),
            'status'      => 'present',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $employee = Employee::withoutGlobalScopes()->findOrFail($empId);

        $response = $this->actingAs($this->manager)
            ->post(route('attendance.clock-in', $employee));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, DB::table('attendance')->where('employee_id', $empId)->count());
    }

    // ── Test 5: Performance view shows sales revenue for linked user ───────────

    public function test_performance_shows_sales_for_linked_user(): void
    {
        $empId = $this->makeEmployee(['user_id' => $this->manager->id]);
        $employee = Employee::withoutGlobalScopes()->findOrFail($empId);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'EmpWH',
            'code'       => 'EWH',
            'is_active'  => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales')->insertGetId([
            'tenant_id'      => $this->tenant->id,
            'cashier_id'     => $this->manager->id,
            'warehouse_id'   => $warehouseId,
            'receipt_no'     => 'EMP-RCP-001',
            'total'          => 750.00,
            'discount'       => 0,
            'tax'            => 0,
            'grand_total'    => 750.00,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('employees.performance', $employee));

        $response->assertStatus(200);
        $response->assertSee('750.00');
    }
}
