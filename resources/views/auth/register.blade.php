<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Register - Web-based Mangrove Extent Mapping and Genus Classification and Data Driven Planting Suitability Recommendation</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
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

        /* Left Panel - Register Form */
        .register-left {
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

        .register-container {
            width: 100%;
            max-width: 400px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.82);
            border-radius: 18px;
            padding: 38px 34px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.10);
            border: 1px solid rgba(148, 163, 184, 0.45);
            backdrop-filter: blur(10px);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .register-header {
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

        .register-subtitle {
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

        .register-footer {
            text-align: center;
            margin-top: 20px;
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
                min-height: 100vh;
                padding: 40px 20px;
            }

            /* Adjust card styling for better mobile experience */
            .register-card {
                padding: 28px 20px;
                max-width: 100%;
                margin: 0 auto;
                max-height: 85vh;
                overflow-y: auto;
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
            .register-left {
                padding: 30px 16px;
            }

            .register-card {
                padding: 24px 18px;
                max-height: 80vh;
            }

            .back-btn {
                top: 20px;
                left: 20px;
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
                margin-bottom: 16px;
            }
        }

        /* Landscape orientation on mobile - keep right panel hidden */
        @media (max-width: 768px) and (orientation: landscape) {
            .register-left {
                min-height: 100vh;
                padding: 20px;
            }

            .register-card {
                padding: 20px 18px;
                max-height: 90vh;
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
                padding: 32px 28px;
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
                    Web-based Mangrove Extent Mapping, Species Classification,<br>
                    and Data-driven Planting Suitability Recommendations
                </div>
                <div class="hero-sub">
                    A single platform for mapping mangrove extent, classifying species from imagery, and recommending
                    planting sites using data-driven suitability analysis.
                </div>
                <div class="hero-meta" aria-label="Key modules">
                    <span class="chip">Extent mapping</span>
                    <span class="chip">Species classifier</span>
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

                <form method="POST" action="/register">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-input" placeholder="Your full name" required value="{{ old('name') }}">
                        @error('name')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="your@email.com" required value="{{ old('email') }}">
                        @error('email')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="phone" class="form-input" placeholder="09123456789" pattern="[0-9]{1,11}" maxlength="11" value="{{ old('phone') }}">
                        @error('phone')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-wrapper">
                            <input id="password" type="password" name="password" class="form-input" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                        </div>
                        @error('password')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="password-wrapper">
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-input" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                        </div>
                        @error('password_confirmation')
                        <div class="error-message">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="submit-btn">Create Account</button>
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
        </div>
    </div>

    <script>
        const form = document.querySelector('form');
        const modal = document.getElementById('loadingModal');
        const submitBtn = form.querySelector('.submit-btn');

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
                const input = form.querySelector(`input[name="${field}"]`);
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
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Clear field-specific errors when user types
        form.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function() {
                // Remove error styling from this field
                this.classList.remove('error-input');

                // Remove error message for this field
                const formGroup = this.closest('.form-group');
                if (formGroup) {
                    const errorMsg = formGroup.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission
            clearErrors();

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';

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
                    modal.classList.add('active');
                    // Modal stays showing - user must verify email
                })
                .catch(error => {
                    console.error('Error:', error);
                    // On error, hide modal and re-enable form
                    modal.classList.remove('active');
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';

                    // Only show alert if validation errors weren't displayed inline
                    if (!error.message.includes('errors above')) {
                        alert(`Registration failed. ${error.message}`);
                    }
                });
        });
    </script>
</body>

</html>