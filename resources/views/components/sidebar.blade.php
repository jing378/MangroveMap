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

        <a href="{{ route('admin.annual-observations.create') }}" class="sidebar-item {{ request()->routeIs('admin.annual-observations.*') ? 'active' : '' }}">
            <i class="bi bi-calendar2-check"></i>
            <span>Annual Observations</span>
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

    @php($currentUser = auth()->user())
    <div class="sidebar-profile-footer">
        <a href="{{ route('profile.show') }}" class="sidebar-profile-link" aria-label="View profile for {{ $currentUser->name }}">
            <span class="sidebar-profile-avatar">
                @if($currentUser->profile_image)
                    <img src="{{ asset('storage/' . $currentUser->profile_image) }}" alt="{{ $currentUser->name }}'s profile photo">
                @else
                    {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                @endif
            </span>
            <span class="sidebar-profile-details">
                <span class="sidebar-profile-name">{{ $currentUser->name }}</span>
                <span class="sidebar-profile-type">{{ $currentUser->isResident() ? 'Resident' : ucfirst($currentUser->role) }}</span>
                <span class="sidebar-profile-email">{{ $currentUser->email }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-profile-logout" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>