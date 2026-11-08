<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin - MangroveMap')</title>
    <link rel="icon" type="image/png" href="/favicon-round.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    @include('partials.pwa-head')
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
            background: var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-1a2e1a, #1a2e1a);
            overflow: hidden;
        }

        .header {
            height: 60px;
            background: var(--bg-ffffff, #fff);
            border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
            color: var(--fg-1a2e1a, #1a2e1a);
            text-decoration: none;
            border-radius: 8px;
            transition: opacity 0.15s ease;
        }

        .logo:hover {
            opacity: 0.8;
        }

        .logo:focus-visible {
            outline: 3px solid rgba(30, 158, 98, 0.35);
            outline-offset: 3px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo-mark img {
            width: 100%;
            height: 100%;
            display: block;
            border-radius: inherit;
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
            color: var(--fg-1a2e1a, #1a2e1a);
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
        }

        .mobile-menu-btn:hover {
            background: var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
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
            color: var(--fg-1a2e1a, #1a2e1a);
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
            border: 2px solid var(--bd-e0e8e0, #e0e8e0);
            flex-shrink: 0;
        }

        .profile-dropdown-wrapper {
            position: relative;
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
            background: var(--bg-f5f7f6, #f5f7f6);
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--bg-ffffff, #fff);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
            border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
            background: var(--bg-f5f7f6, #f5f7f6);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dropdown-header-image {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        }

        .dropdown-header-text {
            flex: 1;
        }

        .dropdown-header-name {
            font-weight: 600;
            font-size: 12px;
            color: var(--fg-1a2e1a, #1a2e1a);
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
            color: var(--fg-3a5a3a, #3a5a3a);
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
            background: var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .dropdown-item i {
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--bg-e0e8e0, #e0e8e0);
            margin: 8px 0;
        }

        .dropdown-item.danger {
            color: var(--fg-d04030, #d04030);
        }

        .dropdown-item.danger:hover {
            background: rgba(208, 64, 48, 0.08);
            color: var(--fg-b83828, #b83828);
        }

        .logout-btn {
            padding: 8px 16px;
            font-family: 'Manrope', sans-serif;
            font-size: 12px;
            font-weight: 600;
            color: var(--fg-d04030, #d04030);
            border: 1.5px solid #d04030;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .logout-btn:hover {
            background: rgba(208, 64, 48, 0.08);
            color: var(--fg-b83828, #b83828);
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
            background: var(--bg-ffffff, #fff);
            border-right: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
            color: var(--fg-6a8a6a, #6a8a6a);
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
            border: 2px solid var(--bd-ffffff, #fff);
            border-radius: 9px;
            background: #ef4444;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
        }

        .sidebar-item.has-unread {
            font-weight: 800;
        }

        .sidebar-item:hover {
            background: var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-1a2e1a, #1a2e1a);
        }

        .sidebar-item.active {
            background: #1d593d;
            color: #fff;
            border-left-color: var(--bd-a9e3bf, #a9e3bf);
            font-weight: 700;
            box-shadow: 0 3px 9px rgba(29, 89, 61, 0.18);
        }

        .sidebar-item.active i {
            color: #bcebd0;
        }

        .sidebar-logout {
            width: calc(100% - 16px);
            border: 0;
            background: transparent;
            font-family: inherit;
            text-align: left;
            cursor: pointer;
            color: var(--fg-b83828, #b83828);
        }

        .sidebar-logout-form {
            margin-top: auto !important;
            padding-top: 16px;
            border-top: 1px solid var(--bd-f0d8d4, #f0d8d4);
        }

        .sidebar-logout:hover {
            background: var(--bg-fdf0ee, #fdf0ee);
            color: var(--fg-a52f21, #a52f21);
            border-left-color: #d04030;
        }

        .sidebar-profile-footer {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: auto;
            padding: 16px 12px 0;
            border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
        }

        .sidebar-profile-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
            padding: 8px;
            color: inherit;
            text-decoration: none;
            border-radius: 8px;
        }

        .sidebar-profile-link:hover {
            background: var(--bg-f5f7f6, #f5f7f6);
        }

        .sidebar-profile-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex: 0 0 40px;
            overflow: hidden;
            border-radius: 50%;
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-1e9e62, #1e9e62);
            font-size: 15px;
            font-weight: 700;
        }

        .sidebar-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-profile-details {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .sidebar-profile-name,
        .sidebar-profile-email {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-profile-name {
            color: var(--fg-1a2e1a, #1a2e1a);
            font-size: 12px;
            font-weight: 700;
        }

        .sidebar-profile-email,
        .sidebar-profile-type {
            margin-top: 2px;
            color: #7a9a7a;
            font-size: 10px;
        }

        .sidebar-profile-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--fg-b83828, #b83828);
            cursor: pointer;
            font-size: 17px;
        }

        .sidebar-profile-logout:hover {
            background: var(--bg-fdf0ee, #fdf0ee);
            color: var(--fg-a52f21, #a52f21);
        }

        .sidebar-version {
            margin-top: auto;
            padding: 0 16px 8px;
            color: #7a9a7a;
            font-size: 10px;
            text-align: center;
        }

        .sidebar-version + .sidebar-profile-footer {
            margin-top: 0;
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
                color: var(--fg-1a2e1a, #1a2e1a);
                padding: 8px;
                border-radius: 6px;
                transition: all 0.2s;
            }

            .mobile-menu-btn:hover {
                background: var(--bg-f5f7f6, #f5f7f6);
                color: var(--fg-1e9e62, #1e9e62);
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

    <style>
        /* Admin dropdowns: one chevron set a little in from the right edge instead of the
           browser's arrow, which sits flush against the border (far off on wide/full-width selects).
           The extra :not()s give this rule enough weight over the per-page select styles.
           AI Models' select (.upload-field) draws its own icon, so it is left alone. */
        html body select:not([multiple]):not([size]):not(.upload-field select) {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 34px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%236a8a6a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M3.5 6l4.5 4.5L12.5 6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px 12px;
            cursor: pointer;
        }

        html body select:not([multiple]):not([size]):not(.upload-field select)::-ms-expand {
            display: none;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.scrollbar-styles')
    @include('partials.action-feedback')
</head>

<body>
    <header class="header">
        <div class="header-left">
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" style="display: none;" aria-label="Open navigation menu">
                <i class="bi bi-list"></i>
                @include('partials.mobile-menu-badge')
            </button>
            {{-- Goes to the user's own dashboard (reloads it when already there). --}}
            <a href="{{ auth()->user()->homeRoute() }}" class="logo" aria-label="MangroveMap, go to your dashboard" title="Go to your dashboard">
                <div class="logo-mark"><img src="/icon-192.png" alt=""></div>
                <span>MangroveMap{{ auth()->user()->isAdmin() ? ' Admin' : '' }}</span>
            </a>
        </div>

        <div class="header-right">
            @include('partials.theme-toggle')
        </div>
    </header>

    <div class="container">
        @include('components.sidebar')

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
            notificationToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                profileDropdown?.classList.remove('active');
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

        // Profile dropdown toggle
        const profileToggle = document.getElementById('profileToggle');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileToggle && profileDropdown) {
            profileToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('active');
                notificationDropdown?.classList.remove('active');
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

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function (e) {
                if (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }

        // Close sidebar when a navigation link is clicked
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        sidebarItems.forEach(item => {
            item.addEventListener('click', function (e) {
                // Sidebar will close due to navigation or explicit removal
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
            });
        });

        // Handle window resize - close sidebar if resizing to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>

</html>