<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Temporary Password — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--bg:#0f172a;--surface:#1e293b;--border:#334155;--text:#f1f5f9;--muted:#94a3b8;--primary:#6366f1;--primary-g:#4f46e5;--success:#22c55e;--warning:#f59e0b;}
        body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
        .bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(99,102,241,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(99,102,241,.04) 1px,transparent 1px);background-size:40px 40px;pointer-events:none;}
        .bg-orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;}
        .orb1{width:400px;height:400px;top:-100px;left:-100px;background:rgba(99,102,241,.1);}
        .card{position:relative;z-index:1;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:2.5rem;width:100%;max-width:460px;box-shadow:0 25px 50px rgba(0,0,0,.5);}
        .logo{display:flex;align-items:center;gap:.6rem;font-weight:800;font-size:1.2rem;margin-bottom:2rem;justify-content:center;}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#6366f1,#38bdf8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
        .icon-wrap{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1.5rem;}
        h1{font-size:1.4rem;font-weight:800;text-align:center;margin-bottom:.4rem;}
        .subtitle{color:var(--muted);text-align:center;font-size:.875rem;margin-bottom:1.5rem;line-height:1.5;}
        .temp-pw-box{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;text-align:center;}
        .temp-pw-label{font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;}
        .temp-pw{font-family:monospace;font-size:1.5rem;font-weight:800;color:#4ade80;letter-spacing:.15em;word-break:break-all;}
        .warning-box{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:1rem;font-size:.8rem;color:#fbbf24;margin-bottom:1.5rem;display:flex;gap:.5rem;align-items:flex-start;line-height:1.5;}
        .no-account-box{background:rgba(99,102,241,.05);border:1px solid var(--border);border-radius:10px;padding:1rem;font-size:.85rem;color:var(--muted);text-align:center;margin-bottom:1.5rem;}
        .btn{display:block;width:100%;background:linear-gradient(135deg,var(--primary),var(--primary-g));color:#fff;border:none;border-radius:10px;padding:.8rem;font-size:.95rem;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;margin-bottom:.75rem;box-shadow:0 0 20px rgba(99,102,241,.3);}
        .back-link{text-align:center;font-size:.875rem;color:var(--muted);}
        .back-link a{color:var(--primary);text-decoration:none;font-weight:600;}
    </style>
</head>
<body>
<div class="bg-grid"></div>
<div class="bg-orb orb1"></div>

<div class="card">
    <div class="logo">
        <div class="logo-icon"><i class="fas fa-boxes-stacked"></i></div>
        SmartStock
    </div>

    @if($temp_password)
        <div class="icon-wrap" style="background:rgba(34,197,94,.1);color:#4ade80">
            <i class="fas fa-key"></i>
        </div>
        <h1>Temporary Password</h1>
        <p class="subtitle">A temporary password has been generated for <strong>{{ $email }}</strong>. Use it to log in, then change your password immediately.</p>

        <div class="temp-pw-box">
            <div class="temp-pw-label">Your temporary password</div>
            <div class="temp-pw" id="tmpPw">{{ $temp_password }}</div>
        </div>

        <div class="warning-box">
            <i class="fas fa-triangle-exclamation" style="flex-shrink:0;margin-top:.1rem"></i>
            <span>This password is shown only once. Copy it now — you will be required to change it after logging in.</span>
        </div>

        <a href="{{ route('login') }}" class="btn">
            <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>
    @else
        <div class="icon-wrap" style="background:rgba(99,102,241,.1);color:var(--primary)">
            <i class="fas fa-envelope-circle-check"></i>
        </div>
        <h1>Check Your Email</h1>
        <div class="no-account-box">
            If an account exists for <strong>{{ $email }}</strong>, a temporary password has been generated. Please contact your system administrator if you do not receive it.
        </div>
        <a href="{{ route('login') }}" class="btn">
            <i class="fas fa-sign-in-alt"></i> Back to Login
        </a>
    @endif

    <div class="back-link">
        <a href="{{ route('password.request') }}"><i class="fas fa-arrow-left"></i> Try a different email</a>
    </div>
</div>
</body>
</html>
