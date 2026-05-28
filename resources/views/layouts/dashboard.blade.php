<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventory System') — InventoryPro</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg:#0f172a; --surface:#1e293b; --surface2:#263348; --border:#334155;
            --text:#f1f5f9; --muted:#94a3b8;
            --primary:#6366f1; --primary-h:#4f46e5;
            --success:#22c55e; --danger:#ef4444; --warning:#f59e0b; --info:#38bdf8;
            --sidebar-w:240px;
        }
        html { scroll-behavior: smooth; }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; height:100vh; overflow-y:auto; z-index:100; transition:transform .25s ease; }
        .sidebar-header { display:flex; align-items:center; gap:.75rem; padding:1.25rem 1rem; border-bottom:1px solid var(--border); color:var(--primary); font-weight:700; font-size:1.1rem; }
        .sidebar-header i { font-size:1.4rem; }
        .sidebar-nav { flex:1; padding:.75rem 0; }
        .nav-section-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); padding:.75rem 1rem .25rem; }
        .nav-item { display:flex; align-items:center; gap:.65rem; padding:.6rem 1rem; color:var(--muted); text-decoration:none; font-size:.875rem; transition:all .15s; }
        .nav-item:hover { background:rgba(99,102,241,.12); color:var(--text); }
        .nav-item.active { background:rgba(99,102,241,.2); color:var(--primary); font-weight:600; border-right:3px solid var(--primary); }
        .nav-item i { width:18px; text-align:center; }

        /* ── SIDEBAR FOOTER ── */
        .sidebar-footer { border-top:1px solid var(--border); padding:1rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
        .user-info { display:flex; align-items:center; gap:.6rem; overflow:hidden; }
        .avatar { width:32px; height:32px; background:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.875rem; flex-shrink:0; }
        .user-name { font-size:.8rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .user-role { font-size:.7rem; color:var(--muted); }
        .btn-logout { background:none; border:1px solid var(--border); color:var(--muted); padding:.4rem .6rem; border-radius:6px; cursor:pointer; transition:all .15s; }
        .btn-logout:hover { border-color:var(--danger); color:var(--danger); }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:.875rem 1.5rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar-title { font-size:1.1rem; font-weight:700; }
        .topbar-breadcrumb { font-size:.8rem; color:var(--muted); margin-top:.1rem; }
        .topbar-actions { display:flex; align-items:center; gap:.5rem; }
        .content { padding:1.5rem; flex:1; }

        /* ── CARDS ── */
        .card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; }
        .card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; }
        .card-title { font-size:1rem; font-weight:700; }

        /* ── STAT CARDS ── */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; display:flex; align-items:center; gap:1rem; }
        .stat-icon { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0; }
        .stat-icon.purple { background:rgba(99,102,241,.15); color:var(--primary); }
        .stat-icon.green  { background:rgba(34,197,94,.15);  color:var(--success); }
        .stat-icon.amber  { background:rgba(245,158,11,.15); color:var(--warning); }
        .stat-icon.sky    { background:rgba(56,189,248,.15); color:var(--info);    }
        .stat-icon.red    { background:rgba(239,68,68,.15);  color:var(--danger);  }
        .stat-value { font-size:1.6rem; font-weight:800; line-height:1; }
        .stat-label { font-size:.8rem; color:var(--muted); margin-top:.25rem; }

        /* ── TABLES ── */
        .table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:.875rem; }
        thead th { padding:.75rem 1rem; text-align:left; color:var(--muted); font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
        tbody td { padding:.85rem 1rem; border-bottom:1px solid rgba(51,65,85,.5); vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr:hover { background:rgba(99,102,241,.04); }

        /* ── BADGES ── */
        .badge { display:inline-flex; align-items:center; gap:.3rem; padding:.2rem .6rem; border-radius:999px; font-size:.75rem; font-weight:600; }
        .badge-green  { background:rgba(34,197,94,.15);  color:#4ade80; }
        .badge-red    { background:rgba(239,68,68,.15);  color:#f87171; }
        .badge-amber  { background:rgba(245,158,11,.15); color:#fbbf24; }
        .badge-sky    { background:rgba(56,189,248,.15); color:#38bdf8; }
        .badge-purple { background:rgba(99,102,241,.15); color:#818cf8; }
        .badge-gray   { background:rgba(148,163,184,.15);color:#94a3b8; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem 1rem; border-radius:8px; font-size:.875rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; transition:all .15s; }
        .btn-primary   { background:var(--primary);  color:#fff; } .btn-primary:hover   { background:var(--primary-h); }
        .btn-success   { background:#166534; color:#4ade80; }      .btn-success:hover   { background:#14532d; }
        .btn-danger    { background:#450a0a; color:#f87171; }      .btn-danger:hover    { background:#3f0e0e; }
        .btn-secondary { background:var(--border); color:var(--text); } .btn-secondary:hover { background:#475569; }
        .btn-sm   { padding:.35rem .65rem; font-size:.8rem; }
        .btn-icon { padding:.4rem; width:32px; height:32px; justify-content:center; }

        /* ── FORMS ── */
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

        /* ── SEARCH BAR ── */
        .search-bar { display:flex; gap:.75rem; margin-bottom:1.25rem; flex-wrap:wrap; }
        .search-input { flex:1; min-width:200px; display:flex; align-items:center; gap:.5rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:.55rem .875rem; }
        .search-input i { color:var(--muted); }
        .search-input input { background:none; border:none; color:var(--text); font-size:.875rem; outline:none; flex:1; }

        /* ── ALERTS ── */
        .alert { padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem; display:flex; align-items:flex-start; gap:.6rem; }
        .alert-success { background:rgba(34,197,94,.1);  border:1px solid rgba(34,197,94,.3);  color:#4ade80; }
        .alert-error   { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3);  color:#f87171; }
        .alert-warning { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); color:#fbbf24; }

        /* ── PAGINATION ── */
        .pagination-wrapper { margin-top:1rem; display:flex; justify-content:flex-end; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); }
        .empty-state i { font-size:3rem; margin-bottom:1rem; opacity:.4; display:block; }
        .empty-state h3 { color:var(--text); margin-bottom:.5rem; }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:var(--primary); }

        /* ── MOBILE ── */
        .mobile-toggle { display:none; position:fixed; top:.875rem; left:.875rem; z-index:200; background:var(--surface); border:1px solid var(--border); color:var(--text); width:38px; height:38px; border-radius:8px; cursor:pointer; align-items:center; justify-content:center; font-size:1rem; }
        @media(max-width:768px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .main { margin-left:0; }
            .mobile-toggle { display:flex; }
            .topbar { padding-left:3.5rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

{{-- Mobile hamburger --}}
<button class="mobile-toggle" id="sidebarToggle" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>

{{-- Sidebar --}}
@include('partials.sidebar')

{{-- Main --}}
<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">@yield('title', 'Dashboard')</div>
            @hasSection('breadcrumb')
                <div class="topbar-breadcrumb">@yield('breadcrumb')</div>
            @endif
        </div>
        <div class="topbar-actions">@yield('topbar-actions')</div>
    </div>

    <div class="content">
        @include('partials.alerts')
        @yield('content')
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggle  = document.getElementById('sidebarToggle');
    toggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (window.innerWidth <= 768 && sidebar?.classList.contains('open')
            && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
</script>

@stack('scripts')
</body>
</html>