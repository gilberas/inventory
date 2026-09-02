<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error — SmartStock ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#0f172a;color:#f1f5f9;font-family:'Segoe UI',system-ui,sans-serif;
             min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
        .wrap{text-align:center;max-width:420px;width:100%}
        .icon-wrap{width:88px;height:88px;border-radius:24px;background:rgba(239,68,68,.1);
                   border:1px solid rgba(239,68,68,.2);display:flex;align-items:center;
                   justify-content:center;margin:0 auto 2rem;font-size:2.5rem;color:#f87171}
        .error-label{font-size:.75rem;font-weight:700;text-transform:uppercase;
                     letter-spacing:.15em;color:#f87171;margin-bottom:.75rem}
        h1{font-size:2rem;font-weight:800;margin-bottom:.75rem;color:#f1f5f9}
        .desc{color:#94a3b8;font-size:.9375rem;line-height:1.6;margin-bottom:2rem}
        .actions{display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.25rem;
             border-radius:10px;font-size:.875rem;font-weight:600;text-decoration:none;
             cursor:pointer;border:none;transition:opacity .15s}
        .btn-ghost{background:#1e293b;border:1px solid #334155;color:#f1f5f9}
        .btn-ghost:hover{background:#273348}
        .btn-primary{background:#6366f1;color:#fff}
        .btn-primary:hover{background:#4f46e5}
        .brand{display:flex;align-items:center;gap:.5rem;justify-content:center;
               color:#475569;font-size:.75rem;margin-top:2.5rem}
        .brand-icon{width:24px;height:24px;background:#6366f1;border-radius:6px;
                    display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="icon-wrap"><i class="fas fa-triangle-exclamation"></i></div>

        <p class="error-label">Error 500</p>
        <h1>Something Went Wrong</h1>
        <p class="desc">
            An unexpected error occurred on our end.
            Our team has been notified. Please try again in a few minutes.
        </p>

        <div class="actions">
            <button onclick="window.location.reload()" class="btn btn-ghost">
                <i class="fas fa-rotate-right"></i> Try Again
            </button>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="fas fa-house"></i> Dashboard
            </a>
        </div>

        <div class="brand">
            <div class="brand-icon"><i class="fas fa-boxes-stacked"></i></div>
            SmartStock ERP
        </div>
    </div>
</body>
</html>
