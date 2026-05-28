<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#0f172a;--surface:#1e293b;--border:#334155;--text:#f1f5f9;--muted:#94a3b8;--primary:#6366f1;--primary-g:#4f46e5;--danger:#ef4444;--success:#22c55e;}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
        .bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(99,102,241,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
        .bg-orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;}
        .orb1{width:400px;height:400px;top:-100px;right:-100px;background:rgba(99,102,241,.1);}
        .orb2{width:300px;height:300px;bottom:-80px;left:-80px;background:rgba(56,189,248,.07);}
        .card{position:relative;z-index:1;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:2.5rem;width:100%;max-width:440px;box-shadow:0 25px 50px rgba(0,0,0,.5);}
        .logo{display:flex;align-items:center;gap:.6rem;font-weight:800;font-size:1.2rem;margin-bottom:2rem;justify-content:center;}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#6366f1,#38bdf8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
        h1{font-size:1.5rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
        .subtitle{color:var(--muted);text-align:center;font-size:.875rem;margin-bottom:2rem;}
        .form-group{margin-bottom:1.1rem;}
        label{display:block;font-size:.8rem;font-weight:600;color:var(--muted);margin-bottom:.4rem;}
        .input-wrap{position:relative;}
        .input-wrap i{position:absolute;left:.875rem;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.875rem;}
        input{width:100%;background:rgba(15,23,42,.6);border:1px solid var(--border);border-radius:10px;color:var(--text);padding:.7rem .875rem .7rem 2.5rem;font-size:.9rem;outline:none;transition:border-color .15s;}
        input:focus{border-color:var(--primary);}
        .error-msg{color:var(--danger);font-size:.78rem;margin-top:.35rem;display:flex;align-items:center;gap:.3rem;}
        .strength-bar{height:3px;border-radius:2px;margin-top:.4rem;background:var(--border);overflow:hidden;}
        .strength-fill{height:100%;width:0;transition:width .3s,background .3s;}
        .btn-submit{width:100%;background:linear-gradient(135deg,var(--primary),var(--primary-g));color:#fff;border:none;border-radius:10px;padding:.8rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 0 20px rgba(99,102,241,.3);margin-top:1.25rem;}
        .btn-submit:hover{transform:translateY(-1px);box-shadow:0 0 30px rgba(99,102,241,.5);}
        .login-link{text-align:center;font-size:.875rem;color:var(--muted);margin-top:1.25rem;}
        .login-link a{color:var(--primary);text-decoration:none;font-weight:600;}
        .login-link a:hover{text-decoration:underline;}
        .terms{font-size:.78rem;color:var(--muted);text-align:center;margin-top:1rem;line-height:1.5;}
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

    <h1>Create account</h1>
    <p class="subtitle">Start managing your inventory today</p>

    <form method="POST" action="{{ route('register.post') }}">
        @csrf

        {{-- Name --}}
        <div class="form-group">
            <label for="name">Full name</label>
            <div class="input-wrap">
                <i class="fas fa-user"></i>
                <input type="text" id="name" name="name" value="{{ old('name') }}"
                       placeholder="John Doe" autocomplete="name" required>
            </div>
            @error('name')
                <div class="error-msg"><i class="fas fa-circle-exclamation"></i> {{ $message }}</div>
            @enderror
        </div>

        {{-- Email --}}
        <div class="form-group">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       placeholder="you@example.com" autocomplete="email" required>
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
                       placeholder="Min. 8 characters" autocomplete="new-password"
                       oninput="checkStrength(this.value)" required>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            @error('password')
                <div class="error-msg"><i class="fas fa-circle-exclamation"></i> {{ $message }}</div>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       placeholder="Repeat password" required>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-rocket"></i> Create Account
        </button>
    </form>

    <div class="login-link">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </div>

    <p class="terms">By registering you agree to our Terms of Service and Privacy Policy.</p>
</div>

<script>
function checkStrength(pw) {
    const fill = document.getElementById('strengthFill');
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const colors = ['#ef4444','#f59e0b','#22c55e','#6366f1'];
    fill.style.width  = (score * 25) + '%';
    fill.style.background = colors[score - 1] || '#ef4444';
}
</script>
</body>
</html>
