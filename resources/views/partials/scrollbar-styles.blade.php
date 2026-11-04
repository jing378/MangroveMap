{{-- App-wide scrollbar theme (WebKit/Blink: Chrome, Edge, Safari, Opera). Included at the end of <head> in
     every layout and standalone page. Elements that hide their scrollbar (::-webkit-scrollbar { display: none })
     or need their own look override these with a more specific selector.
     Note: Chromium 121+ ignores ::-webkit-scrollbar on any element that sets scrollbar-width or
     scrollbar-color, so those properties are only used as the Firefox fallback below. --}}
<style>
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    ::-webkit-scrollbar-track {
        background: transparent;
    }

    ::-webkit-scrollbar-thumb {
        background: #d4e0d4;
        border-radius: 3px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #b8d4b8;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    ::-webkit-scrollbar-button {
        display: none;
        width: 0;
        height: 0;
    }

    /* Browsers without ::-webkit-scrollbar (Firefox): closest standard equivalent. */
    @supports not selector(::-webkit-scrollbar) {
        * {
            scrollbar-width: thin;
            scrollbar-color: #d4e0d4 transparent;
        }
    }
</style>
