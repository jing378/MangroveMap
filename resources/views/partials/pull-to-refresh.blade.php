{{--
    Pull to refresh on phones and tablets. The layouts scroll inside .content (the page itself never scrolls),
    so the browser's own pull-to-refresh never fires, and the installed app has none at all. This adds it back:
    with .content at the top, pull down and let go past the line to reload the page.
    Skipped on pages with a Leaflet map (dragging moves the map there), inside modals and other scrolled
    boxes, while typing, and on any page that sets data-no-pull-refresh on <body> or an ancestor of the touch.
    Included in the enduser and admin layouts.
--}}
<div class="ptr-indicator" id="ptrIndicator" aria-hidden="true">
    <i class="bi bi-arrow-clockwise"></i>
</div>
<style>
    .ptr-indicator {
        position: fixed;
        left: 50%;
        top: 0;
        z-index: 9990;
        width: 38px;
        height: 38px;
        margin-left: -19px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 50%;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1e9e62, #1e9e62);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        opacity: 0;
        pointer-events: none;
        transform: translateY(-48px);
    }

    .ptr-indicator.is-ready {
        background: #1e9e62;
        border-color: #1e9e62;
        color: #fff;
    }

    .ptr-indicator.is-settling {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .ptr-indicator.is-loading i {
        animation: ptr-spin 0.8s linear infinite;
    }

    @keyframes ptr-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const scroller = document.querySelector('.main-content > .content');
        const indicator = document.getElementById('ptrIndicator');
        const touchDevice = window.matchMedia('(pointer: coarse)').matches || 'ontouchstart' in window;
        if (!scroller || !indicator || !touchDevice) return;
        if (document.body.hasAttribute('data-no-pull-refresh')) return;

        const threshold = 70; // px of (damped) pull needed to reload
        const maxPull = 110;
        let startY = null;
        let startX = 0;
        let pull = 0;
        let pulling = false;
        let reloading = false;

        // Map pages: dragging pans the map, and the right panel may hold unsaved drawings.
        const hasMap = () => !!scroller.querySelector('.leaflet-container');

        function blockedBy(target) {
            if (!(target instanceof Element)) return true;
            if (target.closest('[data-no-pull-refresh], .leaflet-container, dialog, [role="dialog"], .modal, input, textarea, select, [contenteditable="true"]')) return true;
            // Inside a box that scrolls on its own and isn't at its top: let that box scroll.
            for (let el = target; el && el !== scroller; el = el.parentElement) {
                if (el.scrollTop > 0) return true;
            }
            return false;
        }

        function place(distance) {
            const top = scroller.getBoundingClientRect().top;
            indicator.style.top = top + 'px';
            indicator.style.opacity = String(Math.min(1, distance / 40));
            indicator.style.transform = `translateY(${Math.min(distance, maxPull) - 48 + 12}px) rotate(${distance * 3}deg)`;
            indicator.classList.toggle('is-ready', distance >= threshold);
        }

        function reset() {
            indicator.classList.add('is-settling');
            indicator.classList.remove('is-ready');
            indicator.style.opacity = '0';
            indicator.style.transform = 'translateY(-48px)';
            setTimeout(() => indicator.classList.remove('is-settling'), 220);
        }

        scroller.addEventListener('touchstart', event => {
            startY = null;
            pulling = false;
            pull = 0;
            if (reloading || event.touches.length !== 1 || scroller.scrollTop > 0) return;
            if (hasMap() || blockedBy(event.target)) return;
            const active = document.activeElement;
            if (active && active.matches('input, textarea, select, [contenteditable="true"]')) return;
            startY = event.touches[0].clientY;
            startX = event.touches[0].clientX;
        }, { passive: true });

        scroller.addEventListener('touchmove', event => {
            if (startY === null || reloading) return;
            const dy = event.touches[0].clientY - startY;
            const dx = Math.abs(event.touches[0].clientX - startX);
            if (!pulling) {
                // Only a clearly downward drag at the very top starts a pull; sideways swipes and scrolling up don't.
                if (dy < 8 || dx > dy || scroller.scrollTop > 0) {
                    if (dy < 0 || dx > 12) startY = null;
                    return;
                }
                pulling = true;
                indicator.classList.remove('is-settling');
            }
            event.preventDefault(); // stop the bounce while pulling
            pull = Math.max(0, dy * 0.5);
            place(pull);
        }, { passive: false });

        // A page with something to lose (e.g. Drone Upload's chosen files) can set window.confirmReload to an
        // async function that asks first in the app's modal; it resolves true to go ahead with the reload.
        const finish = async () => {
            if (!pulling) {
                startY = null;
                return;
            }
            pulling = false;
            startY = null;
            if (pull >= threshold) {
                reloading = true;
                if (typeof window.confirmReload === 'function' && !(await window.confirmReload())) {
                    reloading = false;
                    reset();
                    return;
                }
                indicator.classList.add('is-settling', 'is-loading', 'is-ready');
                indicator.style.opacity = '1';
                indicator.style.transform = `translateY(${threshold - 48 + 12}px)`;
                location.reload();
            } else {
                reset();
            }
        };
        scroller.addEventListener('touchend', finish);
        scroller.addEventListener('touchcancel', () => {
            pulling = false;
            startY = null;
            reset();
        });
    });
</script>
