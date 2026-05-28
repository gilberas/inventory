<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'InventoryPro') }} — Smart Inventory Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #0f172a;
            --surface:  #1e293b;
            --surface2: #263348;
            --border:   #334155;
            --text:     #f1f5f9;
            --muted:    #94a3b8;
            --primary:  #6366f1;
            --primary-g:#4f46e5;
            --success:  #22c55e;
            --warning:  #f59e0b;
            --danger:   #ef4444;
            --info:     #38bdf8;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── ANIMATED BACKGROUND ── */
        .bg-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(99,102,241,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99,102,241,.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .bg-orb {
            position: fixed; border-radius: 50%; filter: blur(80px);
            pointer-events: none; z-index: 0; animation: drift 12s ease-in-out infinite;
        }
        .bg-orb-1 { width: 500px; height: 500px; top: -150px; left: -150px; background: rgba(99,102,241,.12); }
        .bg-orb-2 { width: 400px; height: 400px; bottom: -100px; right: -100px; background: rgba(56,189,248,.08); animation-delay: -6s; }
        .bg-orb-3 { width: 300px; height: 300px; top: 40%; left: 50%; background: rgba(34,197,94,.06); animation-delay: -3s; }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(30px, -20px) scale(1.05); }
            66%       { transform: translate(-20px, 15px) scale(.97); }
        }

        /* ── NAVBAR ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: .875rem 2rem;
            background: rgba(15,23,42,.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(99,102,241,.15);
            transition: all .3s;
        }
        .nav-logo { display: flex; align-items: center; gap: .6rem; font-weight: 800; font-size: 1.25rem; color: var(--text); text-decoration: none; }
        .nav-logo .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--info)); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .nav-links { display: flex; align-items: center; gap: .5rem; }
        .nav-link { padding: .45rem 1rem; border-radius: 8px; text-decoration: none; font-size: .875rem; font-weight: 500; transition: all .15s; }
        .nav-link-ghost { color: var(--muted); border: 1px solid transparent; }
        .nav-link-ghost:hover { color: var(--text); background: rgba(255,255,255,.06); }
        .nav-link-outline { color: var(--text); border: 1px solid var(--border); }
        .nav-link-outline:hover { border-color: var(--primary); color: var(--primary); }
        .nav-link-primary { background: var(--primary); color: #fff; border: 1px solid var(--primary); }
        .nav-link-primary:hover { background: var(--primary-g); }

        /* ── SECTIONS ── */
        section { position: relative; z-index: 1; }

        /* ── HERO ── */
        .hero {
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 7rem 2rem 4rem; text-align: center;
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(99,102,241,.12); border: 1px solid rgba(99,102,241,.3);
            color: #a5b4fc; padding: .35rem .9rem; border-radius: 999px;
            font-size: .8rem; font-weight: 600; margin-bottom: 1.75rem;
            animation: fadeUp .8s ease both;
        }
        .hero-badge .dot { width: 7px; height: 7px; background: #a5b4fc; border-radius: 50%; animation: pulse-dot 2s ease infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.7)} }

        .hero h1 {
            font-size: clamp(2.4rem, 6vw, 4.5rem); font-weight: 900;
            line-height: 1.08; letter-spacing: -.03em;
            animation: fadeUp .8s .1s ease both;
        }
        .gradient-text {
            background: linear-gradient(135deg, #6366f1 0%, #38bdf8 50%, #22c55e 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            max-width: 580px; color: var(--muted); font-size: 1.1rem; line-height: 1.65;
            margin: 1.5rem auto 2.5rem;
            animation: fadeUp .8s .2s ease both;
        }

        .hero-cta { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; animation: fadeUp .8s .3s ease both; }

        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .75rem 1.6rem; border-radius: 10px; font-size: .95rem;
            font-weight: 600; text-decoration: none; cursor: pointer; border: none;
            transition: all .2s; position: relative; overflow: hidden;
        }
        .btn::after { content:''; position:absolute; inset:0; background:rgba(255,255,255,0); transition:.2s; }
        .btn:hover::after { background:rgba(255,255,255,.07); }

        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-g)); color: #fff; box-shadow: 0 0 24px rgba(99,102,241,.35); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 0 36px rgba(99,102,241,.5); }
        .btn-ghost { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }

        /* ── LIVE STATS TICKER ── */
        .stats-ticker {
            margin-top: 4rem; animation: fadeUp .8s .4s ease both;
            display: flex; gap: 1px; background: var(--border); border: 1px solid var(--border);
            border-radius: 14px; overflow: hidden;
        }
        .ticker-item {
            flex: 1; padding: 1.1rem 1.5rem;
            background: var(--surface); text-align: center;
            position: relative; overflow: hidden;
        }
        .ticker-item::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--info));
            opacity: 0; transition: opacity .3s;
        }
        .ticker-item:hover::before { opacity: 1; }
        .ticker-val { font-size: 1.8rem; font-weight: 800; line-height: 1; }
        .ticker-label { font-size: .75rem; color: var(--muted); margin-top: .25rem; text-transform: uppercase; letter-spacing: .06em; }

        /* ── LIVE DASHBOARD PREVIEW ── */
        .preview-section { padding: 5rem 2rem; }
        .preview-section .section-label { text-align: center; color: var(--primary); font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; margin-bottom: .75rem; }
        .preview-section h2 { text-align: center; font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 800; margin-bottom: 1rem; }
        .preview-section .section-sub { text-align: center; color: var(--muted); max-width: 520px; margin: 0 auto 3rem; line-height: 1.6; }

        .dashboard-preview {
            max-width: 1100px; margin: 0 auto;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,.5);
        }

        .preview-topbar {
            display: flex; align-items: center; gap: .5rem;
            padding: .875rem 1.25rem;
            background: rgba(15,23,42,.6); border-bottom: 1px solid var(--border);
        }
        .dot-red   { width: 12px; height: 12px; background: #ef4444; border-radius: 50%; }
        .dot-yellow{ width: 12px; height: 12px; background: #f59e0b; border-radius: 50%; }
        .dot-green { width: 12px; height: 12px; background: #22c55e; border-radius: 50%; }
        .preview-url { margin-left: 1rem; background: rgba(99,102,241,.08); border: 1px solid var(--border); color: var(--muted); font-size: .8rem; padding: .3rem .8rem; border-radius: 6px; }

        .preview-body { display: grid; grid-template-columns: 200px 1fr; min-height: 420px; }

        .mini-sidebar { background: rgba(15,23,42,.6); border-right: 1px solid var(--border); padding: .75rem 0; }
        .mini-nav-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .6rem 1rem; font-size: .78rem; color: var(--muted); cursor: default;
        }
        .mini-nav-item.active { color: var(--primary); background: rgba(99,102,241,.12); border-right: 2px solid var(--primary); }
        .mini-nav-item i { width: 14px; font-size: .8rem; }
        .mini-section-label { font-size: .62rem; color: #475569; text-transform: uppercase; padding: .6rem 1rem .2rem; letter-spacing: .08em; }

        .preview-content { padding: 1.25rem; overflow: hidden; }

        .mini-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin-bottom: 1rem; }
        .mini-stat { background: rgba(15,23,42,.5); border: 1px solid var(--border); border-radius: 10px; padding: .875rem; }
        .mini-stat .icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .85rem; margin-bottom: .5rem; }
        .mini-stat .val { font-size: 1.3rem; font-weight: 800; line-height: 1; }
        .mini-stat .lbl { font-size: .68rem; color: var(--muted); margin-top: .2rem; }
        .mini-stat .trend { font-size: .65rem; display: flex; align-items: center; gap: .2rem; margin-top: .3rem; }
        .trend-up { color: var(--success); }
        .trend-dn { color: var(--danger); }

        .mini-charts { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .mini-chart-card { background: rgba(15,23,42,.5); border: 1px solid var(--border); border-radius: 10px; padding: .875rem; }
        .mini-chart-card h4 { font-size: .78rem; font-weight: 700; margin-bottom: .5rem; }

        /* ── FEATURES ── */
        .features { padding: 5rem 2rem; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; max-width: 1100px; margin: 0 auto; }

        .feature-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 16px; padding: 1.75rem;
            transition: all .25s; position: relative; overflow: hidden;
        }
        .feature-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            opacity: 0; transition: opacity .3s;
        }
        .feature-card:hover { transform: translateY(-4px); border-color: rgba(99,102,241,.4); box-shadow: 0 20px 40px rgba(0,0,0,.3); }
        .feature-card:hover::before { opacity: 1; }
        .fc-purple::before { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .fc-teal::before   { background: linear-gradient(90deg, #14b8a6, #38bdf8); }
        .fc-amber::before  { background: linear-gradient(90deg, #f59e0b, #f97316); }
        .fc-green::before  { background: linear-gradient(90deg, #22c55e, #10b981); }
        .fc-red::before    { background: linear-gradient(90deg, #ef4444, #f43f5e); }
        .fc-sky::before    { background: linear-gradient(90deg, #38bdf8, #6366f1); }

        .feat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 1.1rem; }
        .fi-purple { background: rgba(99,102,241,.15);  color: #818cf8; }
        .fi-teal   { background: rgba(20,184,166,.15);  color: #2dd4bf; }
        .fi-amber  { background: rgba(245,158,11,.15);  color: #fbbf24; }
        .fi-green  { background: rgba(34,197,94,.15);   color: #4ade80; }
        .fi-red    { background: rgba(239,68,68,.15);   color: #f87171; }
        .fi-sky    { background: rgba(56,189,248,.15);  color: #38bdf8; }

        .feature-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: .5rem; }
        .feature-card p  { font-size: .875rem; color: var(--muted); line-height: 1.6; }

        /* ── LIVE FLOW ── */
        .flow { padding: 5rem 2rem; background: rgba(30,41,59,.4); }
        .flow-steps { display: flex; justify-content: center; align-items: center; flex-wrap: wrap; gap: 0; max-width: 900px; margin: 0 auto; }
        .flow-step { text-align: center; padding: 1.5rem 1rem; flex: 1; min-width: 130px; }
        .flow-icon { width: 52px; height: 52px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto .75rem; font-size: 1.15rem; border: 2px solid transparent; transition: all .3s; }
        .flow-step:hover .flow-icon { transform: scale(1.15); }
        .flow-step h4 { font-size: .825rem; font-weight: 700; margin-bottom: .3rem; }
        .flow-step p  { font-size: .75rem; color: var(--muted); }
        .flow-arrow { color: var(--border); font-size: 1.25rem; flex-shrink: 0; }

        /* ── COUNTER SECTION ── */
        .counters { padding: 4rem 2rem; }
        .counters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; max-width: 900px; margin: 0 auto; text-align: center; }
        .counter-card { padding: 2rem; }
        .counter-val { font-size: 3rem; font-weight: 900; line-height: 1; }
        .counter-label { color: var(--muted); margin-top: .5rem; font-size: .875rem; }

        /* ── CTA SECTION ── */
        .cta-section {
            padding: 6rem 2rem; text-align: center;
            background: linear-gradient(135deg, rgba(99,102,241,.08) 0%, rgba(56,189,248,.05) 100%);
            border-top: 1px solid var(--border);
        }
        .cta-section h2 { font-size: clamp(1.8rem, 3vw, 2.8rem); font-weight: 800; margin-bottom: 1rem; }
        .cta-section p  { color: var(--muted); max-width: 480px; margin: 0 auto 2.5rem; line-height: 1.6; }

        /* ── FOOTER ── */
        footer { border-top: 1px solid var(--border); padding: 1.5rem 2rem; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 1; }
        footer .brand { font-weight: 700; font-size: .9rem; color: var(--muted); }
        footer small { color: #475569; font-size: .78rem; }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        @media (max-width: 768px) {
            .stats-ticker { flex-direction: column; }
            .preview-body { grid-template-columns: 1fr; }
            .mini-sidebar { display: none; }
            .mini-stats { grid-template-columns: repeat(2, 1fr); }
            .mini-charts { grid-template-columns: 1fr; }
            .flow-arrow { display: none; }
            nav { padding: .75rem 1.25rem; }
        }
    </style>
</head>
<body>

<div class="bg-grid"></div>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>
<div class="bg-orb bg-orb-3"></div>

{{-- ── NAVBAR ── --}}
<nav>
    <a href="/" class="nav-logo">
        <div class="logo-icon"><i class="fas fa-boxes-stacked"></i></div>
        InventoryPro
    </a>
    <div class="nav-links">
        @if (Route::has('login'))
            @auth
                <a href="{{ url('/dashboard') }}" class="nav-link nav-link-primary">
                    <i class="fas fa-gauge"></i> Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="nav-link nav-link-ghost">Log in</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="nav-link nav-link-primary">Get Started</a>
                @endif
            @endauth
        @endif
    </div>
</nav>

{{-- ── HERO ── --}}
<section class="hero">
    <div class="hero-badge">
        <span class="dot"></span>
        Live Inventory Intelligence
    </div>

    <h1>
        Full Control Over<br>
        <span class="gradient-text">Every Item. Every Move.</span>
    </h1>

    <p class="hero-sub">
        A powerful inventory management system built for modern businesses.
        Track stock, manage purchases, close sales — all in one place.
    </p>

    <div class="hero-cta">
        @auth
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                <i class="fas fa-gauge"></i> Go to Dashboard
            </a>
        @else
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Get Started Free
                </a>
            @endif
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="btn btn-ghost">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            @endif
        @endauth
    </div>

    {{-- Live counting stat strip --}}
    <div class="stats-ticker" style="width:100%;max-width:780px;">
        <div class="ticker-item">
            <div class="ticker-val" style="color:#6366f1;" id="t1">0</div>
            <div class="ticker-label">Products Tracked</div>
        </div>
        <div class="ticker-item">
            <div class="ticker-val" style="color:#22c55e;" id="t2">0</div>
            <div class="ticker-label">Orders Processed</div>
        </div>
        <div class="ticker-item">
            <div class="ticker-val" style="color:#f59e0b;" id="t3">0</div>
            <div class="ticker-label">Warehouses Managed</div>
        </div>
        <div class="ticker-item">
            <div class="ticker-val" style="color:#38bdf8;" id="t4">0</div>
            <div class="ticker-label">Reports Generated</div>
        </div>
    </div>
</section>

{{-- ── LIVE DASHBOARD PREVIEW ── --}}
<section class="preview-section reveal">
    <div class="section-label">Live Preview</div>
    <h2>Your Inventory, At a Glance</h2>
    <p class="section-sub">An interactive dashboard designed to surface what matters — instantly.</p>

    <div class="dashboard-preview">
        <div class="preview-topbar">
            <div class="dot-red"></div>
            <div class="dot-yellow"></div>
            <div class="dot-green"></div>
            <div class="preview-url">inventorypro.app/dashboard</div>
        </div>
        <div class="preview-body">
            {{-- Mini sidebar --}}
            <div class="mini-sidebar">
                <div class="mini-section-label">Navigation</div>
                <div class="mini-nav-item active"><i class="fas fa-gauge"></i> Dashboard</div>
                <div class="mini-nav-item"><i class="fas fa-boxes-stacked"></i> Products</div>
                <div class="mini-nav-item"><i class="fas fa-right-left"></i> Inventory</div>
                <div class="mini-nav-item"><i class="fas fa-file-invoice"></i> Purchases</div>
                <div class="mini-nav-item"><i class="fas fa-cart-shopping"></i> Sales</div>
                <div class="mini-section-label" style="margin-top:.5rem;">Reports</div>
                <div class="mini-nav-item"><i class="fas fa-chart-bar"></i> Analytics</div>
                <div class="mini-nav-item"><i class="fas fa-warehouse"></i> Warehouses</div>
            </div>

            {{-- Main preview content --}}
            <div class="preview-content">
                <div class="mini-stats">
                    <div class="mini-stat">
                        <div class="icon fi-purple"><i class="fas fa-boxes-stacked"></i></div>
                        <div class="val" id="pv1">1,248</div>
                        <div class="lbl">Total Products</div>
                        <div class="trend trend-up"><i class="fas fa-arrow-up"></i> +12 this week</div>
                    </div>
                    <div class="mini-stat">
                        <div class="icon fi-amber"><i class="fas fa-triangle-exclamation"></i></div>
                        <div class="val" style="color:var(--warning);" id="pv2">18</div>
                        <div class="lbl">Low Stock</div>
                        <div class="trend trend-dn"><i class="fas fa-arrow-down"></i> Needs reorder</div>
                    </div>
                    <div class="mini-stat">
                        <div class="icon fi-green"><i class="fas fa-cart-shopping"></i></div>
                        <div class="val" style="color:var(--success);" id="pv3">$12,430</div>
                        <div class="lbl">Sales Today</div>
                        <div class="trend trend-up"><i class="fas fa-arrow-up"></i> +8.4%</div>
                    </div>
                    <div class="mini-stat">
                        <div class="icon fi-sky"><i class="fas fa-file-invoice"></i></div>
                        <div class="val" style="color:var(--info);" id="pv4">9</div>
                        <div class="lbl">Pending Orders</div>
                        <div class="trend trend-dn"><i class="fas fa-clock"></i> 3 urgent</div>
                    </div>
                </div>

                <div class="mini-charts">
                    <div class="mini-chart-card">
                        <h4>Monthly Sales</h4>
                        <div id="miniSalesChart"></div>
                    </div>
                    <div class="mini-chart-card">
                        <h4>Stock Status</h4>
                        <div id="miniDonutChart"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── FEATURES ── --}}
<section class="features reveal">
    <div class="section-label" style="text-align:center;color:var(--primary);font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:.75rem;">Everything You Need</div>
    <h2 style="text-align:center;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:800;margin-bottom:3rem;">Built for Real Inventory Operations</h2>

    <div class="features-grid">
        <div class="feature-card fc-purple">
            <div class="feat-icon fi-purple"><i class="fas fa-right-left"></i></div>
            <h3>Smart Inventory Engine</h3>
            <p>Every stock movement — purchases, sales, transfers, adjustments — creates a transaction record. Full history, never lose track.</p>
        </div>
        <div class="feature-card fc-teal">
            <div class="feat-icon fi-teal"><i class="fas fa-warehouse"></i></div>
            <h3>Multi-Warehouse Support</h3>
            <p>Manage multiple warehouses, bin locations, and shelf tracking. Transfer stock between locations with approval workflows.</p>
        </div>
        <div class="feature-card fc-amber">
            <div class="feat-icon fi-amber"><i class="fas fa-truck"></i></div>
            <h3>Purchase Order Workflow</h3>
            <p>From purchase request to goods received — with approval gates, supplier invoicing, and automatic stock updates on receipt.</p>
        </div>
        <div class="feature-card fc-green">
            <div class="feat-icon fi-green"><i class="fas fa-cart-shopping"></i></div>
            <h3>Sales & Dispatch</h3>
            <p>Create sales orders, check live stock availability, dispatch with one click. Stock deducted automatically on shipment.</p>
        </div>
        <div class="feature-card fc-red">
            <div class="feat-icon fi-red"><i class="fas fa-triangle-exclamation"></i></div>
            <h3>Expiry & Batch Tracking</h3>
            <p>Track products by batch number and expiry date. Get alerts for items expiring within 30 days before they become a loss.</p>
        </div>
        <div class="feature-card fc-sky">
            <div class="feat-icon fi-sky"><i class="fas fa-chart-bar"></i></div>
            <h3>Reports & Audit Logs</h3>
            <p>Stock valuation, low-stock reports, movement history, user activity logs. Complete audit trail for every action taken.</p>
        </div>
    </div>
</section>

{{-- ── SYSTEM FLOW ── --}}
<section class="flow reveal">
    <div class="section-label" style="text-align:center;color:var(--primary);font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:.75rem;">Procurement Flow</div>
    <h2 style="text-align:center;font-size:clamp(1.4rem,3vw,2rem);font-weight:800;margin-bottom:2.5rem;">From Order to Stock, Automated</h2>

    <div class="flow-steps">
        <div class="flow-step">
            <div class="flow-icon" style="background:rgba(99,102,241,.15);border-color:rgba(99,102,241,.3);color:#818cf8;">
                <i class="fas fa-file-pen"></i>
            </div>
            <h4>Purchase Request</h4>
            <p>Raise a request for items needed</p>
        </div>
        <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="flow-step">
            <div class="flow-icon" style="background:rgba(56,189,248,.15);border-color:rgba(56,189,248,.3);color:#38bdf8;">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h4>Purchase Order</h4>
            <p>Convert to official PO for supplier</p>
        </div>
        <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="flow-step">
            <div class="flow-icon" style="background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.3);color:#fbbf24;">
                <i class="fas fa-circle-check"></i>
            </div>
            <h4>Approval</h4>
            <p>Manager reviews and approves PO</p>
        </div>
        <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="flow-step">
            <div class="flow-icon" style="background:rgba(20,184,166,.15);border-color:rgba(20,184,166,.3);color:#2dd4bf;">
                <i class="fas fa-truck-ramp-box"></i>
            </div>
            <h4>Goods Received</h4>
            <p>Record actual quantities received</p>
        </div>
        <div class="flow-arrow"><i class="fas fa-chevron-right"></i></div>
        <div class="flow-step">
            <div class="flow-icon" style="background:rgba(34,197,94,.15);border-color:rgba(34,197,94,.3);color:#4ade80;">
                <i class="fas fa-boxes-stacked"></i>
            </div>
            <h4>Stock Updated</h4>
            <p>Inventory automatically increased</p>
        </div>
    </div>
</section>

{{-- ── CTA ── --}}
<section class="cta-section reveal">
    <h2>Ready to Take Control of<br><span class="gradient-text">Your Inventory?</span></h2>
    <p>Set up your system in minutes. No complex configuration. Start with products and warehouses, the engine does the rest.</p>
    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        @auth
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                <i class="fas fa-gauge"></i> Go to Dashboard
            </a>
        @else
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Create Your Account
                </a>
            @endif
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="btn btn-ghost">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </a>
            @endif
        @endauth
    </div>
</section>

{{-- ── FOOTER ── --}}
<footer>
    <div class="brand"><i class="fas fa-boxes-stacked" style="color:var(--primary);margin-right:.4rem;"></i> InventoryPro</div>
    <small>Built with Laravel {{ app()->version() }} &mdash; © {{ date('Y') }} {{ config('app.name') }}</small>
</footer>

<script>
// ── Animated counters ──────────────────────────────────────────
function animateCount(el, target, suffix='', duration=1800) {
    let start = 0;
    const step = Math.ceil(target / (duration / 16));
    const timer = setInterval(() => {
        start += step;
        if (start >= target) { start = target; clearInterval(timer); }
        el.textContent = start.toLocaleString() + suffix;
    }, 16);
}

window.addEventListener('load', () => {
    setTimeout(() => {
        animateCount(document.getElementById('t1'), 1248);
        animateCount(document.getElementById('t2'), 8432);
        animateCount(document.getElementById('t3'), 24);
        animateCount(document.getElementById('t4'), 3167);
    }, 400);
});

// ── Reveal on scroll ──────────────────────────────────────────
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// ── ApexCharts preview ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Sales area chart
    new ApexCharts(document.getElementById('miniSalesChart'), {
        chart:  { type: 'area', height: 130, sparkline: { enabled: true }, animations: { easing: 'easeinout', speed: 1000 } },
        series: [{ name: 'Sales', data: [3200, 4100, 3800, 5200, 4800, 6100, 5700] }],
        stroke: { curve: 'smooth', width: 2 },
        fill:   { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: 0 } },
        colors: ['#6366f1'],
        xaxis:  { categories: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] },
        tooltip: { theme: 'dark' },
        grid:   { show: false },
    }).render();

    // Stock donut chart
    new ApexCharts(document.getElementById('miniDonutChart'), {
        chart:   { type: 'donut', height: 130 },
        series:  [68, 18, 14],
        labels:  ['In Stock', 'Low Stock', 'Out of Stock'],
        colors:  ['#22c55e', '#f59e0b', '#ef4444'],
        legend:  { position: 'bottom', fontSize: '10px', labels: { colors: '#94a3b8' } },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark' },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();

    // Animate preview values dynamically
    setInterval(() => {
        const el = document.getElementById('pv3');
        if (el) {
            const base = 12000 + Math.floor(Math.random() * 800);
            el.textContent = '$' + base.toLocaleString();
        }
        const el2 = document.getElementById('pv4');
        if (el2) {
            el2.textContent = Math.floor(Math.random() * 5) + 7;
        }
    }, 3000);
});
</script>
</body>
</html>