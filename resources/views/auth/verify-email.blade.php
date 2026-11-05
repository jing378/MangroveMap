<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    @include('partials.theme')
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verify Email - MangroveMap</title>
    <link rel="icon" type="image/png" href="/icon-192.png" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Same backdrop and card as the other auth pages (login, forgot and reset password). */
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

        .verify-container {
            background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.92);
            border-radius: 22px;
            padding: 36px 32px;
            max-width: 460px;
            width: 100%;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.30);
        }

        .verify-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .verify-icon {
            width: 80px;
            height: 80px;
            background: var(--bg-edf7f2, #edf7f2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 40px;
        }

        .verify-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--fg-1a2e1a, #1a2e1a);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .verify-subtitle {
            font-size: 13px;
            color: var(--fg-5b7864, #5b7864);
            line-height: 1.6;
        }

        .verify-content {
            background: var(--bg-f5f7f6, #f5f7f6);
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .verify-message {
            font-size: 13px;
            color: var(--fg-3a5a3a, #3a5a3a);
            line-height: 1.8;
            margin-bottom: 12px;
        }

        .verify-message strong {
            color: var(--fg-1e9e62, #1e9e62);
        }

        .verify-message:last-child {
            margin-bottom: 0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .alert-success {
            background: var(--bg-edf7f2, #edf7f2);
            color: var(--fg-1e9e62, #1e9e62);
            border-color: var(--bd-b0e0c0, #b0e0c0);
        }

        .alert-info {
            background: var(--bg-eff6ff, #eff6ff);
            color: var(--fg-0369a1, #0369a1);
            border-color: var(--bd-7dd3fc, #7dd3fc);
        }

        .verify-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 20px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            font-family: 'Manrope', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
            color: #fff;
            box-shadow: 0 4px 14px rgba(30, 158, 98, 0.18);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 18px rgba(30, 158, 98, 0.22);
            transform: translateY(-1px);
        }

        @include('auth.partials.submit-loading-styles')

        .btn-secondary {
            background: var(--bg-f5f7f6, #f5f7f6);
            color: var(--fg-3a5a3a, #3a5a3a);
            border: 1.5px solid var(--bd-e0e8e0, #e0e8e0);
        }

        .btn-secondary:hover {
            background: var(--bg-edf2ed, #edf2ed);
            border-color: var(--bd-d4e0d4, #d4e0d4);
        }

        .verify-footer {
            text-align: center;
            font-size: 12px;
            color: #7a9a7a;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
        }

        .verify-footer a {
            color: var(--fg-1e9e62, #1e9e62);
            text-decoration: none;
            font-weight: 600;
        }

        .verify-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .verify-container {
                padding: 24px;
            }

            .verify-title {
                font-size: 20px;
            }

            .verify-icon {
                width: 64px;
                height: 64px;
                font-size: 32px;
            }
        }
    </style>
    @include('partials.scrollbar-styles')
</head>

<body>
    <div class="verify-container">
        @include('auth.partials.brand')
        <div class="verify-header">
            <div class="verify-icon"><i class="bi bi-envelope-check"></i></div>
            <h1 class="verify-title">Verify your email</h1>
            <p class="verify-subtitle">Check your inbox and confirm your email address to get started</p>
        </div>

        @if(session('message'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            {{ session('message') }}
        </div>
        @endif

        <div class="verify-content">
            <div class="verify-message">
                <strong>{{ auth()->user()->email ?? 'Your email' }}</strong>
            </div>
            <div class="verify-message">
                We sent a confirmation link to your email. Click the link in the email to verify your account and get full access to MangroveMap.
            </div>
            <div class="verify-message">
                Didn't receive the email? Check your spam or junk folder.
            </div>
        </div>

        <div class="verify-actions">
            <form method="POST" action="{{ route('verification.send') }}" style="width: 100%;" id="resendForm">
                @csrf
                <button type="submit" class="btn btn-primary submit-btn" style="width: 100%;" id="resendSubmitBtn">
                    <span class="submit-spinner" aria-hidden="true"></span>
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                    <span class="submit-label">Resend Verification Email</span>
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" style="width: 100%;">
                @csrf
                <input type="hidden" name="to_login" value="1">
                <button type="submit" class="btn btn-secondary" style="width: 100%;">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Go to Login
                </button>
            </form>
        </div>

        <div class="verify-footer">
            <p>Still nothing after a few minutes? Press Resend above. If the email address is wrong, go to login and create a new account with the correct one.</p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @include('auth.partials.submit-loading-script', ['formId' => 'resendForm', 'buttonId' => 'resendSubmitBtn', 'busyLabel' => 'Sending...'])
        });
    </script>
    @include('partials.theme-toggle', ['floating' => true])
</body>

</html>