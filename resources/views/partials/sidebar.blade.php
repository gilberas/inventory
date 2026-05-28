<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-boxes-stacked"></i>
        <span>InventoryPro</span>
    </div>

    <nav class="sidebar-nav">

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fas fa-gauge"></i> Dashboard
        </a>

        {{-- CATALOGUE --}}
        <div class="nav-section-label">Catalogue</div>

        <a href="{{ route('products.index') }}"
           class="nav-item {{ request()->routeIs('products.*') ? 'active' : '' }}">
            <i class="fas fa-boxes-stacked"></i> Products
        </a>

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

        {{-- INVENTORY --}}
        <div class="nav-section-label">Inventory</div>

        <a href="{{ route('inventory.index') }}"
           class="nav-item {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
            <i class="fas fa-right-left"></i> Transactions
        </a>

        <a href="{{ route('inventory.stock') }}"
           class="nav-item {{ request()->routeIs('inventory.stock') ? 'active' : '' }}">
            <i class="fas fa-layer-group"></i> Stock on Hand
        </a>

        <a href="{{ route('inventory.adjust') }}"
           class="nav-item {{ request()->routeIs('inventory.adjust') ? 'active' : '' }}">
            <i class="fas fa-sliders"></i> Adjustments
        </a>

        <a href="{{ route('warehouses.index') }}"
           class="nav-item {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
            <i class="fas fa-warehouse"></i> Warehouses
        </a>

        <a href="{{ route('transfers.index') }}"
           class="nav-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
            <i class="fas fa-truck-moving"></i> Transfers
        </a>

        {{-- PURCHASING --}}
        <div class="nav-section-label">Purchasing</div>

        <a href="{{ route('suppliers.index') }}"
           class="nav-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
            <i class="fas fa-truck"></i> Suppliers
        </a>

        <a href="{{ route('purchases.index') }}"
           class="nav-item {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
            <i class="fas fa-file-invoice"></i> Purchase Orders
        </a>

        {{-- SALES --}}
        <div class="nav-section-label">Sales</div>

        <a href="{{ route('customers.index') }}"
           class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i> Customers
        </a>

        <a href="{{ route('sales.index') }}"
           class="nav-item {{ request()->routeIs('sales.*') ? 'active' : '' }}">
            <i class="fas fa-cart-shopping"></i> Sales Orders
        </a>

        {{-- REPORTS --}}
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

        <a href="{{ route('reports.valuation') }}"
           class="nav-item {{ request()->routeIs('reports.valuation') ? 'active' : '' }}">
            <i class="fas fa-coins"></i> Valuation
        </a>

        <a href="{{ route('reports.movements') }}"
           class="nav-item {{ request()->routeIs('reports.movements') ? 'active' : '' }}">
            <i class="fas fa-clock-rotate-left"></i> Movement History
        </a>

        {{-- ADMIN --}}
        @role('Super Admin')
        <div class="nav-section-label">Administration</div>

        <a href="{{ route('users.index') }}"
           class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield"></i> Users & Roles
        </a>
        @endrole

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
// Mobile sidebar toggle
const sidebar = document.getElementById('sidebar');
const toggle  = document.getElementById('sidebarToggle');

function checkMobile() {
    if (window.innerWidth <= 768) {
        toggle.style.display = 'flex';
        toggle.style.alignItems = 'center';
        toggle.style.justifyContent = 'center';
    } else {
        toggle.style.display = 'none';
        sidebar.classList.remove('open');
    }
}

toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
window.addEventListener('resize', checkMobile);
checkMobile();
</script>
