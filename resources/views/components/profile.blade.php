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
    </div>


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
                <button type="button" class="avatar-camera-btn" onclick="document.getElementById('profile_image_sidebar').click()" aria-label="Change profile photo" title="Change profile photo">
                    <i class="bi bi-camera-fill"></i>
                </button>
                <input type="file" id="profile_image_sidebar" name="profile_image_sidebar" class="file-input-hidden" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
            </div>

            <h2 class="sidebar-name">{{ $user->name ?? 'Admin User' }}</h2>
            <p class="sidebar-email">{{ $user->email ?? 'admin@example.com' }}</p>
            <span class="sidebar-role">{{ $user->roleLabel() }}</span>

            <div class="sidebar-divider"></div>

            <ul class="sidebar-meta">
                @if(($user->isExpert() || $user->isAdmin()) && $user->organization)
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

                    @php
                    // Accounts not yet split into name parts fall back to parsing the full name.
                    $fallbackName = $user->first_name === null ? \App\Models\User::parseName((string) $user->name) : [];
                    @endphp
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control"
                                value="{{ old('first_name', $user->first_name ?? ($fallbackName['first_name'] ?? '')) }}"
                                maxlength="100" autocomplete="given-name" required>
                            @error('first_name')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="middle_name" class="form-label">Middle Name <span class="label-optional">(Optional)</span></label>
                            <input type="text" id="middle_name" name="middle_name" class="form-control"
                                value="{{ old('middle_name', $user->middle_name ?? ($fallbackName['middle_name'] ?? '')) }}"
                                maxlength="100" autocomplete="additional-name" aria-describedby="middle_name_hint">
                            <small class="field-hint" id="middle_name_hint">Leave empty if no middle name. Only the initial is shown on your profile.</small>
                            @error('middle_name')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control"
                                value="{{ old('last_name', $user->last_name ?? ($fallbackName['last_name'] ?? '')) }}"
                                maxlength="100" autocomplete="family-name" required>
                            @error('last_name')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="suffix" class="form-label">Suffix <span class="label-optional">(Optional)</span></label>
                            <input type="text" id="suffix" name="suffix" class="form-control"
                                value="{{ old('suffix', $user->suffix ?? ($fallbackName['suffix'] ?? '')) }}"
                                maxlength="10" placeholder="Jr., Sr., III" autocomplete="honorific-suffix"
                                list="suffix_options" aria-describedby="suffix_hint">
                            <datalist id="suffix_options">
                                <option value="Jr."></option>
                                <option value="Sr."></option>
                                <option value="II"></option>
                                <option value="III"></option>
                                <option value="IV"></option>
                                <option value="V"></option>
                            </datalist>
                            <small class="field-hint" id="suffix_hint">Leave empty if no suffix.</small>
                            @error('suffix')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control"
                                value="{{ $user->email }}" required>
                            <small class="email-helper">Must be a valid email address</small>
                            @error('email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

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

                    @if($user->isExpert() || $user->isAdmin())
                    <div class="form-row">
                        <div class="form-group">
                            {{-- Same field as the expert application on the sign-up form. --}}
                            <label for="organization" class="form-label">Organization / Affiliation</label>
                            <input type="text" id="organization" name="organization" class="form-control"
                                value="{{ old('organization', $user->organization ?? '') }}"
                                placeholder="e.g. DENR CENRO Maasin, SLSU" maxlength="255" autocomplete="organization"
                                @if($user->isExpert()) required @endif>
                            @error('organization')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @endif
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
            <form action="{{ route('profile.updatePassword') }}" method="POST" class="password-form" style="margin-top: 24px;"
                data-confirm="Update password?" data-confirm-message="You use the new password the next time you log in."
                data-confirm-label="Update" data-busy-label="Updating password...">
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
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('current_password')" aria-label="Show password" title="Show password">
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
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')" aria-label="Show password" title="Show password">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                            @error('password')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control password-input" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password_confirmation')" aria-label="Show password" title="Show password">
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

            @if($user->hasPendingExpertApplication())
            <!-- Expert application waiting for an admin -->
            <div class="form-section expert-application-section" id="expert-application">
                <h3 class="form-section-title">
                    <i class="bi bi-person-badge"></i>
                    Expert Application
                </h3>
                <div class="expert-status is-pending">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <div>
                        <strong>Waiting for review</strong>
                        <span>
                            Sent {{ optional($user->expert_applied_at)->format('M d, Y') ?? 'recently' }}.
                            An administrator will review it, and you'll get a notification with the result.
                        </span>
                    </div>
                </div>
            </div>
            @elseif($user->canReapplyAsExpert())
            <!-- Rejected expert application: the resident can send a new one -->
            @php $expertErrors = $errors->expertApplication; @endphp
            <form action="{{ route('profile.expert-application') }}" method="POST" enctype="multipart/form-data"
                class="expert-application-form" id="expert-application"
                data-confirm="Send expert application?"
                data-confirm-message="An administrator will review your new application. Your account stays a resident account until it is approved."
                data-confirm-label="Send" data-busy-label="Sending application...">
                @csrf

                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-person-badge"></i>
                        Expert Application
                    </h3>

                    <div class="expert-status is-rejected">
                        <i class="bi bi-x-circle" aria-hidden="true"></i>
                        <div>
                            <strong>Your last application was not approved</strong>
                            <span>
                                @if($user->expert_reviewed_at)Reviewed {{ $user->expert_reviewed_at->format('M d, Y') }}.@endif
                                @if($user->expert_rejection_notes)
                                Reason: {{ $user->expert_rejection_notes }}
                                @endif
                            </span>
                            <span>You can apply again with a new certificate or proof of expertise.</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reapply_organization" class="form-label">Organization / Affiliation</label>
                            <input type="text" id="reapply_organization" name="organization" class="form-control"
                                value="{{ $expertErrors->any() ? old('organization') : $user->organization }}"
                                placeholder="e.g. DENR CENRO Maasin, SLSU" maxlength="255" autocomplete="organization" required>
                            @if($expertErrors->has('organization'))<div class="form-error">{{ $expertErrors->first('organization') }}</div>@endif
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reapply_certificate" class="form-label">Certificate / Proof of Expertise</label>
                            <label class="certificate-drop" for="reapply_certificate">
                                <input type="file" id="reapply_certificate" name="expert_certificate" class="certificate-input"
                                    accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required
                                    aria-describedby="reapply_certificate_hint">
                                <i class="bi bi-upload" aria-hidden="true"></i>
                                <span id="reapplyCertificateText">Choose a file</span>
                            </label>
                            <small class="expert-hint" id="reapply_certificate_hint">PDF, JPG, or PNG up to 5 MB, e.g. a diploma, license, or certificate of employment. Only administrators can see it. It replaces the certificate from your last application.</small>
                            @if($expertErrors->has('expert_certificate'))<div class="form-error">{{ $expertErrors->first('expert_certificate') }}</div>@endif
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="reapply_qualifications" class="form-label">Qualifications &amp; Experience <span class="expert-optional">(Optional)</span></label>
                            <textarea id="reapply_qualifications" name="expert_qualifications" class="form-control" rows="3" maxlength="2000"
                                placeholder="Anything that answers the reason above, or other qualifications you'd like to add.">{{ $expertErrors->any() ? old('expert_qualifications') : $user->expert_qualifications }}</textarea>
                            @if($expertErrors->has('expert_qualifications'))<div class="form-error">{{ $expertErrors->first('expert_qualifications') }}</div>@endif
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i>
                        Send Application
                    </button>
                </div>
            </form>
            @endif
        </div>

    </div>
</div>

<!-- Image Preview Modal -->
<div id="image-preview-modal" class="modal" style="display: none;">
    <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="crop-modal-title">
        <h3 class="modal-title" id="crop-modal-title">Adjust Profile Photo</h3>
        <p class="crop-hint">Drag to reposition. Zoom to choose what shows inside the circle.</p>
        <div class="crop-stage" id="crop-stage" tabindex="0"
            aria-label="Photo crop area. Use arrow keys to move and plus or minus to zoom.">
            <img id="modal-preview-image" src="" alt="Photo being cropped" class="crop-image" draggable="false">
            <div class="crop-mask" aria-hidden="true"></div>
        </div>
        <div class="crop-zoom">
            <i class="bi bi-zoom-out" aria-hidden="true"></i>
            <input type="range" id="crop-zoom" min="1" max="4" step="0.01" value="1" aria-label="Zoom">
            <i class="bi bi-zoom-in" aria-hidden="true"></i>
            <button type="button" class="btn btn-secondary" id="crop-reset" title="Reset position and zoom">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </button>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-primary" id="crop-confirm" onclick="confirmImageUpload()">
                <i class="bi bi-check-circle"></i>
                Use Photo
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
    .page-header {
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
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        background: var(--bg-f5f7f6, #f5f7f6);
        border-color: #1e9e62;
        color: var(--fg-1e9e62, #1e9e62);
        transform: translateY(-1px);
    }

    .page-title {
        margin-bottom: 0 !important;
    }

    .page-title {
        font-size: 26px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 20px;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        border: 1px solid;
        margin-bottom: 20px;
    }

    .alert-success {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        border-color: var(--bd-b0e0c0, #b0e0c0);
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
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        border: 3px solid var(--bd-ffffff, #fff);
        box-shadow: 0 0 0 2px var(--bd-e0e8e0, #e0e8e0);
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
        border: 2px dashed var(--bd-b0e0c0, #b0e0c0);
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
        border: 3px solid var(--bd-ffffff, #fff);
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
        color: var(--fg-1a2e1a, #1a2e1a);
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
        color: var(--fg-1e9e62, #1e9e62);
        background: var(--bg-edf7f2, #edf7f2);
        border: 1px solid var(--bd-b0e0c0, #b0e0c0);
        border-radius: 20px;
        padding: 3px 10px;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }

    .sidebar-divider {
        width: 100%;
        height: 1px;
        background: var(--bg-e0e8e0, #e0e8e0);
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
        color: var(--fg-3a5a3a, #3a5a3a);
        padding: 6px 0;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
    }

    .sidebar-meta li:last-child {
        border-bottom: none;
    }

    .sidebar-meta li i {
        color: var(--fg-1e9e62, #1e9e62);
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
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 7px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
    }

    .form-section-title i {
        color: var(--fg-1e9e62, #1e9e62);
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
        color: var(--fg-3a5a3a, #3a5a3a);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Manrope', sans-serif;
        transition: all 0.15s;
        background: var(--bg-fafbfa, #fafbfa);
        color: var(--fg-1a2e1a, #1a2e1a);
        box-sizing: border-box;
    }

    .form-control:focus {
        outline: none;
        border-color: #1e9e62;
        background: var(--bg-ffffff, #fff);
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .form-error {
        font-size: 11px;
        color: var(--fg-d04030, #d04030);
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
        color: var(--fg-1e9e62, #1e9e62);
    }

    .phone-helper.invalid {
        color: var(--fg-d04030, #d04030);
    }

    .email-helper,
    .field-hint {
        display: block;
        font-size: 10px;
        color: #7a9a7a;
        margin-top: 4px;
        font-weight: 500;
    }

    .label-optional {
        font-weight: 600;
        color: #7a9a7a;
    }

    .email-helper.valid {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .email-helper.invalid {
        color: var(--fg-d04030, #d04030);
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
        color: var(--fg-1e9e62, #1e9e62);
        background: rgba(30, 158, 98, 0.08);
    }

    .password-toggle:active {
        transform: scale(0.95);
    }

    .password-toggle[data-visible="true"] {
        color: var(--fg-1e9e62, #1e9e62);
    }

    /* File upload */
    .file-upload-container {
        position: relative;
    }

    .file-input {
        display: none;
    }

    .file-upload-box {
        border: 2px dashed var(--bd-d4e0d4, #d4e0d4);
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s;
        background: var(--bg-fafbfa, #fafbfa);
    }

    .file-upload-box:hover {
        border-color: #1e9e62;
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .file-upload-box i {
        font-size: 28px;
        color: var(--fg-1e9e62, #1e9e62);
        display: block;
        margin-bottom: 6px;
    }

    .file-upload-box p {
        margin: 0;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-3a5a3a, #3a5a3a);
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
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
        border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
    }

    /* Shared size and type for every button on this page; variants only change colours. */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-sizing: border-box;
        padding: 10px 18px;
        border: 1px solid transparent; /* same height whether or not a variant shows a border */
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.15s;
        text-decoration: none;
    }

    .btn i {
        font-size: 1.1em;
        line-height: 1;
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
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-3a5a3a, #3a5a3a);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    }

    .btn-secondary:hover {
        background: var(--bg-edf2ed, #edf2ed);
        border-color: var(--bd-d4e0d4, #d4e0d4);
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
        background: var(--bg-ffffff, #fff);
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
        color: var(--fg-1a2e1a, #1a2e1a);
        margin: 0 0 16px;
    }

    .crop-hint {
        font-size: 13px;
        color: var(--fg-6a8a6a, #6a8a6a);
        margin: -8px 0 14px;
    }

    .crop-stage {
        --crop-size: min(260px, 70vw);
        position: relative;
        width: var(--crop-size);
        height: var(--crop-size);
        margin: 0 auto 16px;
        overflow: hidden;
        border-radius: 12px;
        background: #1a2e1a;
        cursor: grab;
        touch-action: none;
        user-select: none;
    }

    .crop-stage:active {
        cursor: grabbing;
    }

    .crop-stage:focus-visible {
        outline: 3px solid #1e9e62;
        outline-offset: 3px;
    }

    .crop-image {
        position: absolute;
        top: 0;
        left: 0;
        max-width: none;
        transform-origin: 0 0;
        pointer-events: none;
    }

    /* Circle matches the round avatar; the corners are dimmed because they get cut off. */
    .crop-mask {
        position: absolute;
        inset: 0;
        border-radius: 50%;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.55);
        border: 2px solid rgba(var(--bd-ffffff-rgb, 255, 255, 255), 0.85);
        pointer-events: none;
    }

    .crop-zoom {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .crop-zoom input[type="range"] {
        flex: 1;
        accent-color: #1e9e62;
    }

    /* Reset sits beside the zoom slider, so it must not stretch like the full-width mobile buttons. */
    .crop-zoom .btn {
        width: auto;
        flex: none;
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

    /* Colour only: size, padding and font come from .btn (including its mobile sizes). */
    .btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: #fff;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
    }

    .btn-warning:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }

    /* Expert application (residents with a pending or rejected application) */
    .expert-application-form,
    .expert-application-section {
        margin-top: 24px;
    }

    .expert-status {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 14px;
        margin-bottom: 18px;
        border: 1px solid;
        border-radius: 10px;
        font-size: 13px;
        line-height: 1.5;
    }

    .expert-status > .bi {
        font-size: 20px;
        line-height: 1.2;
    }

    .expert-status strong,
    .expert-status span {
        display: block;
    }

    .expert-status.is-pending {
        background: var(--bg-fdf5e8, #fdf5e8);
        border-color: var(--bd-e8cc98, #e8cc98);
        color: var(--fg-925a12, #925a12);
    }

    .expert-status.is-rejected {
        background: var(--bg-fdf0ee, #fdf0ee);
        border-color: var(--bd-e8b8b0, #e8b8b0);
        color: var(--fg-b73318, #b73318);
    }

    .certificate-drop {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1px dashed var(--bd-b8cbb8, #b8cbb8);
        border-radius: 10px;
        background: var(--bg-f7faf7, #f7faf7);
        color: var(--fg-3a5a3a, #3a5a3a);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        overflow-wrap: anywhere;
    }

    .certificate-drop:hover,
    .certificate-drop:focus-within {
        border-color: #1e9e62;
    }

    .certificate-drop .bi {
        font-size: 18px;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .certificate-input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .expert-hint {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .expert-optional {
        font-weight: 500;
        color: #7a9a7a;
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

        const label = input.type === 'password' ? 'Hide password' : 'Show password';
        button.setAttribute('aria-label', label);
        button.title = label;

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

    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const MAX_SOURCE_IMAGE_BYTES = 20 * 1024 * 1024; // the cropped 512px upload stays well under the 2 MB server limit

    sidebarFileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            // The original can be large: it is cropped and resized in the browser before upload.
            const problem = !ALLOWED_IMAGE_TYPES.includes(file.type)
                ? 'The profile photo must be a JPG, PNG, GIF, or WebP file.'
                : (file.size > MAX_SOURCE_IMAGE_BYTES ? 'Please choose a photo smaller than 20 MB.' : null);

            if (problem) {
                this.value = '';
                window.AppActions.notify(problem, 'error');
                return;
            }

            showImagePreviewModal(file);
        }
    });

    // ---- Crop & zoom ----------------------------------------------------------------------------
    const CROP_OUTPUT_SIZE = 512; // px, square; displayed as a circle everywhere
    const cropModal = document.getElementById('image-preview-modal');
    const cropStage = document.getElementById('crop-stage');
    const cropImage = document.getElementById('modal-preview-image');
    const cropZoom = document.getElementById('crop-zoom');
    const crop = { objectUrl: null, size: 0, naturalW: 0, naturalH: 0, baseScale: 1, scale: 1, x: 0, y: 0 };

    function renderCrop() {
        // Keep the image covering the whole frame so no empty edges end up in the photo.
        const width = crop.naturalW * crop.scale;
        const height = crop.naturalH * crop.scale;
        crop.x = Math.min(0, Math.max(crop.size - width, crop.x));
        crop.y = Math.min(0, Math.max(crop.size - height, crop.y));
        cropImage.style.transform = `translate(${crop.x}px, ${crop.y}px) scale(${crop.scale})`;
    }

    function setCropZoom(zoom, anchorX = crop.size / 2, anchorY = crop.size / 2) {
        zoom = Math.min(Number(cropZoom.max), Math.max(Number(cropZoom.min), zoom));
        const newScale = crop.baseScale * zoom;
        // Zoom around the anchor point so the spot under the cursor/centre stays put.
        const imageX = (anchorX - crop.x) / crop.scale;
        const imageY = (anchorY - crop.y) / crop.scale;
        crop.scale = newScale;
        crop.x = anchorX - imageX * newScale;
        crop.y = anchorY - imageY * newScale;
        cropZoom.value = zoom;
        renderCrop();
    }

    function resetCrop() {
        crop.size = cropStage.clientWidth;
        crop.baseScale = Math.max(crop.size / crop.naturalW, crop.size / crop.naturalH);
        crop.scale = crop.baseScale;
        crop.x = (crop.size - crop.naturalW * crop.scale) / 2;
        crop.y = (crop.size - crop.naturalH * crop.scale) / 2;
        cropZoom.value = 1;
        renderCrop();
    }

    function showImagePreviewModal(file) {
        if (crop.objectUrl) URL.revokeObjectURL(crop.objectUrl);
        crop.objectUrl = URL.createObjectURL(file);

        cropImage.onload = function() {
            crop.naturalW = cropImage.naturalWidth;
            crop.naturalH = cropImage.naturalHeight;
            cropModal.style.display = 'flex';
            resetCrop(); // needs the modal visible to measure the frame
            cropStage.focus();
        };
        cropImage.onerror = function() {
            sidebarFileInput.value = '';
            window.AppActions.notify('That image could not be opened. Please try another photo.', 'error');
        };
        cropImage.src = crop.objectUrl;
    }

    let dragStart = null;
    cropStage.addEventListener('pointerdown', function(e) {
        dragStart = { pointerX: e.clientX, pointerY: e.clientY, x: crop.x, y: crop.y };
        cropStage.setPointerCapture(e.pointerId);
    });
    cropStage.addEventListener('pointermove', function(e) {
        if (!dragStart) return;
        crop.x = dragStart.x + (e.clientX - dragStart.pointerX);
        crop.y = dragStart.y + (e.clientY - dragStart.pointerY);
        renderCrop();
    });
    ['pointerup', 'pointercancel'].forEach(type => cropStage.addEventListener(type, () => { dragStart = null; }));

    cropStage.addEventListener('wheel', function(e) {
        e.preventDefault();
        const rect = cropStage.getBoundingClientRect();
        const zoom = Number(cropZoom.value) * (e.deltaY < 0 ? 1.08 : 1 / 1.08);
        setCropZoom(zoom, e.clientX - rect.left, e.clientY - rect.top);
    }, { passive: false });

    cropStage.addEventListener('keydown', function(e) {
        const step = e.shiftKey ? 30 : 10;
        const moves = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
        if (moves[e.key]) {
            crop.x += moves[e.key][0];
            crop.y += moves[e.key][1];
            renderCrop();
        } else if (e.key === '+' || e.key === '=') {
            setCropZoom(Number(cropZoom.value) + 0.1);
        } else if (e.key === '-') {
            setCropZoom(Number(cropZoom.value) - 0.1);
        } else if (e.key === 'Enter') {
            confirmImageUpload();
        } else {
            return;
        }
        e.preventDefault();
    });

    cropZoom.addEventListener('input', () => setCropZoom(Number(cropZoom.value)));
    document.getElementById('crop-reset').addEventListener('click', resetCrop);

    function cropToBlob() {
        const canvas = document.createElement('canvas');
        canvas.width = CROP_OUTPUT_SIZE;
        canvas.height = CROP_OUTPUT_SIZE;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; // transparent PNG/GIF areas become white instead of black in JPEG
        ctx.fillRect(0, 0, CROP_OUTPUT_SIZE, CROP_OUTPUT_SIZE);
        ctx.imageSmoothingQuality = 'high';
        const sourceSize = crop.size / crop.scale;
        ctx.drawImage(cropImage, -crop.x / crop.scale, -crop.y / crop.scale, sourceSize, sourceSize,
            0, 0, CROP_OUTPUT_SIZE, CROP_OUTPUT_SIZE);
        return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    }

    function closeCropModal() {
        cropModal.style.display = 'none';
        sidebarFileInput.value = '';
        if (crop.objectUrl) {
            URL.revokeObjectURL(crop.objectUrl);
            crop.objectUrl = null;
        }
    }

    async function confirmImageUpload() {
        if (!crop.objectUrl) return;

        const blob = await cropToBlob();
        if (!blob) {
            window.AppActions.notify('The photo could not be cropped. Please try another image.', 'error');
            return;
        }
        pendingImageFile = new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' });
        if (tempImageDataUrl) URL.revokeObjectURL(tempImageDataUrl);
        tempImageDataUrl = URL.createObjectURL(blob);

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

        // Close modal; pendingImageFile is kept for form submission
        closeCropModal();

        // Save right away so "Use Photo" actually uploads it (runs the usual confirm + validation).
        profileForm.requestSubmit();
    }

    function cancelImageUpload() {
        closeCropModal();
    }

    // Intercept form submission using fetch to ensure file is properly included
    profileForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Create a fresh FormData and manually add all fields
        const formData = new FormData();

        // Add CSRF token
        const csrfToken = document.querySelector('input[name="_token"]').value;
        formData.append('_token', csrfToken);
        formData.append('_method', 'PUT');

        // Add form fields
        formData.append('first_name', document.getElementById('first_name').value);
        formData.append('middle_name', document.getElementById('middle_name').value);
        formData.append('last_name', document.getElementById('last_name').value);
        formData.append('suffix', document.getElementById('suffix').value);
        formData.append('email', document.getElementById('email').value);
        // Organization is only rendered for admins.
        const organizationInput = document.getElementById('organization');
        if (organizationInput) {
            formData.append('organization', organizationInput.value);
        }
        formData.append('phone', document.getElementById('phone').value);

        // Add the file if we have one
        if (pendingImageFile) {
            formData.append('profile_image', pendingImageFile, pendingImageFile.name);
        }

        // Confirmation → Processing → Success or Error (partials.action-feedback), then submit via fetch.
        const outcome = await window.AppActions.run({
            confirm: { title: 'Save changes?', message: 'Your profile details are updated.', confirmLabel: 'Save' },
            button: e.submitter || profileForm.querySelector('button[type="submit"]'),
            busyLabel: 'Saving...',
            task: async () => {
                // Ask for JSON so validation errors come back as 422 instead of a redirect that looks like success.
                const response = await fetch(profileForm.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok) {
                    console.error('Profile save failed:', response.status, payload);
                    const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
                    throw new Error(
                        firstError
                        || (response.status === 413 ? 'The profile photo is too large. Please choose a smaller image.' : null)
                        || `Unable to save your profile (HTTP ${response.status}). Please try again.`
                    );
                }
                return payload;
            },
            // Same success toast as the other actions (e.g. deleting drone imagery).
            successMessage: payload => payload?.message || 'Your profile was updated.',
        });
        if (outcome.status !== 'done') return;

        // Reset state after successful submission
        pendingImageFile = null;
        mainFileInput.value = '';
        if (tempImageDataUrl) URL.revokeObjectURL(tempImageDataUrl);
        tempImageDataUrl = null;

        // Refresh the page in place instead of reloading, so the toast stays visible.
        applySavedProfile(outcome.result || {});
    });

    // Results of full-page submits (e.g. the password form) use the same success toast.
    @if (session('success'))
    document.addEventListener('DOMContentLoaded', () => window.AppActions?.notify(@json(session('success'))));
    @endif
    @if (session('error'))
    document.addEventListener('DOMContentLoaded', () => window.AppActions?.notify(@json(session('error')), 'error'));
    @endif

    // Expert reapplication: show the chosen certificate's file name.
    (() => {
        const input = document.getElementById('reapply_certificate');
        const label = document.getElementById('reapplyCertificateText');
        if (!input || !label) return;
        input.addEventListener('change', () => {
            label.textContent = input.files && input.files[0] ? input.files[0].name : 'Choose a file';
        });
    })();

    function applySavedProfile(saved) {
        if (saved.name) {
            document.querySelectorAll('.sidebar-name, .sidebar-profile-name')
                .forEach(el => { el.textContent = saved.name; });
            document.querySelectorAll('.sidebar-profile-link')
                .forEach(el => el.setAttribute('aria-label', `View profile for ${saved.name}`));
        }
        if (saved.email) {
            document.querySelectorAll('.sidebar-email, .sidebar-profile-email')
                .forEach(el => { el.textContent = saved.email; });
        }
        if ('organization' in saved) {
            const orgItem = document.querySelector('.sidebar-meta .bi-building')?.nextElementSibling;
            if (orgItem && saved.organization) {
                orgItem.textContent = saved.organization;
            } else if (orgItem) {
                orgItem.parentElement.remove();
            } else if (saved.organization && document.getElementById('organization')) {
                const li = document.createElement('li');
                li.innerHTML = '<i class="bi bi-building"></i><span></span>';
                li.querySelector('span').textContent = saved.organization;
                document.querySelector('.sidebar-meta')?.prepend(li);
            }
        }
        if (saved.phone) {
            const phoneItem = document.querySelector('.sidebar-meta .bi-telephone')?.nextElementSibling;
            if (phoneItem) phoneItem.textContent = saved.phone;
        }
        if (saved.profile_image_url) {
            // Profile card avatar (the crop preview is a temporary blob URL).
            const avatar = document.querySelector('.avatar-wrapper .profile-avatar');
            if (avatar?.tagName === 'IMG') avatar.src = saved.profile_image_url;

            // Navigation sidebar avatar(s): replace the initial with the photo if needed.
            document.querySelectorAll('.sidebar-profile-avatar').forEach(slot => {
                let img = slot.querySelector('img');
                if (!img) {
                    img = document.createElement('img');
                    slot.replaceChildren(img);
                }
                img.src = saved.profile_image_url;
                img.alt = `${saved.name || ''}'s profile photo`.trim();
            });
        }
    }

    // Close modal when clicking outside
    document.getElementById('image-preview-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            cancelImageUpload();
        }
    });
</script>
@endsection