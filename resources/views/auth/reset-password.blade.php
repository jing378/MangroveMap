<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Reset Password - Mangrove Extent Mapping</title>
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
                #f5f7f6;
            color: #1a2e1a;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 22px;
            padding: 36px 32px;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.30);
        }

        .title {
            font-size: 26px;
            font-weight: 800;
            color: #1a2e1a;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 13px;
            line-height: 1.6;
            color: #5b7864;
            margin-bottom: 28px;
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
            padding: 12px 14px;
            border: 1.5px solid rgba(148, 163, 184, 0.55);
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            color: #1a2e1a;
            background: rgba(255, 255, 255, 0.95);
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
            color: #6b7280;
        }

        .password-toggle:hover svg {
            color: #374151;
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
            color: #d04030;
            margin-top: 8px;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #7a9a7a;
        }

        .footer a {
            color: #1e9e62;
            text-decoration: none;
            font-weight: 700;
        }

        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="card">
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

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ old('email', $email ?? '') }}">

            <div class="form-group">
                <label for="password" class="form-label">New Password</label>
                <div class="password-wrapper">
                    <input id="password" type="password" name="password" class="form-input" required>
                    <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                </div>
                @error('password')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <div class="password-wrapper">
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-input" required>
                    <button type="button" class="password-toggle" aria-label="Show password" title="Show password"></button>
                </div>
            </div>

            <button type="submit" class="submit-btn">Reset Password</button>
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
        });
    </script>
</body>

</html>