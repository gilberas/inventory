<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#0f172a;--surface:#1e293b;--border:#334155;--text:#f1f5f9;--muted:#94a3b8;--primary:#6366f1;--primary-g:#4f46e5;--danger:#ef4444;}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
        .bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(99,102,241,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
        .bg-orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;}
        .orb1{width:400px;height:400px;top:-100px;left:-100px;background:rgba(99,102,241,.1);}
        .orb2{width:300px;height:300px;bottom:-80px;right:-80px;background:rgba(56,189,248,.07);}
        .card{position:relative;z-index:1;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:2.5rem;width:100%;max-width:420px;box-shadow:0 25px 50px rgba(0,0,0,.5);}
        .logo{display:flex;align-items:center;gap:.6rem;font-weight:800;font-size:1.2rem;margin-bottom:2rem;justify-content:center;}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#6366f1,#38bdf8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
        h1{font-size:1.5rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
        .subtitle{color:var(--muted);text-align:center;font-size:.875rem;margin-bottom:2rem;}
        .form-group{margin-bottom:1.25rem;}
        label{display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;}
        .input-wrap{position:relative;}
        .input-wrap i{position:absolute;left:.875rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.875rem;}
        input{width:100%;background:rgba(15,23,42,.6);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.7rem .875rem .7rem 2.5rem;font-size:.9rem;outline:none;transition:border-color .15s;}
        input:focus{border-color:var(--primary);}
        input.no-icon{padding-left:.875rem;}
        .error-msg{color:var(--danger);font-size:.78rem;margin-top:.35rem;display:flex;align-items:center;gap:.3rem;}
        .remember{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--muted);cursor:pointer;margin-bottom:1.5rem;}
        .remember input[type=checkbox]{width:16px;height:16px;accent-color:var(--primary);}
        .btn-submit{width:100%;background:linear-gradient(135deg,var(--primary),var(--primary-g));color:#fff;border:none;border-radius:10px;padding:.8rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 0 20px rgba(99,102,241,.3);}
        .btn-submit:hover{transform:translateY(-1px);box-shadow:0 0 30px rgba(99,102,241,.5);}
        .divider{text-align:center;color:var(--muted);font-size:.8rem;margin:1.25rem 0;position:relative;}
        .divider::before,.divider::after{content:'';position:absolute;top:50%;width:42%;height:1px;background:var(--border);}
        .divider::before{left:0} .divider::after{right:0}
        .register-link{text-align:center;font-size:.875rem;color:var(--muted);}
        .register-link a{color:var(--primary);text-decoration:none;font-weight:600;}
        .register-link a:hover{text-decoration:underline;}
    </style>
</head>
<body>
<div class="bg-grid"></div>
<div class="bg-orb orb1"></div>
<div class="bg-orb orb2"></div>

<div class="card">
    <div class="logo">
        <div class="logo-icon"><i class="fas fa-boxes-stacked"></i></div>
        InventoryPro
    </div>

    <h1>Welcome back</h1>
    <p class="subtitle">Sign in to your account to continue</p>

    @if (session('success'))
        <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:.75rem 1rem;border-radius:8px;font-size:.85rem;margin-bottom:1.25rem;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        {{-- Email --}}
        <div class="form-group">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       placeholder="you@example.com"
                       autocomplete="email" required>
            </div>
            @error('email')
                <div class="error-msg"><i class="fas fa-circle-exclamation"></i> {{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password"
                       placeholder="••••••••"
                       autocomplete="current-password" required>
            </div>
            @error('password')
                <div class="error-msg"><i class="fas fa-circle-exclamation"></i> {{ $message }}</div>
            @enderror
        </div>

        {{-- Remember me --}}
        <label class="remember">
            <input type="checkbox" name="remember"> Remember me for 30 days
        </label>

        <button type="submit" class="btn-submit">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>

    @if (Route::has('register'))
        <div class="divider">or</div>
        <div class="register-link">
            Don't have an account? <a href="{{ route('register') }}">Create one</a>
        </div>
    @endif
</div>
</body>
</html>
