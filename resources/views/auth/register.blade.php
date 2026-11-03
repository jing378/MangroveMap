<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Register - Web-Based Mangrove Extent Mapping, Genus Classification, and Expert-Based Planting Suitability Recommendation System</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Manrope', system-ui, -apple-system, Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            height: 100dvh;
            display: flex;
            color: #1a2e1a;
            background: radial-gradient(900px 500px at 10% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 20%, rgba(59, 130, 246, 0.10), transparent 55%),
                #f5f7f6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Left Panel - Register Form */
        .register-left {
            flex: 1;
            background:
                radial-gradient(900px 420px at 70% 90%, rgba(93, 115, 99, 0.15), transparent 60%),
                radial-gradient(900px 520px at 10% 30%, rgba(107, 122, 112, 0.12), transparent 60%),
                linear-gradient(135deg, #4a5d52 0%, #556159 100%);
            display: flex;
            padding: 24px 20px;
            height: 100%;
            /* Still scrollable on very short screens, but without a visible scrollbar. */
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .register-left::-webkit-scrollbar {
            display: none;
        }

        .register-container {
            width: 100%;
            max-width: 440px;
            /* Centers the card, and starts it at the top if the screen is too short. */
            margin: auto;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.82);
            border-radius: 18px;
            padding: 28px 30px 24px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.10);
            border: 1px solid rgba(148, 163, 184, 0.45);
            backdrop-filter: blur(10px);
            position: relative;
        }

        .register-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
            margin-bottom: 6px;
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
            width: 34px;
            height: 34px;
            position: absolute;
            top: 24px;
            left: 24px;
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

        .register-subtitle {
            font-size: 12px;
            color: #5b7864;
            margin-top: 0;
            line-height: 1.45;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #365a45;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 10px 13px;
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

        .password-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 330px) {
            .password-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        .password-rules {
            list-style: none;
            margin: -4px 0 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            font-size: 11.5px;
            color: #7a8a7f;
        }

        .password-rules li {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-rules li::before {
            content: "";
            width: 12px;
            height: 12px;
            flex: none;
            border-radius: 50%;
            border: 1.5px solid #b8c4bc;
            box-sizing: border-box;
        }

        .password-rules li.met {
            color: #1e9e62;
        }

        .password-rules li.met::before {
            border-color: #1e9e62;
            background: #1e9e62 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 6.2l2 2 4-4.4' fill='none' stroke='%23fff' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/10px no-repeat;
        }

        .password-rules li.unmet {
            color: #d04030;
        }

        .password-rules li.unmet::before {
            border-color: #d04030;
        }

        @media (max-width: 330px) {
            .password-rules {
                grid-template-columns: 1fr;
            }
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Email gets the wider column; the 09 number needs less room. */
        .field-row--contact {
            grid-template-columns: 3fr 2fr;
        }

        .field-row > *,
        .password-row > * {
            min-width: 0;
        }

        /* On phones, email and contact number stack; names and passwords stay paired. */
        @media (max-width: 520px) {
            .field-row--contact {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 330px) {
            .field-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        .phone-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .phone-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            font-weight: 700;
            color: #1a2e1a;
            pointer-events: none;
            user-select: none;
        }

        .phone-wrapper .form-input {
            padding-left: 38px;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
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

        .form-input.error-input {
            border-color: #d04030;
            background-color: rgba(208, 64, 48, 0.03);
        }

        .form-input.error-input:focus {
            border-color: #d04030;
            box-shadow: 0 0 0 3px rgba(208, 64, 48, 0.1);
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
            min-width: 18px;
            height: 18px;
            min-height: 18px;
            border-radius: 50%;
            background: #d04030;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            padding: 0;
            flex: none;
            text-align: center;
            box-sizing: border-box;
        }

        .expert-apply {
            margin-top: 16px;
            border: 1.5px solid rgba(148, 163, 184, 0.45);
            border-radius: 12px;
            padding: 12px 13px;
            background: rgba(255, 255, 255, 0.6);
        }

        .expert-apply-toggle {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            cursor: pointer;
        }

        .expert-apply-toggle input {
            margin-top: 3px;
            width: 16px;
            height: 16px;
            flex: none;
            accent-color: #1e9e62;
        }

        .expert-apply-toggle strong {
            display: block;
            font-size: 13px;
            color: #1a2e1a;
        }

        .expert-apply-toggle small {
            display: block;
            font-size: 11.5px;
            line-height: 1.45;
            color: #5b7864;
            margin-top: 2px;
        }

        .expert-fields {
            margin-top: 12px;
        }

        .expert-fields[hidden] {
            display: none;
        }

        .expert-fields .form-group:last-child {
            margin-bottom: 0;
        }

        textarea.form-input {
            resize: vertical;
            min-height: 72px;
            line-height: 1.45;
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
            margin-top: 18px;
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
            border-top-color: #fff;
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

        .register-footer {
            text-align: center;
            margin-top: 14px;
            font-size: 13px;
            color: #7a9a7a;
        }

        .register-footer a {
            color: #1e9e62;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .register-footer a:hover {
            color: #16a34a;
            text-decoration: underline;
        }

        /* Right Panel - Mangrove Image */
        .register-right {
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
            .register-right {
                display: none;
            }

            /* Left panel takes full width and full height */
            .register-left {
                flex: none;
                width: 100%;
                padding: 20px 16px;
            }

            /* Adjust card styling for better mobile experience */
            .register-card {
                padding: 24px 20px 20px;
                max-width: 100%;
            }

            .logo-text {
                font-size: 24px;
            }

            /* Adjust back button position for smaller screens */
            .back-btn {
                top: 20px;
                left: 20px;
            }
        }

        /* Extra small devices (phones under 480px) - fine-tune spacing */
        @media (max-width: 480px) {
            .register-left {
                padding: 16px 12px;
            }

            .register-card {
                padding: 22px 16px 18px;
            }

            .back-btn {
                top: 18px;
                left: 16px;
                width: 32px;
                height: 32px;
            }

            .back-btn svg {
                width: 18px;
                height: 18px;
            }

            .logo-text {
                font-size: 22px;
            }

            .register-subtitle {
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

            .register-footer {
                font-size: 12px;
            }

            .form-label {
                font-size: 11px;
            }

            .form-group {
                margin-bottom: 12px;
            }
        }

        /* Landscape orientation on mobile - keep right panel hidden */
        @media (max-width: 768px) and (orientation: landscape) {
            .register-left {
                padding: 12px 20px;
            }

            .register-card {
                padding: 20px 18px;
            }

            .back-btn {
                top: 16px;
                left: 16px;
            }
        }

        /* Tablet sizes (optional - keep both panels but slightly adjust) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .hero-title {
                font-size: 20px;
            }

            .hero-sub {
                font-size: 12px;
            }

            .hero-copy {
                padding: 18px 18px;
            }

            .register-card {
                padding: 26px 24px 22px;
            }
        }

        /* Loading Modal Styles */
        .loading-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
        }

        .loading-modal.active {
            display: flex;
        }

        .loading-content {
            background: #fff;
            border-radius: 18px;
            padding: 48px 40px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e0e8e0;
            border-top-color: #1e9e62;
            border-radius: 50%;
            margin: 0 auto 24px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-title {
            font-size: 22px;
            font-weight: 800;
            color: #1a2e1a;
            margin-bottom: 12px;
        }

        .loading-subtitle {
            font-size: 13px;
            color: #7a9a7a;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .loading-info {
            background: #edf7f2;
            border: 1px solid #b0e0c0;
            border-radius: 10px;
            padding: 16px;
            font-size: 12px;
            color: #3a5a3a;
            line-height: 1.6;
        }

        .loading-info strong {
            color: #1e9e62;
            display: block;
            margin-bottom: 6px;
        }
    </style>
</head>

<body>
    <!-- Right Panel - Mangrove Visual (hidden on mobile) -->
    <div class="register-right">
        <div class="mangrove-visual">
            <div class="hero-photo" aria-hidden="true"></div>
            <div class="hero-copy" role="note" aria-label="Project overview">
                <div class="hero-title">
                    Web-Based Mangrove Extent Mapping, Genus Classification,<br>
                    and Expert-Based Planting Suitability Recommendation System
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

    <!-- Left Panel - Register Form (takes full screen on mobile) -->
    <div class="register-left">
        <div class="register-container">
            <div class="register-card">
                <button type="button" class="back-btn" onclick="window.location.href = '/'" title="Go to home">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12.5 16.25L5.75 9.5L12.5 2.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="register-header">
                    <div class="logo-section">
                        <div class="logo-text">Create Account</div>
                    </div>
                    <p class="register-subtitle">Create an account to access mapping, classification, and recommendations.</p>
                </div>

                <form method="POST" action="/register" novalidate>
                    @csrf

                    <div class="field-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" placeholder="Juan" required maxlength="100" autocomplete="given-name" value="{{ old('first_name') }}">
                            @error('first_name')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Dela Cruz" required maxlength="100" autocomplete="family-name" value="{{ old('last_name') }}">
                            @error('last_name')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="field-row field-row--contact">
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com" required autocomplete="email" value="{{ old('email') }}">
                            @error('email')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Contact No.</label>
                            <div class="phone-wrapper">
                                <span class="phone-prefix" aria-hidden="true">09</span>
                                <input type="tel" id="phone" name="phone" class="form-input" placeholder="123456789" inputmode="numeric" pattern="[0-9]{9}" autocomplete="off" aria-label="Contact number after 09" title="Enter the 9 digits after 09" value="{{ old('phone') }}">
                            </div>
                            @error('phone')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="password-row">
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="password-wrapper">
                                <input id="password" type="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="new-password" aria-describedby="passwordRules">
                                <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                            </div>
                            @error('password')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password_confirmation">Confirm Password</label>
                            <div class="password-wrapper">
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-input" placeholder="••••••••" required autocomplete="new-password">
                                <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                            </div>
                            @error('password_confirmation')
                            <div class="error-message">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <ul class="password-rules" id="passwordRules" aria-live="polite">
                        <li data-rule="length">At least 8 characters</li>
                        <li data-rule="upper">One uppercase letter</li>
                        <li data-rule="lower">One lowercase letter</li>
                        <li data-rule="number">One number</li>
                    </ul>

                    <div class="expert-apply">
                        <label class="expert-apply-toggle" for="apply_as_expert">
                            <input type="checkbox" id="apply_as_expert" name="apply_as_expert" value="1" aria-controls="expertFields" @checked(old('apply_as_expert'))>
                            <span>
                                <strong>Apply for an expert account</strong>
                                <small>For mangrove specialists who review delineations and give planting recommendations. An administrator approves each application; you can use a resident account until then.</small>
                            </span>
                        </label>

                        <div class="expert-fields" id="expertFields" @if(!old('apply_as_expert')) hidden @endif>
                            <div class="form-group">
                                <label class="form-label" for="organization">Organization / Affiliation</label>
                                <input type="text" id="organization" name="organization" class="form-input" placeholder="e.g. DENR CENRO Maasin, SLSU" maxlength="255" autocomplete="organization" value="{{ old('organization') }}">
                                @error('organization')
                                <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="expert_qualifications">Qualifications &amp; Experience</label>
                                <textarea id="expert_qualifications" name="expert_qualifications" class="form-input" rows="3" maxlength="2000" placeholder="Your position, field of study, and experience with mangroves.">{{ old('expert_qualifications') }}</textarea>
                                @error('expert_qualifications')
                                <div class="error-message">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <span class="submit-spinner" aria-hidden="true"></span>
                        <span class="submit-label">Create Account</span>
                    </button>
                </form>

                <div class="register-footer">
                    Already have an account? <a href="/login">Sign in here</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="loading-modal" id="loadingModal">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h2 class="loading-title">Account Created!</h2>
            <p class="loading-subtitle">Verification email sent to your inbox</p>
            <div class="loading-info">
                <strong>📧 Check your email</strong>
                Click the verification link to activate your account and start mapping mangroves!
            </div>
            <div class="loading-info" id="expertPendingInfo" style="margin-top: 12px;" hidden>
                <strong>🧭 Expert application submitted</strong>
                An administrator will review your application. You'll be notified once it's decided; until then you can use your account as a resident.
            </div>
        </div>
    </div>

    <script>
        const form = document.querySelector('form');
        const modal = document.getElementById('loadingModal');
        const submitBtn = form.querySelector('.submit-btn');
        const submitLabel = submitBtn.querySelector('.submit-label');

        function setSubmitting(isSubmitting) {
            submitBtn.disabled = isSubmitting;
            submitBtn.classList.toggle('is-loading', isSubmitting);
            submitBtn.setAttribute('aria-busy', isSubmitting ? 'true' : 'false');
            submitLabel.textContent = isSubmitting ? 'Signing up...' : 'Create Account';
        }

        // Clear validation error displays
        function clearErrors() {
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.form-input').forEach(el => el.classList.remove('error-input'));
        }

        // Display field-specific validation errors
        function displayValidationErrors(errors) {
            clearErrors();

            const fieldErrors = {
                ...errors
            };
            const passwordError = fieldErrors.password?.[0] ?? null;

            if (passwordError && !fieldErrors.password_confirmation) {
                const lowerMessage = passwordError.toLowerCase();
                if (lowerMessage.includes('confirm') || lowerMessage.includes('confirmation') || lowerMessage.includes('match')) {
                    fieldErrors.password_confirmation = [passwordError];
                }
            }

            for (const field in fieldErrors) {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    input.classList.add('error-input');
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = fieldErrors[field][0];

                    // Append to the form-group (works for both regular and password fields)
                    const formGroup = input.closest('.form-group');
                    if (formGroup) {
                        formGroup.appendChild(errorDiv);
                    }
                }
            }
        }

        function initPasswordToggles() {
            const showIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';
            const hideIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M1.39 4.22l1.41 1.41A11.94 11.94 0 001 12s4 7 11 7c2.24 0 4.33-.66 6.1-1.79l1.44 1.44 1.42-1.42L2.81 2.81 1.39 4.22zM12 17a5 5 0 01-5-5c0-.66.14-1.29.39-1.87l1.87 1.87A3 3 0 0012 15a2.99 2.99 0 002.76-1.85l1.89 1.89A4.98 4.98 0 0112 17zm1.61-3.41l-2.1-2.1A1 1 0 0111 10c0-.55.45-1 1-1 .27 0 .52.11.7.29l1.91 1.91a1.01 1.01 0 01-.0 1.39zM12 7a5 5 0 014.61 3.11l1.53-1.53A11.96 11.96 0 0012 5c-2.24 0-4.33.66-6.1 1.79l1.5 1.5A9.94 9.94 0 0112 7z"/></svg>';

            document.querySelectorAll('.password-wrapper').forEach(wrapper => {
                const input = wrapper.querySelector('input.form-input');
                const toggle = wrapper.querySelector('.password-toggle');
                if (!input || !toggle) return;

                toggle.innerHTML = showIcon;

                function updateToggleVisibility() {
                    if (input.value.trim()) {
                        toggle.style.display = 'inline-flex';
                    } else {
                        toggle.style.display = 'none';
                        input.type = 'password';
                        toggle.innerHTML = showIcon;
                        toggle.setAttribute('aria-label', 'Show password');
                        toggle.setAttribute('title', 'Show password');
                    }
                }

                input.addEventListener('input', updateToggleVisibility);
                toggle.addEventListener('click', function() {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    toggle.innerHTML = isPassword ? hideIcon : showIcon;
                    toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
                    toggle.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
                });

                updateToggleVisibility();
            });
        }

        // Handle form submission
        initPasswordToggles();
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            // "09" is a fixed prefix; the field holds only the 9 digits after it.
            phoneInput.addEventListener('input', function(e) {
                let digits = this.value.replace(/[^0-9]/g, '');
                // Allow pasting a full number like 09123456789 or 639123456789.
                if (digits.length > 9 && digits.startsWith('09')) digits = digits.slice(2);
                else if (digits.length > 9 && digits.startsWith('639')) digits = digits.slice(3);
                this.value = digits.slice(0, 9);
            });
        }

        // ---------- Live validation while typing ----------
        const checkEmailUrl = @json(route('register.check-email'));
        const fieldInput = name => form.querySelector(`[name="${name}"]`);
        const expertToggle = document.getElementById('apply_as_expert');
        const expertFields = document.getElementById('expertFields');
        const appliesAsExpert = () => expertToggle.checked;

        expertToggle.addEventListener('change', () => {
            expertFields.hidden = !appliesAsExpert();
            if (appliesAsExpert()) {
                fieldInput('organization').focus();
            } else {
                ['organization', 'expert_qualifications'].forEach(name => {
                    touched.delete(name);
                    setFieldError(fieldInput(name), null);
                });
            }
        });

        function setFieldError(input, message) {
            const formGroup = input.closest('.form-group');
            if (!formGroup) return;
            let errorDiv = formGroup.querySelector('.error-message');
            if (!message) {
                input.classList.remove('error-input');
                input.removeAttribute('aria-invalid');
                errorDiv?.remove();
                return;
            }
            input.classList.add('error-input');
            input.setAttribute('aria-invalid', 'true');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.setAttribute('role', 'alert');
                formGroup.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        }

        // Password requirements, shown as a live checklist under the field.
        function passwordRuleResults(value) {
            return {
                length: value.length >= 8,
                upper: /[A-Z]/.test(value),
                lower: /[a-z]/.test(value),
                number: /[0-9]/.test(value),
            };
        }

        function updatePasswordRules(showUnmet) {
            const results = passwordRuleResults(fieldInput('password').value);
            document.querySelectorAll('#passwordRules li').forEach(item => {
                const met = !!results[item.dataset.rule];
                item.classList.toggle('met', met);
                item.classList.toggle('unmet', !met && showUnmet);
            });
        }

        // Client-side rules (the server still validates everything on submit).
        const validators = {
            first_name: value => value.trim() ? null : 'First name is required.',
            last_name: value => value.trim() ? null : 'Last name is required.',
            email: value => {
                const email = value.trim();
                if (!email) return 'Email address is required.';
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? null : 'Please enter a valid email address.';
            },
            phone: value => (!value || /^[0-9]{9}$/.test(value)) ? null : 'Enter the 9 digits after 09 (e.g. 09123456789).',
            password: value => {
                if (!value) return 'Password is required.';
                const rules = passwordRuleResults(value);
                // The checklist below the fields shows which requirement is missing.
                return (rules.length && rules.upper && rules.lower && rules.number)
                    ? null
                    : 'Password does not meet all requirements.';
            },
            password_confirmation: value => {
                if (!value) return 'Please confirm your password.';
                return value === fieldInput('password').value ? null : 'Password confirmation does not match.';
            },
            organization: value => (!appliesAsExpert() || value.trim())
                ? null
                : 'Organization or affiliation is required for an expert application.',
            expert_qualifications: value => {
                if (!appliesAsExpert()) return null;
                if (!value.trim()) return 'Describe your expertise so an administrator can review your application.';
                return value.trim().length >= 20 ? null : 'Please describe your expertise in at least 20 characters.';
            },
        };

        // A field only shows "required"/format errors after the user has left it once,
        // so they are not warned while still typing for the first time.
        const touched = new Set();
        let emailTakenMessage = null;
        let emailCheckTimer = null;
        let emailCheckController = null;

        function validateField(name) {
            const input = fieldInput(name);
            if (!input || !validators[name]) return true;
            let message = validators[name](input.value);
            if (!message && name === 'email' && emailTakenMessage) message = emailTakenMessage;
            if (name === 'password') updatePasswordRules(true);
            setFieldError(input, message);
            return !message;
        }

        function scheduleEmailCheck() {
            clearTimeout(emailCheckTimer);
            emailCheckController?.abort();
            emailTakenMessage = null;
            const email = fieldInput('email').value.trim();
            if (validators.email(email)) return; // Only ask the server about well-formed emails.

            emailCheckTimer = setTimeout(() => {
                emailCheckController = new AbortController();
                fetch(`${checkEmailUrl}?email=${encodeURIComponent(email)}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: emailCheckController.signal,
                    })
                    .then(response => response.ok ? response.json() : null)
                    .then(data => {
                        if (!data || fieldInput('email').value.trim() !== email) return;
                        emailTakenMessage = data.available ? null : (data.message || null);
                        if (emailTakenMessage) {
                            touched.add('email');
                        }
                        if (touched.has('email')) validateField('email');
                    })
                    .catch(error => {
                        if (error.name !== 'AbortError') console.warn('Email check failed:', error);
                    });
            }, 500);
        }

        Object.keys(validators).forEach(name => {
            const input = fieldInput(name);
            if (!input) return;

            input.addEventListener('input', () => {
                if (name === 'email') scheduleEmailCheck();
                if (name === 'password') updatePasswordRules(touched.has('password'));
                if (touched.has(name)) validateField(name);
                if (name === 'password' && touched.has('password_confirmation')) {
                    validateField('password_confirmation');
                }
            });

            input.addEventListener('blur', () => {
                // Don't nag about an untouched empty field when the user just tabs past it.
                if (!input.value && !touched.has(name)) return;
                touched.add(name);
                validateField(name);
            });
        });

        // Keep server-rendered errors (non-JS fallback) in sync once the user edits the field.
        form.querySelectorAll('.form-input').forEach(input => {
            if (input.parentElement.closest('.form-group')?.querySelector('.error-message')) {
                touched.add(input.name);
            }
        });

        function validateAll() {
            let firstInvalid = null;
            Object.keys(validators).forEach(name => {
                touched.add(name);
                if (!validateField(name) && !firstInvalid) firstInvalid = fieldInput(name);
            });
            firstInvalid?.focus();
            return !firstInvalid;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission
            if (!validateAll()) return;
            clearErrors();

            // Show loading state on the submit button
            setSubmitting(true);

            // Submit form via AJAX
            const formData = new FormData(this);
            const csrfToken = document.querySelector('input[name="_token"]').value;

            fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                })
                .then(async response => {
                    const contentType = response.headers.get('content-type') || '';
                    const body = contentType.includes('application/json') ? await response.json() : null;

                    if (response.ok) {
                        return body;
                    }

                    // Handle validation errors (422 status)
                    if (response.status === 422 && body?.errors) {
                        displayValidationErrors(body.errors);
                        throw new Error('Please fix the errors above and try again.');
                    }

                    const serverMessage = body?.message || response.statusText || 'Registration failed';
                    throw new Error(serverMessage);
                })
                .then(data => {
                    // Only show modal if registration succeeded
                    document.getElementById('expertPendingInfo').hidden = !appliesAsExpert();
                    modal.classList.add('active');
                    // Modal stays showing - user must verify email
                })
                .catch(error => {
                    console.error('Error:', error);
                    // On error, hide modal and re-enable form
                    modal.classList.remove('active');
                    setSubmitting(false);

                    // Only show alert if validation errors weren't displayed inline
                    if (!error.message.includes('errors above')) {
                        alert(`Registration failed. ${error.message}`);
                    }
                });
        });
    </script>
</body>

</html>