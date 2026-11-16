{{-- Header button that minimizes the desktop sidebar to icons only, or expands it again
     (styles and the remembered state: partials.sidebar-collapse). --}}
<button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Minimize sidebar" title="Minimize sidebar"
    aria-pressed="false">
    <i class="bi bi-layout-sidebar" aria-hidden="true"></i>
</button>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const button = document.getElementById('sidebarCollapseBtn');
        const sidebar = document.querySelector('.sidebar');
        if (!button || !sidebar) return;
        const root = document.documentElement;

        // Minimized, only the icon shows: keep each item's name as a tooltip and for screen readers.
        sidebar.querySelectorAll('.sidebar-item').forEach(function (item) {
            const label = item.querySelector('strong')
                || item.querySelector(':scope > span:not(.sidebar-notification-icon)');
            const text = label ? label.textContent.trim() : '';
            if (text && !item.getAttribute('aria-label')) item.setAttribute('aria-label', text);
            if (text) item.dataset.label = text;
        });

        function apply(collapsed) {
            const label = collapsed ? 'Expand sidebar' : 'Minimize sidebar';
            button.setAttribute('aria-label', label);
            button.title = label;
            button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
            button.querySelector('i').className = collapsed ? 'bi bi-layout-sidebar-inset' : 'bi bi-layout-sidebar';
            sidebar.querySelectorAll('.sidebar-item').forEach(function (item) {
                if (collapsed && item.dataset.label) item.title = item.dataset.label;
                else item.removeAttribute('title');
            });
        }

        apply(root.getAttribute('data-sidebar') === 'collapsed');

        button.addEventListener('click', function () {
            const collapsed = root.getAttribute('data-sidebar') !== 'collapsed';
            if (collapsed) root.setAttribute('data-sidebar', 'collapsed');
            else root.removeAttribute('data-sidebar');
            try { localStorage.setItem('mangrovemap-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (error) { /* private mode */ }
            apply(collapsed);
            // The page got wider or narrower: maps and charts measure themselves again once the sidebar has moved.
            setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 230);
        });
    });
</script>
