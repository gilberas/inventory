<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventory System')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:#0f172a; --surface:#1e293b; --border:#334155; --text:#f1f5f9; --muted:#94a3b8;
            --primary:#6366f1; --primary-h:#4f46e5; --success:#22c55e; --danger:#ef4444;
            --warning:#f59e0b; --info:#38bdf8; --sidebar-w:240px;
        }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; height:100vh; overflow-y:auto; z-index:100; }
        .sidebar-header { display:flex; align-items:center; gap:.75rem; padding:1.25rem 1rem; border-bottom:1px solid var(--border); color:var(--primary); font-weight:700; font-size:1.1rem; }
        .sidebar-header i { font-size:1.4rem; }
        .sidebar-nav { flex:1; padding:.75rem 0; }
        .nav-section-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); padding:.75rem 1rem .25rem; }
        .nav-item { display:flex; align-items:center; gap:.65rem; padding:.6rem 1rem; color:var(--muted); text-decoration:none; font-size:.875rem; transition:all .15s; }
        .nav-item:hover { background:rgba(99,102,241,.12); color:var(--text); }
        .nav-item.active { background:rgba(99,102,241,.2); color:var(--primary); font-weight:600; border-right:3px solid var(--primary); }
        .nav-item i { width:18px; text-align:center; }
        .sidebar-footer { border-top:1px solid var(--border); padding:1rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
        .user-info { display:flex; align-items:center; gap:.6rem; overflow:hidden; }
        .avatar { width:32px; height:32px; background:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.875rem; flex-shrink:0; }
        .user-name { font-size:.8rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .user-role { font-size:.7rem; color:var(--muted); }
        .btn-logout { background:none; border:1px solid var(--border); color:var(--muted); padding:.4rem .6rem; border-radius:6px; cursor:pointer; transition:all .15s; }
        .btn-logout:hover { border-color:var(--danger); color:var(--danger); }
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:.875rem 1.5rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar-title { font-size:1.1rem; font-weight:700; }
        .topbar-breadcrumb { font-size:.8rem; color:var(--muted); margin-top:.1rem; }
        .topbar-actions { display:flex; align-items:center; gap:.5rem; }
        .content { padding:1.5rem; flex:1; }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
        .card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }
        .card-title { font-size:1rem; font-weight:700; }
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; display:flex; align-items:center; gap:1rem; }
        .stat-icon { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; }
        .stat-icon.purple{background:rgba(99,102,241,.15);color:var(--primary)} .stat-icon.green{background:rgba(34,197,94,.15);color:var(--success)}
        .stat-icon.amber{background:rgba(245,158,11,.15);color:var(--warning)} .stat-icon.sky{background:rgba(56,189,248,.15);color:var(--info)}
        .stat-icon.red{background:rgba(239,68,68,.15);color:var(--danger)}
        .stat-value { font-size:1.6rem; font-weight:800; line-height:1; }
        .stat-label { font-size:.8rem; color:var(--muted); margin-top:.25rem; }
        .table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        thead th { padding:.75rem 1rem; text-align:left; color:var(--muted); font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
        tbody td { padding:.85rem 1rem; border-bottom:1px solid rgba(51,65,85,.5); vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:rgba(99,102,241,.04); }
        .badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .6rem; border-radius:999px; font-size:.75rem; font-weight:600; }
        .badge-green{background:rgba(34,197,94,.15);color:#4ade80} .badge-red{background:rgba(239,68,68,.15);color:#f87171}
        .badge-amber{background:rgba(245,158,11,.15);color:#fbbf24} .badge-sky{background:rgba(56,189,248,.15);color:#38bdf8}
        .badge-purple{background:rgba(99,102,241,.15);color:#818cf8} .badge-gray{background:rgba(148,163,184,.15);color:#94a3b8}
        .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1rem; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; transition:all .15s; }
        .btn-primary{background:var(--primary);color:#fff} .btn-primary:hover{background:var(--primary-h)}
        .btn-success{background:#166534;color:#4ade80} .btn-success:hover{background:#14532d}
        .btn-danger{background:#450a0a;color:#f87171} .btn-danger:hover{background:#3f0e0e}
        .btn-secondary{background:var(--border);color:var(--text)} .btn-secondary:hover{background:#475569}
        .btn-sm{padding:.35rem .65rem;font-size:.8rem} .btn-icon{padding:.4rem;width:32px;height:32px;justify-content:center}
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1.25rem; }
        .form-group { display:flex; flex-direction:column; gap:.4rem; }
        .form-group.full { grid-column:1/-1; }
        label { font-size:.8rem; font-weight:600; color:var(--muted); }
        input[type="text"],input[type="email"],input[type="password"],input[type="number"],input[type="date"],select,textarea {
            background:var(--bg); border:1px solid var(--border); border-radius:8px; color:var(--text);
            padding:.6rem .875rem; font-size:.875rem; outline:none; transition:border-color .15s; width:100%;
        }
        input:focus,select:focus,textarea:focus { border-color:var(--primary); }
        textarea { resize:vertical; min-height:90px; }
        .form-actions { display:flex; gap:.75rem; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--border); }
        .search-bar { display:flex; gap:.75rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .search-input { flex:1; min-width:200px; display:flex; align-items:center; gap:.5rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.55rem .875rem; }
        .search-input i { color:var(--muted); }
        .search-input input { background:none; border:none; color:var(--text); font-size:.875rem; outline:none; flex:1; }
        .alert { padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem; display:flex; align-items:flex-start; gap:.6rem; }
        .alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80}
        .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171}
        .alert-warning{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);color:#fbbf24}
        .pagination-wrapper { margin-top:1rem; display:flex; justify-content:flex-end; }
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); }
        .empty-state i { font-size:3rem; margin-bottom:1rem; opacity:.4; display:block; }
        .empty-state h3 { color:var(--text); margin-bottom:.5rem; }
        @media(max-width:768px){ .sidebar{transform:translateX(-100%);transition:transform .25s} .sidebar.open{transform:translateX(0)} .main{margin-left:0} }
    </style>
    @stack('styles')
</head>
<body>
    @include('partials.sidebar')
    <div class="main">
        <div class="topbar">
            <div>
                <div class="topbar-title">@yield('title','Dashboard')</div>
                @hasSection('breadcrumb')<div class="topbar-breadcrumb">@yield('breadcrumb')</div>@endif
            </div>
            <div class="topbar-actions">@yield('topbar-actions')</div>
        </div>
        <div class="content">
            @include('partials.alerts')

            @auth
            @if(auth()->user()->must_change_password && !session('pw_notice_dismissed'))
            <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.4);border-radius:10px;padding:.875rem 1.1rem;margin-bottom:1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                <span style="color:#fbbf24;font-size:.875rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong>Action required:</strong> Please change your password — you are using a temporary password.
                    @if(Route::has('profile.edit'))
                    <a href="{{ route('profile.edit') }}" style="color:#fbbf24;font-weight:700;text-decoration:underline;">Change now</a>
                    @endif
                </span>
                <form method="POST" action="{{ route('banner.dismiss.password') }}" style="margin:0">
                    @csrf
                    <button type="submit" style="background:none;border:none;color:#fbbf24;cursor:pointer;font-size:.8rem;opacity:.7">
                        <i class="fas fa-xmark"></i> Dismiss
                    </button>
                </form>
            </div>
            @endif
            @endauth

            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
