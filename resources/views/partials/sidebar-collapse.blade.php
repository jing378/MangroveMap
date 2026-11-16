{{-- Desktop sidebar that can be minimized to icons only (resident/expert and admin layouts).
     Included in the page <head>: sets <html data-sidebar="collapsed"> before the page paints (so it does not
     jump), and holds the styles. The button is partials.sidebar-collapse-button, in the header.
     The choice is remembered per browser. On phones the sidebar is a slide-in menu, so this does nothing there. --}}
<script>
    (function () {
        try {
            if (localStorage.getItem('mangrovemap-sidebar') === 'collapsed') {
                document.documentElement.setAttribute('data-sidebar', 'collapsed');
            }
        } catch (error) { /* private mode: stays expanded */ }
    })();
</script>
<style>
    .sidebar-collapse-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        padding: 0;
        border: none;
        border-radius: 8px;
        background: transparent;
        color: var(--fg-6a8a6a, #6a8a6a);
        font-size: 18px;
        cursor: pointer;
        transition: background .15s, color .15s;
    }

    .sidebar-collapse-btn:hover,
    .sidebar-collapse-btn:focus-visible {
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1e9e62, #1e9e62);
    }

    @media (min-width: 769px) {
        .sidebar-collapse-btn {
            display: inline-flex;
        }

        .sidebar {
            flex-shrink: 0;
            transition: width .2s ease;
        }

        html[data-sidebar="collapsed"] .sidebar {
            width: 68px;
            overflow-x: hidden;
        }

        /* Icons only: labels, section names, the version and the profile text are hidden. */
        html[data-sidebar="collapsed"] .sidebar-item > span:not(.sidebar-notification-icon),
        html[data-sidebar="collapsed"] .sidebar-profile-details,
        html[data-sidebar="collapsed"] .sidebar-version {
            display: none;
        }

        /* A section name becomes a thin divider (none above the first section). */
        html[data-sidebar="collapsed"] .sidebar-title {
            height: 1px;
            margin: 0 14px 10px;
            padding: 0;
            overflow: hidden;
            background: var(--bg-e0e8e0, #e0e8e0);
            color: transparent;
            font-size: 0;
        }

        html[data-sidebar="collapsed"] .sidebar-section:first-child .sidebar-title {
            display: none;
        }

        html[data-sidebar="collapsed"] .sidebar-section {
            margin-bottom: 14px;
        }

        html[data-sidebar="collapsed"] .sidebar-item {
            justify-content: center;
            gap: 0;
            /* A fixed square for every item: links and buttons (feedback, install) alike. A button
               with width: auto shrinks to its icon and sits off-centre. */
            box-sizing: border-box;
            width: 44px;
            height: 42px;
            margin: 2px auto;
            padding: 0;
            border-left-width: 0;
        }

        html[data-sidebar="collapsed"] .sidebar-item.sidebar-install {
            width: 44px;
            border: none;
            background: transparent;
        }

        html[data-sidebar="collapsed"] .sidebar-install-section {
            padding-top: 0;
            margin-bottom: 8px;
        }

        html[data-sidebar="collapsed"] .sidebar-profile-footer {
            /* The version line above it is hidden, so the footer keeps itself at the bottom. */
            margin-top: auto;
            flex-direction: column;
            gap: 4px;
            padding: 12px 0 0;
        }

        html[data-sidebar="collapsed"] .sidebar-profile-link {
            flex: none;
            padding: 4px;
        }
    }
</style>
