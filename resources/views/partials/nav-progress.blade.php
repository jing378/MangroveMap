{{-- Thin progress bar along the top of the page while the next page loads (resident/expert and admin layouts).
     It appears only when loading takes longer than SHOW_AFTER_MS, so quick page changes show nothing.
     Started by clicking a link to another page of this site or submitting a form normally; skipped for new
     tabs (target), downloads (download attribute), links within the page (#), modifier-key clicks, forms
     handled by JavaScript (they prevent the default) and anything marked data-no-progress. --}}
<div class="nav-progress" id="navProgress" aria-hidden="true"><div class="nav-progress-bar"></div></div>
<style>
    .nav-progress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 3000;
        height: 3px;
        pointer-events: none;
        opacity: 0;
        transition: opacity .2s ease;
    }

    .nav-progress.is-visible {
        opacity: 1;
    }

    .nav-progress-bar {
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, #1e9e62, #34d399);
        box-shadow: 0 0 8px rgba(30, 158, 98, .55);
        transform: scaleX(0);
        transform-origin: left center;
        transition: transform .25s ease;
    }

    @media (prefers-reduced-motion: reduce) {
        .nav-progress-bar {
            transition: none;
        }
    }
</style>
<script>
    (function () {
        const root = document.getElementById('navProgress');
        if (!root) return;
        const bar = root.firstElementChild;
        const SHOW_AFTER_MS = 300;
        // Something went wrong (no page arrived): stop showing it.
        const GIVE_UP_MS = 20000;
        let showTimer = null;
        let trickleTimer = null;
        let giveUpTimer = null;
        let progress = 0;

        function setProgress(value) {
            progress = value;
            bar.style.transform = `scaleX(${value})`;
        }

        function reset() {
            clearTimeout(showTimer);
            clearInterval(trickleTimer);
            clearTimeout(giveUpTimer);
            showTimer = trickleTimer = giveUpTimer = null;
            root.classList.remove('is-visible');
            setProgress(0);
        }

        function start() {
            if (showTimer || trickleTimer) return;
            showTimer = setTimeout(function () {
                showTimer = null;
                root.classList.add('is-visible');
                setProgress(0.15);
                // Creeps towards 90%, slowing down, until the next page replaces this one.
                trickleTimer = setInterval(function () {
                    setProgress(progress + (0.9 - progress) * 0.08);
                }, 250);
            }, SHOW_AFTER_MS);
            giveUpTimer = setTimeout(reset, GIVE_UP_MS);
        }

        window.NavProgress = { start: start, done: reset };

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const link = event.target.closest('a[href]');
            if (!link || link.hasAttribute('download') || link.closest('[data-no-progress]')) return;
            if (link.target && link.target !== '_self') return;
            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
            // Only the #hash changes: no new page.
            if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
            // Let other click handlers run first: a link they take over (preventDefault) is not a page change.
            setTimeout(function () { if (!event.defaultPrevented) start(); }, 0);
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || form.closest('[data-no-progress]')) return;
            if (form.target && form.target !== '_self') return;
            setTimeout(function () { if (!event.defaultPrevented) start(); }, 0);
        });

        // Coming back with the browser's Back button can show this page from memory: no bar left over.
        window.addEventListener('pageshow', reset);
    })();
</script>
