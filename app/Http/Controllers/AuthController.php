<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))
                ->with('success', 'Welcome back, ' . Auth::user()->name . '!');
        }

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
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Assign a default role if it exists
        if (Role::where('name', 'Storekeeper')->exists()) {
            $user->assignRole('Storekeeper');
        }

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
