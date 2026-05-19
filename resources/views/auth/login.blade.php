<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Web-based Mangrove Extent Mapping and Genus Classification and Data Driven Planting Suitability Recommendation</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="icon" type="image/png" href="/icon-192.png" />
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
            color: #1a2e1a;
            background: radial-gradient(900px 500px at 10% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 20%, rgba(59, 130, 246, 0.10), transparent 55%),
                #f5f7f6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Left Panel - Login Form */
        .login-left {
            flex: 1;
            background:
                radial-gradient(900px 420px at 70% 90%, rgba(93, 115, 99, 0.15), transparent 60%),
                radial-gradient(900px 520px at 10% 30%, rgba(107, 122, 112, 0.12), transparent 60%),
                linear-gradient(135deg, #4a5d52 0%, #556159 100%);
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
            background: rgba(255, 255, 255, 0.82);
            border-radius: 18px;
            padding: 38px 34px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.10);
            border: 1px solid rgba(148, 163, 184, 0.45);
            backdrop-filter: blur(10px);
            position: relative;
        }

        .login-header {
            margin-bottom: 32px;
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
            color: #365a45;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            width: 36px;
            height: 36px;
            position: absolute;
            top: 34px;
            left: 34px;
        }

        .back-btn:hover {
            border-color: #1e9e62;
            background: rgba(30, 158, 98, 0.08);
            color: #1e9e62;
        }

        .back-btn:active {
            transform: scale(0.96);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 800;
            color: #1a2e1a;
            letter-spacing: -0.02em;
        }

        .login-subtitle {
            font-size: 12px;
            color: #5b7864;
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
            color: #365a45;
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
            color: #1a2e1a;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.85);
        }

        .form-input:hover {
            border-color: #c0e8d0;
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
            color: #6b7280;
        }

        .password-toggle:hover svg {
            color: #374151;
        }

        .error-message {
            font-size: 12px;
            color: #d04030;
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
            color: #155724;
            background: #d4edda;
            border: 1px solid #c3e6cb;
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

        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #7a9a7a;
        }

        .login-footer a {
            color: #1e9e62;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .login-footer a:hover {
            color: #16a34a;
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
            .login-left {
                flex: none;
                width: 100%;
                min-height: 100vh;
                padding: 40px 20px;
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
                padding: 30px 16px;
            }

            .login-card {
                padding: 28px 18px;
            }

            .back-btn {
                top: 22px;
                left: 22px;
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
                padding: 20px;
            }

            .login-card {
                padding: 20px 18px;
            }

            .back-btn {
                top: 16px;
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
</head>

<body>
    <!-- Right Panel - Mangrove Visual (hidden on mobile) -->
    <div class="login-right">
        <div class="mangrove-visual">
            <div class="hero-photo" aria-hidden="true"></div>
            <div class="hero-copy" role="note" aria-label="Project overview">
                <div class="hero-title">
                    Web-based Mangrove Extent Mapping, Genus Classification,<br>
                    and Data-driven Planting Suitability Recommendations
                </div>
                <div class="hero-sub">
                    A single platform for mapping mangrove extent, classifying genus from imagery, and recommending
                    planting sites using data-driven suitability analysis.
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
                <div class="login-header">
                    <div class="logo-section">
                        <div class="logo-text">Sign In</div>
                    </div>
                    <p class="login-subtitle">Sign in to access mapping, classification, and planting recommendations.</p>
                </div>

                @if(session('status'))
                <div class="status-message">{{ session('status') }}</div>
                @endif

                <form method="POST" action="/login">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="your@email.com" required value="{{ old('email') }}">
                        @error('email')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input id="password" type="password" name="password" class="form-input" placeholder="••••••••" required>
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
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="remember" id="remember" style="margin: 0; width: 16px; height: 16px; accent-color: #1e9e62;">
                            Remember Me
                        </label>
                    </div>

                    <button type="submit" class="submit-btn">Sign In</button>
                </form>

                <div class="login-footer">
                    <a href="{{ route('password.request') }}" style="display:block; margin-bottom:12px; color:#1e9e62; text-decoration:none; font-weight:600;">Forgot your password?</a>
                    Don't have an account? <a href="/register" onclick="console.log('Register link clicked');">Create one here</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('password-toggle');

            function updateToggleVisibility() {
                toggleButton.style.display = passwordInput.value ? 'inline-flex' : 'none';
            }

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

            const inlineErrors = document.querySelectorAll('.error-message');
            if (inlineErrors.length) {
                setTimeout(() => {
                    inlineErrors.forEach(el => el.remove());
                }, 3000);
            }
        });
    </script>
</body>

</html>