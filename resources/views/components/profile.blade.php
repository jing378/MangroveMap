@php
$currentUser = auth()->user();
$layoutToUse = 'layouts.enduser'; // default

if ($currentUser && $currentUser->role === 'admin') {
    $layoutToUse = 'layouts.admin';
} elseif ($currentUser && in_array($currentUser->role, ['end_user', 'expert'], true)) {
    $layoutToUse = 'layouts.enduser';
}
@endphp

@extends($layoutToUse)



@section('title', 'Profile - MangroveMap')

@section('content')
<div id="profile-section">
    <div class="page-header">
        <div class="page-title">My Profile</div>
        @if($layoutToUse === 'layouts.enduser')
        <a href="{{ $currentUser->homeRoute() }}" class="btn btn-secondary back-to-dashboard">
            <i class="bi bi-arrow-left"></i> <span>Back</span>
        </a>
        @endif
    </div>

    @if ($message = Session::get('success'))
    <div class="alert alert-success">
        {{ $message }}
    </div>
    @endif

    <div class="profile-layout">

        {{-- LEFT PANEL --}}
        <aside class="profile-sidebar">
            <div class="avatar-wrapper">
                @if($user->profile_image)
                <img src="{{ asset('storage/' . $user->profile_image) }}" alt="Profile" class="profile-avatar">
                @else
                <div class="profile-avatar avatar-placeholder">
                    <span class="avatar-initial">{{ substr($user->name ?? 'A', 0, 1) }}</span>
                </div>
                @endif
                <div class="avatar-ring"></div>
                <button type="button" class="avatar-camera-btn" onclick="document.getElementById('profile_image_sidebar').click()">
                    <i class="bi bi-camera-fill"></i>
                </button>
                <input type="file" id="profile_image_sidebar" name="profile_image_sidebar" class="file-input-hidden" accept="image/*" style="display: none;">
            </div>

            <h2 class="sidebar-name">{{ $user->name ?? 'Admin User' }}</h2>
            <p class="sidebar-email">{{ $user->email ?? 'admin@example.com' }}</p>
            <span class="sidebar-role">{{ ucfirst(str_replace('_', ' ', $user->role ?? 'User')) }}</span>

            <div class="sidebar-divider"></div>

            <ul class="sidebar-meta">
                @if($user->role === 'admin' && $user->organization)
                <li>
                    <i class="bi bi-building"></i>
                    <span>{{ $user->organization }}</span>
                </li>
                @endif
                @if($user->phone)
                <li>
                    <i class="bi bi-telephone"></i>
                    <span>{{ $user->phone }}</span>
                </li>
                @endif
                <li>
                    <i class="bi bi-shield-check"></i>
                    <span>Active Account</span>
                </li>
            </ul>
        </aside>

        {{-- RIGHT PANEL: FORM --}}
        <div class="profile-main">
            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="profile-form" id="profile-form">
                @csrf
                @method('PUT')
                <input type="file" id="profile_image" name="profile_image" class="file-input" accept="image/*" style="display: none;">

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-person"></i>
                        Personal Information
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control"
                                value="{{ $user->name }}" required>
                            @error('name')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="{{ $user->email }}" required>
                            <small class="email-helper">Must be a valid email address</small>
                            @error('email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        @if($user->role === 'admin')
                        <div class="form-group">
                            <label for="organization" class="form-label">Organization</label>
                            <input type="text" id="organization" name="organization" class="form-control"
                                value="{{ $user->organization ?? '' }}">
                            @error('organization')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                        @endif

                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                value="{{ $user->phone ?? '' }}"
                                required
                                pattern="[0-9]{11}"
                                maxlength="11"
                                inputmode="numeric"
                                placeholder="11 digits (e.g., 09123456789)"
                                title="Phone number must be exactly 11 digits">
                            <small class="phone-helper">Must be exactly 11 digits (e.g., 09123456789)</small>
                            @error('phone')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i>
                        Save Changes
                    </button>
                    @if($user->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    @else
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Back to Dashboard
                    </a>
                    @endif
                </div>
            </form>

            <!-- Password Update Form (separate from profile form) -->
            <form action="{{ route('profile.updatePassword') }}" method="POST" class="password-form" style="margin-top: 24px;">
                @csrf
                @method('PUT')

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-lock"></i>
                        Change Password
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="current_password" name="current_password" class="form-control password-input" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            @error('current_password')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" class="form-control password-input" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            @error('password')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control password-input" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password_confirmation')" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i>
                        Update Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Image Preview Modal -->
<div id="image-preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <h3 class="modal-title">Preview Image</h3>
        <img id="modal-preview-image" src="" alt="Preview" class="modal-preview-image">
        <div class="modal-actions">
            <button type="button" class="btn btn-primary" onclick="confirmImageUpload()">
                <i class="bi bi-check-circle"></i>
                OK
            </button>
            <button type="button" class="btn btn-secondary" onclick="cancelImageUpload()">
                <i class="bi bi-x-circle"></i>
                Cancel
            </button>
        </div>
    </div>
</div>

@endsection

@section('styles')
<style>
    @if($layoutToUse ==='layouts.enduser')

    /* Hide sidebar and menu toggle for end-user profile */
    .sidebar {
        display: none !important;
    }

    .mobile-menu-btn {
        display: none !important;
    }

    @endif .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        gap: 15px;
    }

    .back-to-dashboard {
        font-size: 26px;
        padding: 2px 12px;
        white-space: nowrap;
        background: #fff;
        color: #1a2e1a;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
        text-decoration: none;
        line-height: 1;
    }

    .back-to-dashboard:hover {
        background: #f5f7f6;
        border-color: #1e9e62;
        color: #1e9e62;
        transform: translateY(-1px);
    }

    .page-title {
        margin-bottom: 0 !important;
    }

    .page-title {
        font-size: 26px;
        font-weight: 800;
        color: #1a2e1a;
        margin-bottom: 20px;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid;
        margin-bottom: 20px;
    }

    .alert-success {
        background: #edf7f2;
        color: #1e9e62;
        border-color: #b0e0c0;
    }

    /* ── Landscape two-column wrapper ── */
    .profile-layout {
        display: grid;
        grid-template-columns: 240px 1fr;
        gap: 24px;
        align-items: start;
    }

    /* ── LEFT SIDEBAR ── */
    .profile-sidebar {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 28px 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        position: sticky;
        top: 20px;
    }

    .avatar-wrapper {
        position: relative;
        margin-bottom: 16px;
    }

    .profile-avatar {
        width: 88px;
        height: 88px;
        min-width: 88px;
        min-height: 88px;
        aspect-ratio: 1 / 1;
        flex-shrink: 0;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px #e0e8e0;
        display: block;
    }

    .avatar-placeholder {
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-initial {
        font-size: 34px;
        font-weight: 800;
        color: #fff;
        line-height: 1;
    }

    .avatar-ring {
        position: absolute;
        inset: -5px;
        border-radius: 50%;
        border: 2px dashed #b0e0c0;
        pointer-events: none;
    }

    .avatar-camera-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #ff7f50;
        color: #fff;
        border: 3px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 16px;
        box-shadow: 0 2px 8px rgba(255, 127, 80, 0.3);
    }

    .avatar-camera-btn:hover {
        background: #ff6b3d;
        box-shadow: 0 4px 12px rgba(255, 127, 80, 0.4);
        transform: scale(1.1);
    }

    .avatar-camera-btn:active {
        transform: scale(0.95);
    }

    .sidebar-name {
        font-size: 16px;
        font-weight: 800;
        color: #1a2e1a;
        margin: 0 0 4px;
    }

    .sidebar-email {
        font-size: 11px;
        color: #7a9a7a;
        margin: 0 0 10px;
        word-break: break-all;
    }

    .sidebar-role {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        color: #1e9e62;
        background: #edf7f2;
        border: 1px solid #b0e0c0;
        border-radius: 20px;
        padding: 3px 10px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }

    .sidebar-divider {
        width: 100%;
        height: 1px;
        background: #e0e8e0;
        margin: 18px 0;
    }

    .sidebar-meta {
        list-style: none;
        padding: 0;
        margin: 0;
        width: 100%;
        text-align: left;
    }

    .sidebar-meta li {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #3a5a3a;
        padding: 6px 0;
        border-bottom: 1px solid #f0f4f0;
    }

    .sidebar-meta li:last-child {
        border-bottom: none;
    }

    .sidebar-meta li i {
        color: #1e9e62;
        font-size: 13px;
        flex-shrink: 0;
    }

    .sidebar-meta li span {
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* ── RIGHT MAIN FORM ── */
    .profile-main {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 28px 32px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .form-section {
        margin-bottom: 28px;
    }

    .form-section-title {
        font-size: 13px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 7px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f0f4f0;
    }

    .form-section-title i {
        color: #1e9e62;
        font-size: 15px;
    }

    /* Two-column field rows */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-group {
        margin-bottom: 14px;
    }

    .form-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #3a5a3a;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Manrope', sans-serif;
        transition: all 0.15s;
        background: #fafbfa;
        color: #1a2e1a;
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: #1e9e62;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .form-error {
        font-size: 11px;
        color: #d04030;
        margin-top: 4px;
    }

    .phone-helper {
        display: block;
        font-size: 10px;
        color: #7a9a7a;
        margin-top: 4px;
        font-weight: 500;
    }

    .phone-helper.valid {
        color: #1e9e62;
    }

    .phone-helper.invalid {
        color: #d04030;
    }

    .email-helper {
        display: block;
        font-size: 10px;
        color: #7a9a7a;
        margin-top: 4px;
        font-weight: 500;
    }

    .email-helper.valid {
        color: #1e9e62;
    }

    .email-helper.invalid {
        color: #d04030;
    }

    /* Password toggle */
    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input {
        padding-right: 40px;
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        background: none;
        border: none;
        color: #7a9a7a;
        cursor: pointer;
        padding: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.2s ease;
        border-radius: 6px;
        opacity: 0;
        pointer-events: none;
    }

    .password-toggle.visible {
        opacity: 1;
        pointer-events: auto;
    }

    .password-toggle:hover {
        color: #1e9e62;
        background: rgba(30, 158, 98, 0.08);
    }

    .password-toggle:active {
        transform: scale(0.95);
    }

    .password-toggle[data-visible="true"] {
        color: #1e9e62;
    }

    /* File upload */
    .file-upload-container {
        position: relative;
    }

    .file-input {
        display: none;
    }

    .file-upload-box {
        border: 2px dashed #d4e0d4;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s;
        background: #fafbfa;
    }

    .file-upload-box:hover {
        border-color: #1e9e62;
        background: #f5f7f6;
    }

    .file-upload-box i {
        font-size: 28px;
        color: #1e9e62;
        display: block;
        margin-bottom: 6px;
    }

    .file-upload-box p {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
    }

    .file-info {
        font-size: 11px !important;
        color: #7a9a7a !important;
        margin-top: 4px !important;
        font-weight: 400 !important;
    }

    .preview-container {
        margin-top: 12px;
        text-align: center;
    }

    .preview-image {
        max-width: 100%;
        max-height: 160px;
        border-radius: 8px;
        border: 1px solid #e0e8e0;
    }

    .btn-remove-preview {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        padding: 5px 12px;
        background: #d04030;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Manrope', sans-serif;
        transition: background 0.15s;
    }

    .btn-remove-preview:hover {
        background: #b83828;
    }

    /* Actions */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 8px;
        padding-top: 20px;
        border-top: 1px solid #e0e8e0;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
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
        box-shadow: 0 4px 12px rgba(30, 158, 98, 0.25);
        transform: translateY(-1px);
    }

    .btn-secondary {
        background: #f5f7f6;
        color: #3a5a3a;
        border: 1px solid #e0e8e0;
    }

    .btn-secondary:hover {
        background: #edf2ed;
        border-color: #d4e0d4;
    }

    /* ── Responsive ── */
    @media (max-width: 900px) {
        .profile-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .profile-sidebar {
            position: static;
            padding: 24px 20px;
            text-align: center;
        }

        .avatar-wrapper {
            margin-bottom: 16px;
        }

        .sidebar-meta {
            display: flex;
            flex-direction: row;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            margin: 16px 0;
        }

        .sidebar-meta li {
            border-bottom: none;
            padding: 0;
            flex: 0 1 auto;
        }

        .sidebar-divider {
            display: block;
        }
    }

    @media (max-width: 768px) {
        .page-title {
            font-size: 22px;
            margin-bottom: 16px;
        }

        .profile-layout {
            gap: 16px;
        }

        .profile-sidebar {
            padding: 20px;
        }

        .profile-main {
            padding: 24px;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .form-section {
            margin-bottom: 20px;
        }

        .sidebar-name {
            font-size: 14px;
        }

        .sidebar-email {
            font-size: 10px;
        }
    }

    @media (max-width: 600px) {
        .page-header {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 10px !important;
            margin-bottom: 16px !important;
            flex-wrap: nowrap !important;
        }

        .back-to-dashboard {
            width: auto !important;
            font-size: 11px !important;
            padding: 9px 16px !important;
            gap: 6px !important;
            font-weight: 700 !important;
            margin: 0 !important;
            line-height: 1.2 !important;
        }

        .page-title {
            font-size: 18px !important;
            margin: 0 !important;
            white-space: nowrap !important;
        }

        .profile-layout {
            gap: 12px;
        }

        .profile-sidebar {
            padding: 16px;
            margin-bottom: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .avatar-wrapper {
            margin-bottom: 12px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
        }

        .avatar-initial {
            font-size: 30px;
        }

        .avatar-camera-btn {
            width: 28px;
            height: 28px;
            font-size: 14px;
        }

        .sidebar-name {
            font-size: 13px;
            margin: 0 0 2px;
            width: 100%;
            text-align: center;
            word-break: break-word;
        }

        .sidebar-email {
            font-size: 9px;
            margin: 0 0 8px;
            width: 100%;
            text-align: center;
            word-break: break-word;
        }

        .sidebar-role {
            font-size: 9px;
            padding: 2px 8px;
            margin-bottom: 8px;
        }

        .sidebar-divider {
            width: 100%;
            margin: 8px 0;
        }

        .sidebar-meta {
            gap: 12px;
            margin: 8px 0;
            width: 100%;
            justify-content: center;
        }

        .sidebar-meta li {
            font-size: 11px;
            border-bottom: none;
            padding: 0;
        }

        .sidebar-meta li i {
            font-size: 12px;
        }

        .profile-main {
            padding: 16px;
        }

        .form-row {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 10px;
        }

        .form-label {
            font-size: 10px;
            margin-bottom: 4px;
        }

        .form-control {
            padding: 8px 10px;
            font-size: 12px;
        }

        .form-section {
            margin-bottom: 16px;
        }

        .form-section-title {
            font-size: 12px;
            margin-bottom: 12px;
            gap: 5px;
        }

        .form-actions {
            flex-direction: column;
            gap: 10px;
            margin-top: 6px;
            padding-top: 16px;
        }

        .btn {
            justify-content: center;
            padding: 9px 16px;
            font-size: 11px;
            gap: 6px;
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 18px !important;
            margin: 0 !important;
            white-space: nowrap !important;
        }

        .back-to-dashboard {
            width: auto !important;
            font-size: 11px !important;
            padding: 9px 16px !important;
            gap: 6px !important;
            font-weight: 700 !important;
            margin: 0 !important;
            line-height: 1.2 !important;
        }

        .profile-sidebar {
            padding: 14px;
        }

        .profile-main {
            padding: 14px;
        }

        .form-section-title {
            font-size: 11px;
        }

        .form-label {
            font-size: 9px;
        }

        .form-control {
            padding: 7px 8px;
            font-size: 11px;
        }

        .btn {
            padding: 8px 14px;
            font-size: 10px;
        }

        .modal-content {
            padding: 20px;
            max-width: 95%;
        }

        .modal-preview-image {
            max-height: 200px;
            margin-bottom: 16px;
        }

        .modal-title {
            font-size: 14px;
            margin-bottom: 12px;
        }
    }

    /* ── Modal Styles ── */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-content {
        background: #fff;
        border-radius: 14px;
        padding: 28px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        max-width: 400px;
        width: 90%;
        text-align: center;
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .modal-title {
        font-size: 16px;
        font-weight: 800;
        color: #1a2e1a;
        margin: 0 0 16px;
    }

    .modal-preview-image {
        width: 100%;
        max-height: 280px;
        border-radius: 8px;
        border: 1px solid #e0e8e0;
        object-fit: cover;
        margin-bottom: 20px;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .modal-actions .btn {
        flex: 1;
        justify-content: center;
    }

    .password-form {
        margin-top: 20px;
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
    }

    .btn-warning:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
</style>
@endsection

@section('scripts')
<script>
    // Phone number validation
    const phoneInput = document.getElementById('phone');
    const phoneHelper = document.querySelector('.phone-helper');

    if (phoneInput) {
        // Only allow numbers
        phoneInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');

            // Limit to 11 digits
            if (e.target.value.length > 11) {
                e.target.value = e.target.value.slice(0, 11);
            }

            // Update helper text
            updatePhoneHelper();
        });

        // Update on paste
        phoneInput.addEventListener('paste', function(e) {
            setTimeout(() => {
                phoneInput.value = phoneInput.value.replace(/[^0-9]/g, '');
                if (phoneInput.value.length > 11) {
                    phoneInput.value = phoneInput.value.slice(0, 11);
                }
                updatePhoneHelper();
            }, 10);
        });

        // Check on blur
        phoneInput.addEventListener('blur', function(e) {
            if (e.target.value && e.target.value.length !== 11) {
                e.target.classList.add('is-invalid');
                phoneHelper.classList.add('invalid');
                phoneHelper.classList.remove('valid');
            }
        });

        // Check on focus
        phoneInput.addEventListener('focus', function(e) {
            if (e.target.value.length === 11) {
                e.target.classList.remove('is-invalid');
                phoneHelper.classList.remove('invalid');
                phoneHelper.classList.add('valid');
            }
        });

        function updatePhoneHelper() {
            const length = phoneInput.value.length;
            if (length === 11) {
                phoneInput.classList.remove('is-invalid');
                phoneHelper.classList.add('valid');
                phoneHelper.classList.remove('invalid');
                phoneHelper.textContent = '✓ Valid phone number';
            } else if (length > 0) {
                phoneInput.classList.add('is-invalid');
                phoneHelper.classList.add('invalid');
                phoneHelper.classList.remove('valid');
                phoneHelper.textContent = `Must be 11 digits (currently ${length})`;
            } else {
                phoneInput.classList.remove('is-invalid');
                phoneHelper.classList.remove('valid', 'invalid');
                phoneHelper.textContent = 'Must be exactly 11 digits (e.g., 09123456789)';
            }
        }
    }

    // Email validation
    const emailInput = document.getElementById('email');
    const emailHelper = document.querySelector('.email-helper');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (emailInput) {
        emailInput.addEventListener('input', function(e) {
            updateEmailHelper();
        });

        emailInput.addEventListener('blur', function(e) {
            if (e.target.value && !emailRegex.test(e.target.value)) {
                e.target.classList.add('is-invalid');
                emailHelper.classList.add('invalid');
                emailHelper.classList.remove('valid');
            } else if (e.target.value) {
                e.target.classList.remove('is-invalid');
                emailHelper.classList.remove('invalid');
                emailHelper.classList.add('valid');
            }
        });

        emailInput.addEventListener('focus', function(e) {
            if (emailRegex.test(e.target.value)) {
                e.target.classList.remove('is-invalid');
                emailHelper.classList.remove('invalid');
                emailHelper.classList.add('valid');
            }
        });

        function updateEmailHelper() {
            const value = emailInput.value.trim();
            if (!value) {
                emailInput.classList.remove('is-invalid');
                emailHelper.classList.remove('valid', 'invalid');
                emailHelper.textContent = 'Must be a valid email address';
            } else if (emailRegex.test(value)) {
                emailInput.classList.remove('is-invalid');
                emailHelper.classList.add('valid');
                emailHelper.classList.remove('invalid');
                emailHelper.textContent = '✓ Valid email address';
            } else {
                emailInput.classList.add('is-invalid');
                emailHelper.classList.add('invalid');
                emailHelper.classList.remove('valid');
                emailHelper.textContent = '✗ Invalid email format';
            }
        }
    }

    // Password visibility toggle
    function togglePasswordVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            button.setAttribute('data-visible', 'true');
            icon.classList.remove('bi-eye-fill');
            icon.classList.add('bi-eye-slash-fill');
        } else {
            input.type = 'password';
            button.setAttribute('data-visible', 'false');
            icon.classList.remove('bi-eye-slash-fill');
            icon.classList.add('bi-eye-fill');
        }
    }

    // Show/hide password toggle button based on input value
    function updatePasswordToggleVisibility(fieldId) {
        const input = document.getElementById(fieldId);
        const button = input.nextElementSibling;

        if (input.value.length > 0) {
            button.classList.add('visible');
        } else {
            button.classList.remove('visible');
            // Reset to password type when field is cleared
            if (input.type === 'text') {
                input.type = 'password';
                button.setAttribute('data-visible', 'false');
                const icon = button.querySelector('i');
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        }
    }

    // Initialize password fields with toggle listeners
    const passwordFields = ['current_password', 'password', 'password_confirmation'];
    passwordFields.forEach(fieldId => {
        const input = document.getElementById(fieldId);
        if (input) {
            // Show toggle when user starts typing
            input.addEventListener('input', function() {
                updatePasswordToggleVisibility(fieldId);
            });

            // Show toggle on paste
            input.addEventListener('paste', function() {
                setTimeout(() => {
                    updatePasswordToggleVisibility(fieldId);
                }, 10);
            });

            // Update on focus
            input.addEventListener('focus', function() {
                if (input.value.length > 0) {
                    input.nextElementSibling.classList.add('visible');
                }
            });

            // Hide toggle if field becomes empty on blur
            input.addEventListener('blur', function() {
                if (input.value.length === 0) {
                    input.nextElementSibling.classList.remove('visible');
                }
            });
        }
    });

    // Handle sidebar camera button file input
    const sidebarFileInput = document.getElementById('profile_image_sidebar');
    const mainFileInput = document.getElementById('profile_image');
    const profileForm = document.getElementById('profile-form');
    let pendingImageFile = null;
    let tempImageDataUrl = null;

    sidebarFileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            pendingImageFile = this.files[0];
            showImagePreviewModal(this.files[0]);
        }
    });

    function showImagePreviewModal(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            tempImageDataUrl = e.target.result;
            document.getElementById('modal-preview-image').src = e.target.result;
            document.getElementById('image-preview-modal').style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    function confirmImageUpload() {
        if (pendingImageFile && tempImageDataUrl) {
            // Create a new FileList-like object using DataTransfer
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(pendingImageFile);

            // Set the files on the main input
            mainFileInput.files = dataTransfer.files;

            // Update avatar display temporarily
            const avatarWrapper = document.querySelector('.avatar-wrapper');
            let avatarElement = avatarWrapper.querySelector('.profile-avatar');

            // If avatar is a placeholder div, replace with img tag
            if (avatarElement && avatarElement.classList.contains('avatar-placeholder')) {
                const newImg = document.createElement('img');
                newImg.src = tempImageDataUrl;
                newImg.alt = 'Profile';
                newImg.className = 'profile-avatar';
                avatarElement.replaceWith(newImg);
            } else if (avatarElement && avatarElement.tagName === 'IMG') {
                // If it's already an img, just update the src
                avatarElement.src = tempImageDataUrl;
            }

            // Close modal
            document.getElementById('image-preview-modal').style.display = 'none';

            // Reset sidebar input but KEEP pendingImageFile for form submission
            sidebarFileInput.value = '';
        }
    }

    function cancelImageUpload() {
        document.getElementById('image-preview-modal').style.display = 'none';
        sidebarFileInput.value = '';
        pendingImageFile = null;
        tempImageDataUrl = null;
    }

    // Intercept form submission using fetch to ensure file is properly included
    profileForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Create a fresh FormData and manually add all fields
        const formData = new FormData();

        // Add CSRF token
        const csrfToken = document.querySelector('input[name="_token"]').value;
        formData.append('_token', csrfToken);
        formData.append('_method', 'PUT');

        // Add form fields
        formData.append('name', document.getElementById('name').value);
        formData.append('email', document.getElementById('email').value);
        formData.append('organization', document.getElementById('organization').value);
        formData.append('phone', document.getElementById('phone').value);

        // Add the file if we have one
        if (pendingImageFile) {
            formData.append('profile_image', pendingImageFile, pendingImageFile.name);
        }

        // Log what we're sending
        console.log('Submitting form with file:', pendingImageFile ? pendingImageFile.name + ' (' + pendingImageFile.type + ')' : 'no file');
        console.log('File size:', pendingImageFile ? pendingImageFile.size + ' bytes' : 'N/A');

        // Submit via fetch
        fetch(profileForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text}`);
                    });
                }
                return response.text();
            })
            .then(data => {
                console.log('Success response:', data);
                // Reset state after successful submission
                pendingImageFile = null;
                tempImageDataUrl = null;

                // Show success message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-success';
                alertDiv.textContent = 'Profile updated successfully!';
                const profileSection = document.getElementById('profile-section');
                profileSection.insertBefore(alertDiv, profileSection.firstChild);

                // Scroll to top to see message
                window.scrollTo(0, 0);

                // Reload to show changes after a brief delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })
            .catch(error => {
                console.error('Full error details:', error);
                console.error('Error message:', error.message);
                console.error('Error stack:', error.stack);
                alert('Error saving profile: ' + error.message + '\n\nCheck browser console for details.');
            });
    });

    // Close modal when clicking outside
    document.getElementById('image-preview-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            cancelImageUpload();
        }
    });
</script>
@endsection