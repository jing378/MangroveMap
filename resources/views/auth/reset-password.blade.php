<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reset Password - Mangrove Extent Mapping</title>
    <link rel="icon" type="image/png" href="/icon-192.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
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
            align-items: center;
            justify-content: center;
            background: radial-gradient(900px 500px at 10% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                radial-gradient(900px 500px at 90% 20%, rgba(59, 130, 246, 0.10), transparent 55%),
                var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-1a2e1a, #1a2e1a);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.92);
            border-radius: 22px;
            padding: 36px 32px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.30);
            position: relative;
        }

        .title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            color: var(--fg-1a2e1a, #1a2e1a);
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            text-align: center;
            font-size: 13px;
            line-height: 1.6;
            color: var(--fg-5b7864, #5b7864);
            margin-bottom: 28px;
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
            padding: 12px 14px;
            border: 1.5px solid rgba(148, 163, 184, 0.55);
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--fg-1a2e1a, #1a2e1a);
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.95);
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #1e9e62;
            box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.12);
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
            color: var(--fg-6b7280, #6b7280);
        }

        .password-toggle:hover svg {
            color: var(--fg-374151, #374151);
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
            padding: 14px 16px;
            background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 14px;
            box-shadow: 0 4px 14px rgba(30, 158, 98, 0.18);
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(30, 158, 98, 0.22);
        }

        .error-message {
            font-size: 12px;
            color: var(--fg-d04030, #d04030);
            margin-top: 8px;
        }

        .alert-error {
            font-size: 13px;
            line-height: 1.5;
            color: var(--fg-b42318, #b42318);
            background: var(--bg-fdf0ee, #fdf0ee);
            border: 1px solid var(--bd-e8b8b0, #e8b8b0);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }

        .alert-error a {
            display: block;
            margin-top: 6px;
            color: inherit;
            font-weight: 800;
        }

        .password-rules {
            list-style: none;
            margin: 10px 0 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            font-size: 11.5px;
            color: var(--fg-7a8a7f, #7a8a7f);
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
            border: 1.5px solid var(--bd-b8c4bc, #b8c4bc);
        }

        .password-rules li.met {
            color: var(--fg-1e9e62, #1e9e62);
        }

        .password-rules li.met::before {
            border-color: #1e9e62;
            background: #1e9e62 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M3 6.2l2 2 4-4.4' fill='none' stroke='%23fff' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/10px no-repeat;
        }

        .password-rules li.unmet {
            color: var(--fg-d04030, #d04030);
        }

        .password-rules li.unmet::before {
            border-color: #d04030;
        }

        @media (max-width: 330px) {
            .password-rules {
                grid-template-columns: 1fr;
            }
        }

        @include('auth.partials.submit-loading-styles')

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #7a9a7a;
        }

        .footer a {
            color: var(--fg-1e9e62, #1e9e62);
            text-decoration: none;
            font-weight: 700;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
    @include('partials.scrollbar-styles')
</head>

<body>
    <div class="card">
        @include('auth.partials.back-button', ['href' => route('login'), 'title' => 'Back to sign in'])
        @include('auth.partials.brand')
        <h1 class="title">Reset Password</h1>
        <p class="subtitle">Set a new password for your account.</p>

        @if(session('status'))
        <div class="status-message">
            {{ session('status') }}<br>
            Redirecting to login after 5 seconds...
        </div>
        <script>
            setTimeout(function() {
                window.location.href = "{{ route('login') }}";
            }, 5000);
        </script>
        @endif

        {{-- The email is a hidden field, so its errors (expired or already-used link, unknown email) are shown here. --}}
        @error('email')
        <div class="alert-error" role="alert">
            {{ $message }}
            <a href="{{ route('password.request') }}">Request a new reset link</a>
        </div>
        @enderror

        <form method="POST" action="{{ route('password.update') }}" id="resetForm">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            {{-- Lets password managers save the new password under the right account. --}}
            <input type="email" name="email" value="{{ old('email', $email ?? '') }}" autocomplete="username" hidden readonly>

            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
                <div class="password-wrapper">
                    <input id="password" type="password" name="password" class="form-input" required autocomplete="new-password" aria-describedby="passwordRules">
                    <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                </div>
                <ul class="password-rules" id="passwordRules" aria-live="polite">
                    <li data-rule="length">At least 8 characters</li>
                    <li data-rule="upper">One uppercase letter</li>
                    <li data-rule="lower">One lowercase letter</li>
                    <li data-rule="number">One number</li>
                </ul>
                @error('password')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <div class="password-wrapper">
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-input" required autocomplete="new-password">
                    <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="resetSubmitBtn">
                <span class="submit-spinner" aria-hidden="true"></span>
                <span class="submit-label">Reset Password</span>
            </button>
        </form>

        <div class="footer">
            Back to <a href="{{ route('login') }}">Sign in</a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.password-wrapper').forEach(wrapper => {
                const input = wrapper.querySelector('input.form-input');
                const toggle = wrapper.querySelector('.password-toggle');
                if (!input || !toggle) return;

                const showIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-8a3 3 0 100 6 3 3 0 000-6z"/></svg>';
                const hideIcon = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M1.39 4.22l1.41 1.41A11.94 11.94 0 001 12s4 7 11 7c2.24 0 4.33-.66 6.1-1.79l1.44 1.44 1.42-1.42L2.81 2.81 1.39 4.22zM12 17a5 5 0 01-5-5c0-.66.14-1.29.39-1.87l1.87 1.87A3 3 0 0012 15a2.99 2.99 0 002.76-1.85l1.89 1.89A4.98 4.98 0 0112 17zm1.61-3.41l-2.1-2.1A1 1 0 0111 10c0-.55.45-1 1-1 .27 0 .52.11.7.29l1.91 1.91a1.01 1.01 0 01-.0 1.39zM12 7a5 5 0 014.61 3.11l1.53-1.53A11.96 11.96 0 0012 5c-2.24 0-4.33.66-6.1 1.79l1.5 1.5A9.94 9.94 0 0112 7z"/></svg>';

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

            // Live checklist for the same rules the server enforces (shared with sign-up).
            const passwordInput = document.getElementById('password');
            const updateRules = () => {
                const value = passwordInput.value;
                const results = {
                    length: value.length >= 8,
                    upper: /[A-Z]/.test(value),
                    lower: /[a-z]/.test(value),
                    number: /[0-9]/.test(value),
                };
                document.querySelectorAll('#passwordRules li').forEach(item => {
                    const met = !!results[item.dataset.rule];
                    item.classList.toggle('met', met);
                    item.classList.toggle('unmet', !met && value !== '');
                });
            };
            passwordInput.addEventListener('input', updateRules);
            updateRules();

            @include('auth.partials.submit-loading-script', ['formId' => 'resetForm', 'buttonId' => 'resetSubmitBtn', 'busyLabel' => 'Resetting...'])
        });
    </script>
    @include('partials.theme-toggle', ['floating' => true])
</body>

</html>