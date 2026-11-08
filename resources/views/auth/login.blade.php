<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Web-Based Mangrove Extent Mapping, Genus Classification, and Expert-Based Planting Suitability Recommendation System</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/png" href="/favicon-round.png" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', system-ui, -apple-system, Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            color: var(--fg-1a2e1a, #1a2e1a);
            background: radial-gradient(900px 500px at 10% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 20%, rgba(59, 130, 246, 0.10), transparent 55%),
                var(--bg-f5f7f6, #f5f7f6);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Left Panel - Login Form */
        /* Same light backdrop and white card as forgot/reset password and verify email. */
        .login-left {
            flex: 1;
            background: radial-gradient(700px 420px at 15% 10%, rgba(16, 185, 129, 0.12), transparent 60%),
                radial-gradient(700px 420px at 90% 90%, rgba(59, 130, 246, 0.08), transparent 60%),
                var(--bg-f5f7f6, #f5f7f6);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
        }

        .login-card {
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.92);
            border-radius: 22px;
            padding: 34px 34px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.30);
            position: relative;
        }

        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--fg-365a45, #365a45);
        }

        .login-header {
            margin-bottom: 28px;
            text-align: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
            margin-bottom: 16px;
        }

        .back-btn {
            background: transparent;
            border: 1.5px solid rgba(148, 163, 184, 0.50);
            border-radius: 10px;
            padding: 8px 8px;
            cursor: pointer;
            color: var(--fg-365a45, #365a45);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            position: absolute;
            /* Level with the brand row at the top of the card. */
            top: 33px;
            left: 34px;
        }

        .back-btn:hover {
            border-color: #1e9e62;
            background: rgba(30, 158, 98, 0.08);
            color: var(--fg-1e9e62, #1e9e62);
        }

        .back-btn:active {
            transform: scale(0.96);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 800;
            color: var(--fg-1a2e1a, #1a2e1a);
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            font-size: 12px;
            color: var(--fg-5b7864, #5b7864);
            margin-top: 8px;
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: var(--fg-365a45, #365a45);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid rgba(148, 163, 184, 0.50);
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--fg-1a2e1a, #1a2e1a);
            transition: all 0.2s ease;
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.85);
        }

        .form-input:hover {
            border-color: var(--bd-c0e8d0, #c0e8d0);
        }

        .form-input:focus {
            outline: none;
            border-color: #1e9e62;
            box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            display: none;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            display: inline-flex;
            padding: 0;
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
            color: var(--fg-6b7280, #6b7280);
        }

        .password-toggle:hover svg {
            color: var(--fg-374151, #374151);
        }

        .error-message {
            font-size: 12px;
            color: var(--fg-d04030, #d04030);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message::before {
            content: "!";
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #d04030;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        .status-message {
            font-size: 13px;
            color: var(--fg-155724, #155724);
            background: var(--bg-d4edda, #d4edda);
            border: 1px solid var(--bd-c3e6cb, #c3e6cb);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .submit-btn {
            width: 100%;
            padding: 12px 16px;
            background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 24px;
            box-shadow: 0 4px 12px rgba(30, 158, 98, 0.2);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 158, 98, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0px);
        }

        .submit-btn {
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
            box-shadow: 0 4px 12px rgba(30, 158, 98, 0.2);
        }

        .submit-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-top-color: var(--bd-ffffff, #fff);
            border-radius: 50%;
            animation: submit-spin 0.7s linear infinite;
        }

        .submit-btn.is-loading .submit-spinner {
            display: inline-block;
        }

        @keyframes submit-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #7a9a7a;
        }

        .login-footer a {
            color: var(--fg-1e9e62, #1e9e62);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .login-footer a:hover {
            color: var(--fg-16a34a, #16a34a);
            text-decoration: underline;
        }

        /* Right Panel - Mangrove Image */
        .login-right {
            flex: 1;
            background:
                radial-gradient(900px 420px at 30% 10%, rgba(16, 185, 129, 0.25), transparent 60%),
                radial-gradient(900px 520px at 90% 70%, rgba(2, 132, 199, 0.22), transparent 60%),
                linear-gradient(135deg, #0b3a2b 0%, #0f3a45 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .mangrove-visual {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-photo {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(180deg, rgba(3, 12, 10, 0.35), rgba(3, 12, 10, 0.68)),
            url("{{ asset('images/mangrove-login.jpg') }}");
            background-size: cover;
            background-position: center;
            transform: scale(1.02);
            filter: saturate(1.05) contrast(1.03);
        }

        .hero-photo::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(800px 420px at 20% 10%, rgba(16, 185, 129, 0.22), transparent 55%),
                radial-gradient(900px 520px at 80% 80%, rgba(2, 132, 199, 0.18), transparent 60%);
            mix-blend-mode: screen;
            opacity: 0.85;
        }

        .hero-copy {
            position: relative;
            width: min(520px, 86%);
            padding: 22px 22px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(10, 27, 21, 0.32);
            backdrop-filter: blur(10px);
            box-shadow: 0 22px 52px rgba(0, 0, 0, 0.35);
        }

        .hero-title {
            color: rgba(255, 255, 255, 0.96);
            font-size: 24px;
            line-height: 1.18;
            letter-spacing: -0.02em;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .hero-sub {
            color: rgba(226, 232, 240, 0.92);
            font-size: 13px;
            line-height: 1.55;
        }

        .hero-meta {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chip {
            padding: 8px 10px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.10);
            color: rgba(255, 255, 255, 0.92);
            font-size: 12px;
            font-weight: 700;
        }

        /* ===== RESPONSIVE STYLES - RIGHT PANEL DISAPPEARS ON MOBILE ===== */
        @media (max-width: 768px) {

            /* Hide the right panel completely on mobile */
            .login-right {
                display: none;
            }

            /* Left panel takes full width and full height */
            /* Top padding keeps the card clear of the floating dark-mode button. */
            .login-left {
                flex: none;
                width: 100%;
                min-height: 100vh;
                padding: 72px 20px 40px;
            }

            /* Adjust card styling for better mobile experience */
            .login-card {
                padding: 28px 20px;
                max-width: 100%;
                margin: 0 auto;
            }

            .logo-text {
                font-size: 24px;
            }

            /* Adjust back button position for smaller screens */
            .back-btn {
                top: 28px;
                left: 28px;
            }
        }

        /* Extra small devices (phones under 480px) - fine-tune spacing */
        @media (max-width: 480px) {
            .login-left {
                padding: 72px 16px 30px;
            }

            .login-card {
                padding: 28px 18px;
            }

            .back-btn {
                top: 27px;
                left: 18px;
                width: 34px;
                height: 34px;
            }

            .logo-text {
                font-size: 22px;
            }

            .login-subtitle {
                font-size: 11px;
            }

            .form-input {
                padding: 10px 12px;
                font-size: 13px;
            }

            .submit-btn {
                padding: 11px 14px;
                font-size: 13px;
            }

            .login-footer {
                font-size: 12px;
            }
        }

        /* Landscape orientation on mobile - keep right panel hidden */
        @media (max-width: 768px) and (orientation: landscape) {
            .login-left {
                min-height: 100vh;
                padding: 64px 20px 20px;
            }

            .login-card {
                padding: 20px 18px;
            }

            .back-btn {
                top: 19px;
                left: 16px;
            }
        }

        /* Tablet sizes (optional - keep right panel visible on tablets) */
        @media (min-width: 769px) and (max-width: 1024px) {

            /* Optional: keep both panels but slightly adjust */
            .hero-title {
                font-size: 20px;
            }

            .hero-sub {
                font-size: 12px;
            }

            .hero-copy {
                padding: 18px 18px;
            }
        }
    </style>
    @include('partials.scrollbar-styles')
</head>

<body>
    <!-- Right Panel - Mangrove Visual (hidden on mobile) -->
    <div class="login-right">
        <div class="mangrove-visual">
            <div class="hero-photo" aria-hidden="true"></div>
            <div class="hero-copy" role="note" aria-label="Project overview">
                <div class="hero-title">
                    Web-Based Mangrove Extent Mapping, Genus Classification, and Expert-Based Planting Suitability Recommendation System
                </div>
                <div class="hero-sub">
                    A single platform for mapping mangrove extent, classifying genus, and providing expert-based
                    planting suitability recommendations.
                </div>
                <div class="hero-meta" aria-label="Key modules">
                    <span class="chip">Extent mapping</span>
                    <span class="chip">Genus classifier</span>
                    <span class="chip">Suitability recommender</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Left Panel - Login Form (takes full screen on mobile) -->
    <div class="login-left">
        <div class="login-container">
            <div class="login-card">
                <button type="button" class="back-btn" onclick="window.location.href = '/'" title="Go to home">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.5 16.25L5.75 9.5L12.5 2.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                @include('auth.partials.brand')
                <div class="login-header">
                    <div class="logo-section">
                        <div class="logo-text">Sign In</div>
                    </div>
                    <p class="login-subtitle">Sign in to access mapping, classification, and planting recommendations.</p>
                </div>

                @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input id="email" type="email" name="email" class="form-input" placeholder="your@email.com" required autocomplete="email" value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="emailError" @enderror>
                        @error('email')
                        <div class="error-message" id="emailError" role="alert">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="password-wrapper">
                            <input id="password" type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
                            <button id="password-toggle" type="button" class="password-toggle" aria-label="Show password" title="Show password">
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z" />
                                </svg>
                            </button>
                        </div>
                        @error('password')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="margin-bottom: 10px;">
                        <label class="remember-label" for="remember">
                            <input type="checkbox" name="remember" id="remember" style="margin: 0; width: 16px; height: 16px; accent-color: #1e9e62;">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="submit-btn" id="loginSubmitBtn">
                        <span class="submit-spinner" aria-hidden="true"></span>
                        <span class="submit-label">Sign In</span>
                    </button>
                </form>

                <div class="login-footer">
                    <a href="{{ route('password.request') }}" style="display:block; margin-bottom:12px; color:var(--fg-1e9e62, #1e9e62); text-decoration:none; font-weight:600;">Forgot your password?</a>
                    Don't have an account? <a href="{{ route('register') }}">Create one here</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('password-toggle');

            passwordInput.addEventListener('input', updateToggleVisibility);
            toggleButton.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleButton.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                toggleButton.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
                toggleButton.innerHTML = isPassword ? hideIcon : showIcon;
            });

            const showIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';
            const hideIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M1.39 4.22l1.41 1.41A11.94 11.94 0 001 12s4 7 11 7c2.24 0 4.33-.66 6.1-1.79l1.44 1.44 1.42-1.42L2.81 2.81 1.39 4.22zM12 17a5 5 0 01-5-5c0-.66.14-1.29.39-1.87l1.87 1.87A3 3 0 0012 15a2.99 2.99 0 002.76-1.85l1.89 1.89A4.98 4.98 0 0112 17zm1.61-3.41l-2.1-2.1A1 1 0 0111 10c0-.55.45-1 1-1 .27 0 .52.11.7.29l1.91 1.91a1.01 1.01 0 01-.0 1.39zM12 7a5 5 0 014.61 3.11l1.53-1.53A11.96 11.96 0 0012 5c-2.24 0-4.33.66-6.1 1.79l1.5 1.5A9.94 9.94 0 0112 7z"/></svg>';

            function updateToggleVisibility() {
                toggleButton.style.display = passwordInput.value ? 'inline-flex' : 'none';
            }

            updateToggleVisibility();

            // Loading state on Sign In.
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginSubmitBtn');
            const submitLabel = submitBtn.querySelector('.submit-label');

            function setSubmitting(isSubmitting) {
                submitBtn.disabled = isSubmitting;
                submitBtn.classList.toggle('is-loading', isSubmitting);
                submitBtn.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
                submitLabel.textContent = isSubmitting ? 'Signing in...' : 'Sign In';
            }

            loginForm.addEventListener('submit', function() {
                setSubmitting(true);
            });

            // Reset if the page is restored from the back/forward cache.
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) setSubmitting(false);
            });

            // Errors stay on screen until the user edits the field they belong to.
            document.querySelectorAll('.form-group').forEach(group => {
                const input = group.querySelector('.form-input');
                const error = group.querySelector('.error-message');
                if (!input || !error) return;
                input.addEventListener('input', () => {
                    error.remove();
                    input.removeAttribute('aria-invalid');
                    input.removeAttribute('aria-describedby');
                }, { once: true });
            });
        });
    </script>
    @include('partials.theme-toggle', ['floating' => true])
</body>

</html>