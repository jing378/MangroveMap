<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard - MangroveMap')</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            font-family: 'Manrope', system-ui, -apple-system, Segoe UI, sans-serif;
            background: #f5f7f6;
            color: #1a2e1a;
            overflow: hidden;
        }

        .header {
            height: 60px;
            background: #fff;
            border-bottom: 1px solid #e0e8e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 18px;
            color: #1a2e1a;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-mark {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .mobile-menu-btn {
            display: none;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: #1a2e1a;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
        }

        .mobile-menu-btn:hover {
            background: #f5f7f6;
            color: #1e9e62;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            height: 100%;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-profile {
            flex: 1;
            text-align: right;
        }

        .admin-name {
            font-weight: 600;
            font-size: 13px;
            color: #1a2e1a;
        }

        .admin-role {
            font-size: 11px;
            color: #7a9a7a;
            margin-top: 2px;
        }

        .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e8e0;
            flex-shrink: 0;
        }

        .profile-dropdown-wrapper {
            position: relative;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .profile-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.15s;
            background: transparent;
            border: none;
            font-family: 'Manrope', sans-serif;
        }

        .profile-toggle:hover {
            background: #f5f7f6;
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border: 1px solid #e0e8e0;
            border-radius: 10px;
            min-width: 220px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            display: none;
            z-index: 999;
            overflow: hidden;
        }

        .profile-dropdown.active {
            display: block;
        }

        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e0e8e0;
            background: #f5f7f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-header-image {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #d4e0d4;
        }

        .dropdown-header-text {
            flex: 1;
        }

        .dropdown-header-name {
            font-weight: 600;
            font-size: 12px;
            color: #1a2e1a;
        }

        .dropdown-header-email {
            font-size: 10px;
            color: #7a9a7a;
            margin-top: 2px;
        }

        .dropdown-menu {
            padding: 8px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            color: #3a5a3a;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: 'Manrope', sans-serif;
        }

        .dropdown-item:hover {
            background: #f5f7f6;
            color: #1e9e62;
        }

        .dropdown-item i {
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-divider {
            height: 1px;
            background: #e0e8e0;
            margin: 8px 0;
        }

        .dropdown-item.danger {
            color: #d04030;
        }

        .dropdown-item.danger:hover {
            background: rgba(208, 64, 48, 0.08);
            color: #b83828;
        }

        .container {
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: #fff;
            border-right: 1px solid #e0e8e0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            padding: 20px 0;
        }

        .sidebar-section {
            margin-bottom: 24px;
        }

        .sidebar-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #9ab0a0;
            padding: 8px 16px;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: #6a8a6a;
            transition: all 0.15s;
            border-left: 3px solid transparent;
            margin: 0 8px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
        }

        .sidebar-item i {
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-item:hover {
            background: #f5f7f6;
            color: #1a2e1a;
        }

        .sidebar-item.active {
            background: #edf7f2;
            color: #1e9e62;
            border-left-color: #1e9e62;
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .content {
            flex: 1;
            overflow-y: auto;
            padding: 28px 32px;
        }

        .content::-webkit-scrollbar {
            width: 6px;
        }

        .content::-webkit-scrollbar-thumb {
            background: #d4e0d4;
            border-radius: 3px;
        }

        .bi {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: inherit;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 16px;
                height: 56px;
            }

            .header-left {
                gap: 12px;
            }

            .admin-info {
                display: none;
            }

            .admin-profile {
                display: none;
            }

            .profile-toggle {
                padding: 6px;
                gap: 0;
            }

            .mobile-menu-btn {
                display: flex !important;
                align-items: center;
                justify-content: center;
                background: transparent;
                border: none;
                cursor: pointer;
                font-size: 20px;
                color: #1a2e1a;
                padding: 8px;
                border-radius: 6px;
                transition: all 0.2s;
            }

            .mobile-menu-btn:hover {
                background: #f5f7f6;
                color: #1e9e62;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 56px;
                bottom: 0;
                width: 260px;
                z-index: 999;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .content {
                padding: 20px 16px;
            }
        }
    </style>

    @yield('styles')
    @stack('styles')
</head>

<body>
    <header class="header">
        <div class="header-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn" style="display: none;">
                <i class="bi bi-list"></i>
            </button>
            <div class="logo">
                <div class="logo-mark"><i class="bi bi-leaf"></i></div>
                <span>MangroveMap</span>
            </div>
        </div>

        <div class="header-right">
            @include('components.notification-bell')

            <div class="profile-dropdown-wrapper">
                <button class="profile-toggle" id="profileToggle">
                    @if(auth()->user()->profile_image)
                    <img src="{{ asset('storage/' . auth()->user()->profile_image) }}" alt="Profile" class="profile-image">
                    @else
                    <div class="profile-image" style="background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px;">
                        {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                    </div>
                    @endif
                    <div class="admin-profile">
                        <div class="admin-name">{{ auth()->user()->name ?? 'User' }}</div>
                        <div class="admin-role">{{ auth()->user()->isExpert() ? 'Expert' : 'End User' }}</div>
                    </div>
                </button>

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        @if(auth()->user()->profile_image)
                        <img src="{{ asset('storage/' . auth()->user()->profile_image) }}" alt="Profile" class="dropdown-header-image">
                        @else
                        <div class="dropdown-header-image" style="background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                            {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                        </div>
                        @endif
                        <div class="dropdown-header-text">
                            <div class="dropdown-header-name">{{ auth()->user()->name ?? 'User' }}</div>
                            <div class="dropdown-header-email">{{ auth()->user()->email ?? 'user@example.com' }}</div>
                        </div>
                    </div>
                    <div class="dropdown-menu">
                        <a href="{{ route('profile.show') }}" class="dropdown-item">
                            <i class="bi bi-person-circle"></i>
                            <span>View Profile</span>
                        </a>
                        <a href="#" class="dropdown-item">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                        <a href="#" class="dropdown-item">
                            <i class="bi bi-question-circle"></i>
                            <span>Help & Support</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}" style="width: 100%; padding: 0;">
                            @csrf
                            <button type="submit" class="dropdown-item danger">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        @unless(request()->routeIs('notifications.*'))
        <!-- End User Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-title">MAIN</div>
                <a href="{{ auth()->user()->isExpert() ? route('expert.dashboard') : route('dashboard') }}" class="sidebar-item {{ request()->routeIs('dashboard') || request()->routeIs('expert.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>{{ auth()->user()->isExpert() ? 'Review' : 'Dashboard' }}</span>
                </a>
                <a href="{{ route('map') }}" class="sidebar-item {{ request()->routeIs('map') ? 'active' : '' }}">
                    <i class="bi bi-map"></i>
                    <span>Map</span>
                </a>
                <a href="{{ route('classify') }}" class="sidebar-item {{ request()->routeIs('classify*') ? 'active' : '' }}">
                    <i class="bi bi-images"></i>
                    <span>Classify</span>
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">ACCOUNT</div>
                <a href="{{ route('profile.show') }}" class="sidebar-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
                <a href="{{ route('notifications.index') }}" class="sidebar-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                    <i class="bi bi-bell"></i>
                    <span>Notifications</span>
                </a>
            </div>
        </aside>
        @endunless

        <div class="main-content">
            <div class="content">
                @yield('content')
            </div>
        </div>
    </div>

    @yield('scripts')
    @stack('scripts')

    <script>
        // Initialize sidebar - ensure it's closed on page load
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.remove('active');
        }

        // Notification dropdown toggle
        const notificationToggle = document.getElementById('notificationToggle');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationToggle && notificationDropdown) {
            notificationToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                if (typeof profileDropdown !== 'undefined' && profileDropdown) {
                    profileDropdown.classList.remove('active');
                }
            });

            document.addEventListener('click', function(e) {
                if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('active');
                }
            });

            notificationDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Profile dropdown toggle
        const profileToggle = document.getElementById('profileToggle');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileToggle && profileDropdown) {
            profileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('active');
                if (typeof notificationDropdown !== 'undefined' && notificationDropdown) {
                    notificationDropdown.classList.remove('active');
                }
            });

            document.addEventListener('click', function(e) {
                if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.classList.remove('active');
                }
            });

            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }

        // Close sidebar when a navigation link is clicked
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        sidebarItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Sidebar will close due to navigation or explicit removal
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
            });
        });

        // Handle window resize - close sidebar if resizing to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>

</html>