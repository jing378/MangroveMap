{{-- Round back button in the top-left corner of an auth card (the card must be position: relative).
     Params: href, title. Same look as the back button on login and register. --}}
<a href="{{ $href }}" class="auth-back-btn" title="{{ $title }}" aria-label="{{ $title }}">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M12.5 16.25L5.75 9.5L12.5 2.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</a>
<style>
    .auth-back-btn {
        position: absolute;
        top: 36px;
        left: 32px;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid rgba(148, 163, 184, 0.50);
        border-radius: 10px;
        color: var(--fg-365a45, #365a45);
        transition: all 0.2s ease;
    }

    .auth-back-btn:hover {
        border-color: #1e9e62;
        background: rgba(30, 158, 98, 0.08);
        color: var(--fg-1e9e62, #1e9e62);
    }
</style>
