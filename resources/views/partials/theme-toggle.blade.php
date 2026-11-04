{{--
    Light / dark switch, handled by partials.theme (any [data-theme-toggle] element works).
    @include('partials.theme-toggle')                         → round icon button for the page header
    @include('partials.theme-toggle', ['floating' => true])   → same button fixed in the corner (pages without a header)

    Icons are inline SVG: not every standalone page loads Bootstrap Icons.
--}}
<button type="button"
    class="theme-toggle-btn {{ ($floating ?? false) ? 'theme-toggle-floating' : '' }}"
    data-theme-toggle aria-pressed="false" title="Toggle dark mode" aria-label="Toggle dark mode">
    <svg class="theme-toggle-light" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>
    <svg class="theme-toggle-dark" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4.5" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </svg>
</button>
