<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    // ── SHOW LOGIN ────────────────────────────────────────────────────────────
    public function showLogin()
    {
        return view('auth.login');
    }

    // ── PROCESS LOGIN ─────────────────────────────────────────────────────────
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Look up the user directly so we can check lock/status before Auth::attempt
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Account locked due to too many failed attempts. Try again later.']);
        }

        if ($user && !$user->isActive()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is inactive. Please contact support.']);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->clearLoginAttempts();
            Auth::user()->update(['last_login_at' => now()]);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Welcome back, ' . Auth::user()->name . '!');
        }

        // Increment failed-login counter for the found user
        $user?->incrementFailedLogins();

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    // ── SHOW REGISTER ─────────────────────────────────────────────────────────
    public function showRegister()
    {
        return view('auth.register');
    }

    // ── PROCESS REGISTER ──────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'unique:users,email'],
            'password'      => ['required', 'confirmed', Password::min(8)],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            // 1. Create Tenant first — every user must belong to a real tenant
            $businessName = $data['business_name'] ?? ($data['name'] . "'s Business");

            $tenant = Tenant::create([
                'name'   => $businessName,
                'slug'   => Str::slug($businessName) . '-' . Str::lower(Str::random(4)),
                'status' => 'active',
                'config' => ['plan' => 'starter'],
            ]);

            // 2. Create User with real tenant_id — never null or 0
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => Hash::make($data['password']),
                'status'    => 'active',
            ]);

            // 3. Assign best available role (Super Admin > Manager > Storekeeper)
            foreach (['Super Admin', 'Manager', 'Storekeeper'] as $roleName) {
                if (Role::where('name', $roleName)->exists()) {
                    $user->assignRole($roleName);
                    break;
                }
            }

            return $user;
        });

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Account created! Welcome, ' . $user->name . '.');
    }

    // ── LOGOUT ────────────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out.');
    }
}
