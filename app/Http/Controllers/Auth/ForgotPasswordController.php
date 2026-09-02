<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showForm(): View
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request): View|RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always show the "sent" page to avoid email enumeration.
        if (! $user) {
            return view('auth.forgot-password-sent', ['email' => $request->email, 'temp_password' => null]);
        }

        $tempPassword = Str::random(12);

        $user->update([
            'password'            => bcrypt($tempPassword),
            'must_change_password' => true,
        ]);

        // MVP: display temp password on screen instead of email delivery.
        return view('auth.forgot-password-sent', [
            'email'         => $request->email,
            'temp_password' => $tempPassword,
        ]);
    }
}
