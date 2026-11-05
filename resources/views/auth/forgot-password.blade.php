<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Forgot Password - Mangrove Extent Mapping</title>
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

        .message {
            font-size: 13px;
            color: var(--fg-1e9e62, #1e9e62);
            background: var(--bg-ecf9ef, #ecf9ef);
            border: 1px solid var(--bd-c7eed0, #c7eed0);
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 18px;
        }

        .error-message {
            font-size: 12px;
            color: var(--fg-d04030, #d04030);
            margin-top: 8px;
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
        <h1 class="title">Forgot Password</h1>
        <p class="subtitle">Enter your email address and we will send you a link to reset your password.</p>

        @if(session('status'))
        <div class="message">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" id="forgotForm">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input id="email" type="email" name="email" class="form-input" placeholder="your@email.com" value="{{ old('email') }}" required autofocus autocomplete="email">
                @error('email')
                <div class="error-message">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="submit-btn" id="forgotSubmitBtn">
                <span class="submit-spinner" aria-hidden="true"></span>
                <span class="submit-label">Send Reset Link</span>
            </button>
        </form>

        <div class="footer">
            Remembered your password? <a href="{{ route('login') }}">Sign in</a>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @include('auth.partials.submit-loading-script', ['formId' => 'forgotForm', 'buttonId' => 'forgotSubmitBtn', 'busyLabel' => 'Sending...'])
        });
    </script>
    @include('partials.theme-toggle', ['floating' => true])
</body>

</html>