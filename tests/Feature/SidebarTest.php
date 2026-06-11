<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Test Co',
            'slug'   => 'sidebar-test',
            'status' => 'active',
        ]);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);

        foreach ($permissions as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $role->givePermissionTo($p);
        }

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }

    // ── Suppliers ─────────────────────────────────────────────────────────────

    public function test_suppliers_route_returns_html_view_not_json(): void
    {
        $user = $this->makeUserWithPermissions(['purchases.view']);

        $response = $this->actingAs($user)->get(route('suppliers.index'));

        $response->assertOk();
        $response->assertViewIs('suppliers.index');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    // ── GRN ───────────────────────────────────────────────────────────────────

    public function test_grn_route_returns_html_view_not_json(): void
    {
        $user = $this->makeUserWithPermissions(['purchases.view']);

        $response = $this->actingAs($user)->get(route('grn.index'));

        $response->assertOk();
        $response->assertViewIs('grn.index');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    // ── Requisitions ──────────────────────────────────────────────────────────

    public function test_requisitions_route_returns_html_view_not_json(): void
    {
        $user = $this->makeUserWithPermissions(['purchases.view']);

        $response = $this->actingAs($user)->get(route('requisitions.index'));

        $response->assertOk();
        $response->assertViewIs('requisitions.index');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    // ── Expenses ──────────────────────────────────────────────────────────────

    public function test_expenses_route_returns_html_view_not_json(): void
    {
        $user = $this->makeUserWithPermissions(['expenses.view']);

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertOk();
        $response->assertViewIs('expenses.index');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public function test_users_route_accessible_for_super_admin(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $perm       = Permission::firstOrCreate(['name' => 'users.manage_all', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($perm);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status'    => 'active',
        ]);
        $user->assignRole('super_admin');
        $user->givePermissionTo('users.manage_all');

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertOk();
    }

    public function test_users_route_forbidden_for_cashier(): void
    {
        $user = $this->makeUserWithPermissions(['sales.process', 'sales.view']);

        $response = $this->actingAs($user)->get(route('users.index'));

        $response->assertForbidden();
    }

    // ── POS terminal ──────────────────────────────────────────────────────────

    public function test_pos_page_loads_without_oversized_elements(): void
    {
        $user = $this->makeUserWithPermissions(['sales.create', 'sales.process', 'inventory.view']);

        $response = $this->actingAs($user)->get(route('pos.terminal'));

        $response->assertOk();
        // Empty cart state must use compact padding, not h-full py-16
        $response->assertDontSee('py-16', false);
        $response->assertSee('cart-empty', false);
    }
}
