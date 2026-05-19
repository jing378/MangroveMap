<aside class="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu</div>

        <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('admin.datasets') }}" class="sidebar-item {{ request()->routeIs('admin.datasets') ? 'active' : '' }}">
            <i class="bi bi-folder-check"></i>
            <span>Dataset Management</span>
        </a>

        <a href="{{ route('admin.models') }}" class="sidebar-item {{ request()->routeIs('admin.models') ? 'active' : '' }}">
            <i class="bi bi-robot"></i>
            <span>Model Training</span>
        </a>

        <a href="{{ route('admin.users') }}" class="sidebar-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
    </div>
</aside>