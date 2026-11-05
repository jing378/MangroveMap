{{-- App logo and name at the top of every auth card (same mark as the landing page header). Links home.
     Styles are inline so each standalone auth page only needs this include. --}}
<a href="{{ url('/') }}" class="auth-brand" aria-label="MangroveMap home">
    <img src="/icon-192.png" alt="" width="28" height="28">
    <span>Mangrove<span class="auth-brand-accent">Map</span></span>
</a>
<style>
    .auth-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 34px;
        margin-bottom: 14px;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: -0.01em;
        color: var(--fg-1a2e1a, #1a2e1a);
        text-decoration: none;
    }

    .auth-brand img {
        width: 28px;
        height: 28px;
        border-radius: 8px;
    }

    .auth-brand-accent {
        color: var(--fg-1e9e62, #1e9e62);
    }
</style>
