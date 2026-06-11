<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'phone'                 => 'nullable|string|max:20',
            'profile_photo'         => 'nullable|image|max:2048',
            'current_password'      => 'nullable|string',
            'password'              => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        // Handle photo upload
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('profile_photo')
                ->store('profile-photos', 'public');
        }

        // Handle password change
        if (! empty($data['password'])) {
            if (! empty($data['current_password']) && ! password_verify($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
            }
            $data['must_change_password'] = false;
        }

        unset($data['profile_photo'], $data['current_password']);

        $user->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('success', 'Profile updated successfully.');
    }
}
