<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Test 1: Registration creates a Tenant and User with a real tenant_id ──

    public function test_registration_creates_tenant_and_user_with_tenant_id(): void
    {
        $response = $this->post(route('register.post'), [
            'business_name'         => 'Kilimanjaro Traders',
            'name'                  => 'Alice Moshi',
            'email'                 => 'alice@kilimanjaro.co.tz',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('dashboard'));

        // Tenant must be created
        $this->assertDatabaseHas('tenants', ['name' => 'Kilimanjaro Traders']);

        // User must be created with a real, non-null, non-zero tenant_id
        $user = User::where('email', 'alice@kilimanjaro.co.tz')->first();
        $this->assertNotNull($user, 'User should exist after registration.');
        $this->assertNotNull($user->tenant_id, 'user.tenant_id must not be null.');
        $this->assertGreaterThan(0, $user->tenant_id, 'user.tenant_id must be > 0.');

        // The tenant_id must point to a real tenant row
        $tenant = Tenant::find($user->tenant_id);
        $this->assertNotNull($tenant, 'Tenant row must exist for the user tenant_id.');
        $this->assertEquals('Kilimanjaro Traders', $tenant->name);
    }

    // ── Test 2: tenant_id is never zero or null after registration ────────────

    public function test_user_tenant_id_is_never_zero_or_null_after_register(): void
    {
        // Without business_name — controller derives it from name
        $this->post(route('register.post'), [
            'name'                  => 'Bob Dar',
            'email'                 => 'bob@example.co.tz',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', 'bob@example.co.tz')->first();
        $this->assertNotNull($user);

        $tenantId = $user->tenant_id;
        $this->assertNotNull($tenantId, 'tenant_id must not be null.');
        $this->assertNotEquals(0, (int) $tenantId, 'tenant_id must not be 0.');
        $this->assertNotEquals(0, $tenantId, 'tenant_id must not equal integer 0.');
    }

    // ── Test 3: Login resolves the correct tenant_id on the session user ──────

    public function test_login_resolves_correct_tenant(): void
    {
        $tenant = Tenant::create([
            'name'   => 'Serengeti Co',
            'slug'   => 'serengeti-co',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email'     => 'mgr@serengeti.co.tz',
            'password'  => bcrypt('Password1!'),
            'status'    => 'active',
        ]);

        $response = $this->post(route('login.post'), [
            'email'    => 'mgr@serengeti.co.tz',
            'password' => 'Password1!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // The authenticated user must carry the correct tenant_id
        $this->assertEquals($tenant->id, auth()->user()->tenant_id);
        $this->assertGreaterThan(0, (int) auth()->user()->tenant_id);
    }

    // ── Test 4: Dashboard loads without tenant_id SQL error ──────────────────

    public function test_dashboard_loads_without_tenant_id_error(): void
    {
        $tenant = Tenant::create([
            'name'   => 'Zanzibar Spice Co',
            'slug'   => 'zanzibar-spice',
            'status' => 'active',
            'config' => ['plan' => 'starter'],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'status'    => 'active',
        ]);

        $this->actingAs($user);

        // Should render without "Unknown column 'tenant_id'" or any 500 error
        $response = $this->get(route('dashboard'));

        $response->assertOk();
    }

    // ── Test 5: TenantScope is not applied when user has no tenant_id ─────────

    public function test_globalscope_not_applied_when_no_tenant(): void
    {
        $userWithNoTenant = User::factory()->create(['tenant_id' => null, 'status' => 'active']);
        $this->actingAs($userWithNoTenant);

        // TenantScope.apply() condition: auth()->hasUser() && auth()->user()->tenant_id
        // null is falsy → scope must NOT inject any WHERE clause
        $scope   = new TenantScope();
        $builder = Sale::query();
        $scope->apply($builder, new Sale());

        $sql = $builder->toSql();
        $this->assertStringNotContainsString('tenant_id', $sql,
            'TenantScope must not inject a tenant_id WHERE clause when user.tenant_id is null.');

        // Also verify null is falsy (never cast to 0 by the scope itself)
        $this->assertNull(auth()->user()->tenant_id);
        $this->assertFalse((bool) auth()->user()->tenant_id,
            'null tenant_id must evaluate as falsy to prevent WHERE tenant_id = 0.');
    }
}
