<aside class="sidebar">
    <div class="sidebar-section">
        <div class="sidebar-title">Menu</div>

        <a href="{{ route('admin.dashboard') }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('admin.models') }}" class="sidebar-item {{ request()->routeIs('admin.models') ? 'active' : '' }}">
            <i class="bi bi-robot"></i>
            <span>Model Training</span>
        </a>

        <a href="{{ route('admin.users') }}" class="sidebar-item {{ request()->routeIs('admin.users') ? 'active' : '' }}">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>

        <a href="{{ route('admin.map-layers.index') }}" class="sidebar-item {{ request()->routeIs('admin.map-layers.*') ? 'active' : '' }}">
            <i class="bi bi-layers"></i>
            <span>Map Layers</span>
        </a>

        <a href="{{ route('admin.activity-logs.index') }}" class="sidebar-item {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text"></i>
            <span>Activity Logs</span>
        </a>

    </div>

    <div class="sidebar-section">
        <div class="sidebar-title">Account</div>

        <a href="{{ route('profile.show') }}" class="sidebar-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="bi bi-person"></i>
            <span>My Profile</span>
        </a>

        @php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
        <a href="{{ route('notifications.index') }}"
            id="sidebarNotificationsLink"
            class="sidebar-item {{ request()->routeIs('notifications.*') ? 'active' : '' }} {{ $unreadNotificationCount > 0 ? 'has-unread' : '' }}">
            <span class="sidebar-notification-icon">
                <i class="bi bi-bell"></i>
                @if($unreadNotificationCount > 0)
                    <span class="sidebar-notification-badge" aria-label="{{ $unreadNotificationCount }} unread notifications">{{ $unreadNotificationCount }}</span>
                @endif
            </span>
            <span>Notifications</span>
        </a>

        <a href="{{ route('guide') }}" class="sidebar-item {{ request()->routeIs('guide') ? 'active' : '' }}">
            <i class="bi bi-question-circle"></i>
            <span>Help &amp; Guide</span>
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
                <span class="sidebar-profile-type">{{ $currentUser->roleLabel() }}</span>
                <span class="sidebar-profile-email">{{ $currentUser->email }}</span>
            </span>
        </a>
        <form method="POST" action="{{ route('logout') }}" data-confirm="Log out?" data-confirm-message="You will need to log in again to continue." data-confirm-label="Log out" data-confirm-tone="danger" data-confirm-icon="bi-box-arrow-right" data-busy-label="Logging out...">
            @csrf
            <button type="submit" class="sidebar-profile-logout" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>