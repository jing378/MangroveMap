        {{-- Loading state for auth submit buttons (inlined into a page <style>; pairs with submit-loading-script).
             The button holds a .submit-spinner and a .submit-label span. --}}
        .submit-btn {
            /* Buttons don't inherit the page font by default (they fell back to a heavy system font). */
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn:disabled,
        .submit-btn.is-loading {
            cursor: wait;
            opacity: 0.85;
            transform: none;
        }

        .submit-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: submit-spin 0.7s linear infinite;
        }

        .is-loading .submit-spinner {
            display: inline-block;
        }

        @keyframes submit-spin {
            to {
                transform: rotate(360deg);
            }
        }
