@php
    // Accounts made before names were split only have the full name; prefill the parts from it.
    $nameParts = $user->first_name === null ? \App\Models\User::parseName((string) $user->name) : [];
    $currentRole = old('role', $user->role);
    $initials = collect(explode(' ', $user->name))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
@endphp
<div class="modal-header">
    <div class="modal-user-heading">
        <div class="user-avatar">{{ $initials }}</div>
        <div>
            <h3>Edit User</h3>
            <p class="modal-subtitle">{{ $user->name }} · {{ $user->roleLabel() }}</p>
        </div>
    </div>
    <button type="button" class="modal-close" data-modal-close aria-label="Close" title="Close">&times;</button>
</div>
<div class="modal-body">
    <div id="userModalError" class="alert alert-danger modal-errors" style="display:none;"></div>
    <form id="userModalForm" class="user-edit-form" action="{{ route('admin.users.update', $user) }}" method="POST" novalidate>
        @csrf
        @method('PUT')

        <fieldset class="edit-group">
            <legend><i class="bi bi-person"></i> Name</legend>
            <div class="edit-grid">
                <div class="form-group">
                    <label for="modal-first_name">First Name <span class="required">*</span></label>
                    <input id="modal-first_name" name="first_name" type="text" maxlength="100" autocomplete="off" required
                        value="{{ old('first_name', $user->first_name ?? ($nameParts['first_name'] ?? '')) }}" placeholder="Juan">
                    <div id="modal-first_name-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-middle_name">Middle Name <span class="label-optional">(Optional)</span></label>
                    <input id="modal-middle_name" name="middle_name" type="text" maxlength="100" autocomplete="off"
                        value="{{ old('middle_name', $user->middle_name ?? ($nameParts['middle_name'] ?? '')) }}" placeholder="Santos">
                    <div id="modal-middle_name-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-last_name">Last Name <span class="required">*</span></label>
                    <input id="modal-last_name" name="last_name" type="text" maxlength="100" autocomplete="off" required
                        value="{{ old('last_name', $user->last_name ?? ($nameParts['last_name'] ?? '')) }}" placeholder="Dela Cruz">
                    <div id="modal-last_name-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-suffix">Suffix <span class="label-optional">(Optional)</span></label>
                    <input id="modal-suffix" name="suffix" type="text" maxlength="10" autocomplete="off" list="modal-suffix-options"
                        value="{{ old('suffix', $user->suffix ?? ($nameParts['suffix'] ?? '')) }}" placeholder="Jr., Sr., III">
                    <datalist id="modal-suffix-options">
                        <option value="Jr."></option>
                        <option value="Sr."></option>
                        <option value="II"></option>
                        <option value="III"></option>
                        <option value="IV"></option>
                    </datalist>
                    <div id="modal-suffix-error" class="form-error error-message" style="display:none;"></div>
                </div>
            </div>
        </fieldset>

        <fieldset class="edit-group">
            <legend><i class="bi bi-envelope"></i> Account</legend>
            <div class="edit-grid">
                <div class="form-group">
                    <label for="modal-email">Email Address <span class="required">*</span></label>
                    <input id="modal-email" name="email" type="email" maxlength="255" autocomplete="off" required
                        value="{{ old('email', $user->email) }}" placeholder="user@example.com">
                    <div id="modal-email-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-phone">Contact No. <span class="label-optional">(Optional)</span></label>
                    <input id="modal-phone" name="phone" type="tel" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11"
                        value="{{ old('phone', $user->phone) }}" placeholder="09123456789">
                    <div id="modal-phone-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-role">Role <span class="required">*</span></label>
                    <select id="modal-role" name="role" required>
                        @foreach (\App\Models\User::ROLE_LABELS as $roleValue => $roleName)
                        <option value="{{ $roleValue }}" @selected($currentRole === $roleValue)>{{ $roleName }}</option>
                        @endforeach
                    </select>
                    <div id="modal-role-error" class="form-error error-message" style="display:none;"></div>
                    @if ($user->hasPendingExpertApplication())
                    <small class="field-hint field-hint--notice"><i class="bi bi-hourglass-split"></i> Expert application pending. Choosing Expert also approves it.</small>
                    @endif
                </div>
                <div class="form-group">
                    <label for="modal-organization">Organization / Affiliation <span class="label-optional">(Optional)</span></label>
                    <input id="modal-organization" name="organization" type="text" maxlength="255" autocomplete="off"
                        value="{{ old('organization', $user->organization) }}" placeholder="e.g. DENR CENRO Maasin, SLSU">
                    <div id="modal-organization-error" class="form-error error-message" style="display:none;"></div>
                </div>
            </div>
        </fieldset>

        <fieldset class="edit-group">
            <legend><i class="bi bi-lock"></i> Password</legend>
            <p class="password-hint">Leave both fields blank to keep the current password.</p>
            <div class="edit-grid">
                <div class="form-group">
                    <label for="modal-password">New Password</label>
                    <div class="password-wrapper">
                        <input id="modal-password" name="password" type="password" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="toggle-pw" data-target="modal-password" aria-label="Show password" title="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    <div id="modal-password-error" class="form-error error-message" style="display:none;"></div>
                </div>
                <div class="form-group">
                    <label for="modal-password_confirmation">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input id="modal-password_confirmation" name="password_confirmation" type="password" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="toggle-pw" data-target="modal-password_confirmation" aria-label="Show password" title="Show password"><i class="bi bi-eye" aria-hidden="true"></i></button>
                    </div>
                    <div id="modal-password_confirmation-error" class="form-error error-message" style="display:none;"></div>
                </div>
            </div>
            <ul class="password-rules" aria-label="Password requirements">
                <li>At least 8 characters</li>
                <li>One uppercase letter</li>
                <li>One lowercase letter</li>
                <li>One number</li>
            </ul>
        </fieldset>
    </form>
</div>
<div class="modal-actions">
    <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
    <button type="submit" form="userModalForm" class="btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
</div>
