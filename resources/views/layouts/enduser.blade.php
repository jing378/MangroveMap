<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard - MangroveMap')</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

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
            z-index: 2000;
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

        .sidebar-notification-icon {
            position: relative;
        }

        .sidebar-notification-badge {
            position: absolute;
            top: -7px;
            right: -10px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 17px;
            height: 17px;
            padding: 0 4px;
            border: 2px solid #fff;
            border-radius: 9px;
            background: #ef4444;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
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

        .sidebar-item.has-unread {
            font-weight: 800;
        }

        .sidebar-logout {
            width: calc(100% - 16px);
            border: 0;
            background: transparent;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
            color: #b83828;
        }

        .sidebar-logout-form {
            margin-top: auto !important;
            padding-top: 16px;
            border-top: 1px solid #f0d8d4;
        }

        .sidebar-logout:hover {
            background: #fdf0ee;
            color: #a52f21;
            border-left-color: #d04030;
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

        .content.content-flush {
            padding: 0 !important;
            overflow: hidden !important;
            display: flex;
            flex-direction: column;
            height: 100%;
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
                z-index: 1900;
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
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="logo">
                <div class="logo-mark"><i class="bi bi-leaf"></i></div>
                <span>MangroveMap</span>
            </div>
        </div>

        <div class="header-right">
        </div>
    </header>

    <div class="container">
        <!-- End User Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-section">
                <div class="sidebar-title">MAIN</div>
                <a href="{{ auth()->user()->isExpert() ? route('expert.dashboard') : route('dashboard') }}"
                    class="sidebar-item {{ request()->routeIs('dashboard') || request()->routeIs('expert.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ auth()->user()->isExpert() ? route('expert.map') : route('map') }}"
                    class="sidebar-item {{ request()->routeIs('map') || request()->routeIs('expert.map') ? 'active' : '' }}">
                    <i class="bi bi-map"></i>
                    <span>Map</span>
                </a>
                <a href="{{ auth()->user()->isExpert() ? route('expert.delineate') : route('delineate') }}"
                    class="sidebar-item {{ request()->routeIs('delineate') || request()->routeIs('expert.delineate') || request()->routeIs('delineations.*') ? 'active' : '' }}">
                    <i class="bi bi-pencil-square"></i>
                    <span>Delineate</span>
                </a>
                <a href="{{ route('delineation.index') }}"
                    class="sidebar-item {{ request()->routeIs('delineation.*') ? 'active' : '' }}">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Upload Image</span>
                </a>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">ACCOUNT</div>
                <a href="{{ route('profile.show') }}"
                    class="sidebar-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
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
            </div>
            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
                @csrf
                <button type="submit" class="sidebar-item sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </button>
            </form>
        </aside>

        <div class="main-content">
            <div class="content @yield('content-class')">
                @yield('content')
            </div>
        </div>
    </div>

    @yield('scripts')
    @stack('scripts')

    <script>
        (function () {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.remove('active');
            }

            const notificationToggle = document.getElementById('notificationToggle');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const profileToggle = document.getElementById('profileToggle');
            const profileDropdown = document.getElementById('profileDropdown');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');

            if (notificationToggle && notificationDropdown) {
                notificationToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('active');
                    profileDropdown?.classList.remove('active');
                    sidebar?.classList.remove('active');
                });

                document.addEventListener('click', function (e) {
                    if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
                        notificationDropdown.classList.remove('active');
                    }
                });

                notificationDropdown.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            if (profileToggle && profileDropdown) {
                profileToggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');
                    notificationDropdown?.classList.remove('active');
                    sidebar?.classList.remove('active');
                });

                document.addEventListener('click', function (e) {
                    if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('active');
                    }
                });

                profileDropdown.addEventListener('click', function (e) {
                    e.stopPropagation();
                });
            }

            if (mobileMenuBtn && sidebar) {
                mobileMenuBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    notificationDropdown?.classList.remove('active');
                    profileDropdown?.classList.remove('active');
                });

                document.addEventListener('click', function (e) {
                    if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                        sidebar.classList.remove('active');
                    }
                });
            }

            document.querySelectorAll('.sidebar-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    sidebar?.classList.remove('active');
                });
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) {
                    sidebar?.classList.remove('active');
                }
            });
        })();
    </script>
</body>

</html>