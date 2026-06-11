@extends('layouts.app')
@section('title', 'My Profile')

@push('styles')
<style>
    .profile-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
    @media(max-width:768px) { .profile-grid { grid-template-columns:1fr; } }
    .profile-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
    .profile-card h2 { font-size:.9rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin-bottom:1.25rem; display:flex; align-items:center; gap:.5rem; }
    .form-group { margin-bottom:1.1rem; }
    .form-group label { display:block; font-size:.8rem; font-weight:600; color:var(--muted); margin-bottom:.35rem; }
    .form-control { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text); padding:.6rem .875rem; font-size:.875rem; outline:none; transition:border-color .15s; }
    .form-control:focus { border-color:var(--primary); }
    .form-control.is-invalid { border-color:var(--danger); }
    .invalid-feedback { color:var(--danger); font-size:.78rem; margin-top:.25rem; }
    .avatar-wrap { display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; }
    .avatar-lg { width:72px; height:72px; border-radius:50%; object-fit:cover; background:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .avatar-lg img { width:100%; height:100%; object-fit:cover; }
    .role-badge { display:inline-flex; align-items:center; gap:.3rem; background:rgba(99,102,241,.15); color:var(--primary); border:1px solid rgba(99,102,241,.3); border-radius:999px; padding:.2rem .65rem; font-size:.75rem; font-weight:600; }
</style>
@endpush

@section('content')
<div class="card-header">
    <h1 style="font-size:1.25rem;font-weight:800">My Profile</h1>
</div>

@if(session('success'))
<div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:.75rem 1rem;border-radius:8px;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-circle-check"></i> {{ session('success') }}
</div>
@endif

<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
@csrf
@method('PUT')

<div class="profile-grid">

    {{-- ── Account Info ──────────────────────────────────────── --}}
    <div class="profile-card">
        <h2><i class="fas fa-user"></i> Account Information</h2>

        {{-- Avatar --}}
        <div class="avatar-wrap">
            <div class="avatar-lg" id="avatarPreview">
                @if($user->profile_photo_path)
                    <img src="{{ Storage::url($user->profile_photo_path) }}" alt="Photo">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </div>
            <div>
                <label class="btn btn-secondary btn-sm" style="cursor:pointer">
                    <i class="fas fa-camera"></i> Change Photo
                    <input type="file" name="profile_photo" accept="image/*" class="d-none" style="display:none" onchange="previewPhoto(this)">
                </label>
                <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">Max 2MB · JPG, PNG, GIF</div>
                @error('profile_photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $user->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" class="form-control" value="{{ $user->email }}" disabled>
            <div style="font-size:.72rem;color:var(--muted);margin-top:.2rem">Email cannot be changed. Contact your admin.</div>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone', $user->phone) }}" placeholder="+255 7xx xxx xxx">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div style="margin-top:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <span class="role-badge">
                <i class="fas fa-shield-halved"></i>
                {{ $user->getRoleNames()->first() ?? 'User' }}
            </span>
            @if($user->tenant)
            <span style="font-size:.8rem;color:var(--muted)">
                <i class="fas fa-building"></i> {{ $user->tenant->name }}
            </span>
            @endif
        </div>
    </div>

    {{-- ── Change Password ───────────────────────────────────── --}}
    <div class="profile-card">
        <h2><i class="fas fa-lock"></i> Change Password</h2>

        @if($user->must_change_password)
        <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:.75rem;font-size:.8rem;color:#fbbf24;margin-bottom:1.1rem;display:flex;gap:.5rem">
            <i class="fas fa-triangle-exclamation" style="flex-shrink:0;margin-top:.1rem"></i>
            You are using a temporary password. Please set a new password now.
        </div>
        @endif

        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password"
                   class="form-control @error('current_password') is-invalid @enderror"
                   placeholder="Enter current password" autocomplete="current-password">
            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Min 8 chars, upper + lower + number" autocomplete="new-password">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="password_confirmation"
                   class="form-control" placeholder="Repeat new password" autocomplete="new-password">
        </div>

        <div style="margin-top:.25rem;font-size:.75rem;color:var(--muted)">
            Leave password fields blank to keep your current password.
        </div>
    </div>

</div>

<div style="margin-top:1.25rem;display:flex;gap:.75rem">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-floppy-disk"></i> Save Changes
    </button>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Cancel</a>
</div>
</form>
@endsection

@push('scripts')
<script>
function previewPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const wrap = document.getElementById('avatarPreview');
        wrap.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
@endpush
