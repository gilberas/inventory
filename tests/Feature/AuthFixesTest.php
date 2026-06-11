<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthFixesTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $slug = 'test'): Tenant
    {
        return Tenant::create([
            'name'   => 'Test Co',
            'slug'   => $slug,
            'status' => 'active',
        ]);
    }

    private function makeUser(?int $tenantId = null, ?string $role = null): User
    {
        if ($role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $user = User::factory()->create([
            'tenant_id' => $tenantId,
            'status'    => 'active',
        ]);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return $user;
    }

    // ── Forgot password ───────────────────────────────────────────────────────

    public function test_forgot_password_form_loads(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertViewIs('auth.forgot-password');
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->post(route('password.email'), []);

        $response->assertSessionHasErrors('email');
    }

    public function test_unknown_email_still_shows_sent_page(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nobody@nowhere.example',
        ]);

        $response->assertOk();
        $response->assertViewIs('auth.forgot-password-sent');
        $response->assertViewHas('temp_password', null);
    }

    public function test_known_email_generates_temp_password(): void
    {
        $tenant = $this->makeTenant('fp-test');
        $user   = $this->makeUser($tenant->id);

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertOk();
        $response->assertViewIs('auth.forgot-password-sent');
        $response->assertViewHas('email', $user->email);
        // A non-null temp_password means one was generated and shown
        $this->assertNotNull($response->viewData('temp_password'));
    }

    public function test_forgot_password_sets_must_change_password_flag(): void
    {
        $tenant = $this->makeTenant('fp-flag');
        $user   = $this->makeUser($tenant->id);

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertTrue($user->fresh()->must_change_password);
    }

    public function test_forgot_password_actually_changes_the_password(): void
    {
        $tenant = $this->makeTenant('fp-pw');
        $user   = $this->makeUser($tenant->id);
        $oldHash = $user->password;

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->assertNotEquals($oldHash, $user->fresh()->password);
    }

    // ── must_change_password banner ───────────────────────────────────────────

    public function test_banner_shows_when_must_change_password_is_true(): void
    {
        $tenant = $this->makeTenant('banner-show');
        $user   = $this->makeUser($tenant->id);
        $user->update(['must_change_password' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertSeeText('Action required');
    }

    public function test_banner_hidden_when_flag_is_false(): void
    {
        $tenant = $this->makeTenant('banner-hide');
        $user   = $this->makeUser($tenant->id);
        $user->update(['must_change_password' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertDontSeeText('Action required');
    }

    public function test_banner_dismiss_sets_session_flag(): void
    {
        $tenant = $this->makeTenant('banner-dismiss');
        $user   = $this->makeUser($tenant->id);
        $user->update(['must_change_password' => true]);

        $response = $this->actingAs($user)->post(route('banner.dismiss.password'));

        $response->assertRedirect();
        $this->assertEquals(true, session('pw_notice_dismissed'));
    }

    // ── User Profile ──────────────────────────────────────────────────────────

    public function test_profile_page_loads(): void
    {
        $tenant = $this->makeTenant('profile-load');
        $user   = $this->makeUser($tenant->id);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertViewIs('profile.edit');
        $response->assertViewHas('user');
    }

    public function test_profile_update_changes_name_and_phone(): void
    {
        $tenant = $this->makeTenant('profile-update');
        $user   = $this->makeUser($tenant->id);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name'  => 'Updated Name',
            'phone' => '+255712345678',
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Name', $user->fresh()->name);
        $this->assertEquals('+255712345678', $user->fresh()->phone);
    }

    public function test_profile_update_changes_password_and_clears_flag(): void
    {
        $tenant = $this->makeTenant('profile-pw');
        $user   = User::factory()->create([
            'tenant_id'            => $tenant->id,
            'password'             => bcrypt('OldPassword1'),
            'must_change_password' => true,
            'status'               => 'active',
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name'                  => $user->name,
            'current_password'      => 'OldPassword1',
            'password'              => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ]);

        $response->assertRedirect();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(password_verify('NewPassword9', $user->fresh()->password));
    }

    public function test_profile_update_rejects_wrong_current_password(): void
    {
        $tenant = $this->makeTenant('profile-bad-pw');
        $user   = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password'  => bcrypt('CorrectPass1'),
            'status'    => 'active',
        ]);

        $response = $this->actingAs($user)->put(route('profile.update'), [
            'name'                  => $user->name,
            'current_password'      => 'WrongPass99',
            'password'              => 'NewPassword9',
            'password_confirmation' => 'NewPassword9',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_profile_photo_upload_stores_file(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available.');
        }

        Storage::fake('public');

        $tenant = $this->makeTenant('profile-photo');
        $user   = $this->makeUser($tenant->id);
        $photo  = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->actingAs($user)->put(route('profile.update'), [
            'name'          => $user->name,
            'profile_photo' => $photo,
        ]);

        $this->assertNotNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertExists($user->fresh()->profile_photo_path);
    }
}
