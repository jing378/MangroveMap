<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Verify Email - MangroveMap</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            font-family: 'Manrope', system-ui, -apple-system, Segoe UI, sans-serif;
            background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
            color: #1a2e1a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verify-container {
            background: #fff;
            border-radius: 14px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .verify-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .verify-icon {
            width: 80px;
            height: 80px;
            background: #edf7f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 40px;
        }

        .verify-title {
            font-size: 24px;
            font-weight: 800;
            color: #1a2e1a;
            margin-bottom: 8px;
        }

        .verify-subtitle {
            font-size: 13px;
            color: #7a9a7a;
            line-height: 1.6;
        }

        .verify-content {
            background: #f5f7f6;
            border: 1px solid #e0e8e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .verify-message {
            font-size: 13px;
            color: #3a5a3a;
            line-height: 1.8;
            margin-bottom: 12px;
        }

        .verify-message strong {
            color: #1e9e62;
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
            background: #edf7f2;
            color: #1e9e62;
            border-color: #b0e0c0;
        }

        .alert-info {
            background: #eff6ff;
            color: #0369a1;
            border-color: #7dd3fc;
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
            padding: 11px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            font-family: 'Manrope', sans-serif;
        }

        .btn-primary {
            background: #1e9e62;
            color: #fff;
        }

        .btn-primary:hover {
            background: #16a34a;
            box-shadow: 0 4px 12px rgba(30, 158, 98, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f7f6;
            color: #3a5a3a;
            border: 1.5px solid #e0e8e0;
        }

        .btn-secondary:hover {
            background: #edf2ed;
            border-color: #d4e0d4;
        }

        .verify-footer {
            text-align: center;
            font-size: 12px;
            color: #7a9a7a;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e8e0;
        }

        .verify-footer a {
            color: #1e9e62;
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
</head>

<body>
    <div class="verify-container">
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
            <form method="POST" action="{{ route('verification.send') }}" style="width: 100%;">
                @csrf
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="bi bi-arrow-clockwise"></i>
                    Resend Verification Email
                </button>
            </form>

            <a href="{{ route('dashboard') }}" class="btn btn-secondary" style="width: 100%;">
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="verify-footer">
            <p>Need help? <a href="#">Contact our support team</a></p>
        </div>
    </div>
</body>

</html>