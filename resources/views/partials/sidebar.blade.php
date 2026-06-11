<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-boxes-stacked"></i>
        <span>SmartStock</span>
    </div>

    <nav class="sidebar-nav">

        {{-- Dashboard — all authenticated users --}}
        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-gauge"></i> Dashboard
        </a>

        {{-- ── PLATFORM (super_admin only) ─────────────────────────── --}}
        @role('super_admin')
        <div class="nav-section-label">Platform</div>
        @if(Route::has('admin.tenants.index'))
        <a href="{{ route('admin.tenants.index') }}"
           class="nav-item {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">
            <i class="fas fa-building"></i> Manage Tenants
        </a>
        @endif
        @if(Route::has('admin.plans.index'))
        <a href="{{ route('admin.plans.index') }}"
           class="nav-item {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i class="fas fa-tags"></i> Subscription Plans
        </a>
        @endif
        @endrole

        {{-- ── CATALOGUE (owner, branch_manager, storekeeper, cashier [view only]) --}}
        @canany(['products.manage', 'products.view'])
        <div class="nav-section-label">Catalogue</div>

        <a href="{{ route('products.index') }}"
           class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <i class="fas fa-boxes-stacked"></i> Products
        </a>

        @can('products.manage')
        <a href="{{ route('categories.index') }}"
           class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
            <i class="fas fa-tags"></i> Categories
        </a>

        <a href="{{ route('brands.index') }}"
           class="nav-item {{ request()->routeIs('brands.*') ? 'active' : '' }}">
            <i class="fas fa-copyright"></i> Brands
        </a>

        <a href="{{ route('units.index') }}"
           class="nav-item {{ request()->routeIs('units.*') ? 'active' : '' }}">
            <i class="fas fa-ruler"></i> Units
        </a>
        @endcan
        @endcanany

        {{-- ── INVENTORY (owner, branch_manager, storekeeper) ──────── --}}
        @canany(['inventory.adjust', 'inventory.audit', 'inventory.audit_count'])
        <div class="nav-section-label">Inventory</div>

        <a href="{{ route('inventory.index') }}"
           class="nav-item {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
            <i class="fas fa-right-left"></i> Transactions
        </a>

        <a href="{{ route('inventory.stock') }}"
           class="nav-item {{ request()->routeIs('inventory.stock') ? 'active' : '' }}">
            <i class="fas fa-layer-group"></i> Stock on Hand
        </a>

        @canany(['inventory.adjust'])
        <a href="{{ route('inventory.adjust') }}"
           class="nav-item {{ request()->routeIs('inventory.adjust') ? 'active' : '' }}">
            <i class="fas fa-sliders"></i> Adjustments
        </a>
        @endcanany

        @canany(['inventory.adjust', 'inventory.audit'])
        <a href="{{ route('warehouses.index') }}"
           class="nav-item {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
            <i class="fas fa-warehouse"></i> Warehouses
        </a>
        @endcanany
        @endcanany

        {{-- ── TRANSFERS (owner, branch_manager, storekeeper) ──────── --}}
        @canany(['inventory.transfer', 'inventory.transfer_dispatch'])
        <a href="{{ route('transfers.index') }}"
           class="nav-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
            <i class="fas fa-truck-moving"></i> Transfers
        </a>
        @endcanany

        {{-- ── PURCHASING (owner, branch_manager, storekeeper [receive only]) --}}
        @canany(['purchase_orders.manage', 'purchase_orders.receive'])
        <div class="nav-section-label">Purchasing</div>

        @canany(['suppliers.manage', 'suppliers.view'])
        <a href="{{ route('suppliers.index') }}"
           class="nav-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
            <i class="fas fa-truck"></i> Suppliers
        </a>
        @endcanany

        <a href="{{ route('purchases.index') }}"
           class="nav-item {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="fas fa-file-invoice"></i> Purchase Orders
        </a>

        @if(Route::has('grn.index'))
        <a href="{{ route('grn.index') }}"
           class="nav-item {{ request()->routeIs('grn.*') ? 'active' : '' }}">
            <i class="fas fa-truck-ramp-box"></i> Receive (GRN)
        </a>
        @endif

        @if(Route::has('requisitions.index'))
        @can('purchase_orders.manage')
        <a href="{{ route('requisitions.index') }}"
           class="nav-item {{ request()->routeIs('requisitions.*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i> Requisitions
        </a>
        @endcan
        @endif
        @endcanany

        {{-- ── SALES (owner, branch_manager, cashier) ──────────────── --}}
        @can('sales.process')
        <div class="nav-section-label">Sales</div>

        <a href="{{ route('pos.terminal') }}"
           class="nav-item {{ request()->routeIs('pos.*') ? 'active' : '' }}">
            <i class="fas fa-cash-register"></i> Point of Sale
        </a>

        @canany(['customers.manage', 'customers.manage_own'])
        <a href="{{ route('customers.index') }}"
           class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i> Customers
        </a>
        @endcanany

        @if(Route::has('sales.index'))
        <a href="{{ route('sales.index') }}"
           class="nav-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
            <i class="fas fa-cart-shopping"></i> Sales History
        </a>
        @endif
        @endcan

        {{-- ── EXPENSES (owner, branch_manager, accountant) ──────────── --}}
        @canany(['expenses.manage', 'expenses.view'])
        @if(Route::has('expenses.index'))
        <div class="nav-section-label">Finance</div>
        <a href="{{ route('expenses.index') }}"
           class="nav-item {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave"></i> Expenses
        </a>
        @endif
        @endcanany

        {{-- ── REPORTS (owner, branch_manager [summary], accountant) ── --}}
        @canany(['reports.financial', 'reports.financial_summary', 'reports.vat'])
        <div class="nav-section-label">Reports</div>

        <a href="{{ route('reports.index') }}"
           class="nav-item {{ request()->routeIs('reports.index') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i> Overview
        </a>

        <a href="{{ route('reports.stock') }}"
           class="nav-item {{ request()->routeIs('reports.stock') ? 'active' : '' }}">
            <i class="fas fa-box"></i> Stock Report
        </a>

        <a href="{{ route('reports.low-stock') }}"
           class="nav-item {{ request()->routeIs('reports.low-stock') ? 'active' : '' }}">
            <i class="fas fa-triangle-exclamation"></i> Low Stock
        </a>

        @can('reports.financial')
        <a href="{{ route('reports.valuation') }}"
           class="nav-item {{ request()->routeIs('reports.valuation') ? 'active' : '' }}">
            <i class="fas fa-coins"></i> Valuation
        </a>

        <a href="{{ route('reports.movements') }}"
           class="nav-item {{ request()->routeIs('reports.movements') ? 'active' : '' }}">
            <i class="fas fa-clock-rotate-left"></i> Movement History
        </a>
        @endcan

        @can('reports.vat')
        <a href="{{ route('reports.financial.vat') }}"
           class="nav-item {{ request()->routeIs('reports.financial.*') ? 'active' : '' }}">
            <i class="fas fa-receipt"></i> VAT Report
        </a>
        @endcan
        @endcanany

        {{-- ── EMPLOYEES (owner, branch_manager) ─────────────────── --}}
        @canany(['employees.manage_all', 'employees.manage_branch'])
        @if(Route::has('employees.index'))
        <div class="nav-section-label">HR</div>
        <a href="{{ route('employees.index') }}"
           class="nav-item {{ request()->routeIs('employees.*') ? 'active' : '' }}">
            <i class="fas fa-id-badge"></i> Employees
        </a>
        @endif
        @endcanany

        {{-- ── ADMIN (owner + super_admin) ─────────────────────────── --}}
        @canany(['users.manage_all', 'users.manage_branch', 'system.configure', 'system.configure_tenant'])
        <div class="nav-section-label">Administration</div>

        @canany(['users.manage_all', 'users.manage_branch'])
        <a href="{{ route('users.index') }}"
           class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield"></i> Users & Roles
        </a>
        @endcanany

        @canany(['audit_logs.view_all', 'audit_logs.view_own'])
        <a href="{{ route('audits.index') }}"
           class="nav-item {{ request()->routeIs('audits.*') ? 'active' : '' }}">
            <i class="fas fa-scroll"></i> Audit Logs
        </a>
        @endcanany

        @canany(['system.configure', 'system.configure_tenant'])
        @if(Route::has('settings.index'))
        <a href="{{ route('settings.index') }}"
           class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <i class="fas fa-gear"></i> Settings
        </a>
        @endif
        @endcanany
        @endcanany

    </nav>

    {{-- Footer: user info + logout --}}
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <div class="user-name">{{ auth()->user()->name }}</div>
                <div class="user-role">
                    {{ auth()->user()->getRoleNames()->first() ?? 'User' }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </form>
    </div>
</aside>

{{-- Mobile toggle button --}}
<button class="mobile-toggle" id="sidebarToggle" aria-label="Toggle sidebar"
    style="display:none;position:fixed;top:1rem;left:1rem;z-index:200;background:var(--surface);border:1px solid var(--border);color:var(--text);width:40px;height:40px;border-radius:8px;cursor:pointer;font-size:1.1rem;">
    <i class="fas fa-bars"></i>
</button>

<script>
const sidebar = document.getElementById('sidebar');
const toggle  = document.getElementById('sidebarToggle');
function checkMobile() {
    if (window.innerWidth <= 768) { toggle.style.display='flex'; toggle.style.alignItems='center'; toggle.style.justifyContent='center'; }
    else { toggle.style.display='none'; sidebar.classList.remove('open'); }
}
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
window.addEventListener('resize', checkMobile);
checkMobile();
</script>
