<div class="modal-header">
    <div>
        <h3>Edit User</h3>
        <p class="modal-subtitle">{{ $user->email }}</p>
    </div>
    <button type="button" class="modal-close" data-modal-close>&times;</button>
</div>
<div class="modal-body">
    <div id="userModalError" class="alert alert-danger modal-errors" style="display:none;"></div>
    <form id="userModalForm" action="{{ route('admin.users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-section">
            <div class="form-group">
                <label for="modal-name">Name <span class="required">*</span></label>
                <input id="modal-name" name="name" type="text" value="{{ old('name', $user->name) }}" required placeholder="Full name">
                <div id="modal-name-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="modal-email">Email <span class="required">*</span></label>
                <input id="modal-email" name="email" type="email" value="{{ old('email', $user->email) }}" required placeholder="user@example.com">
                <div id="modal-email-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="modal-role">Role <span class="required">*</span></label>
                <select id="modal-role" name="role" required>
                    <option value="">Select a role</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                    <option value="end_user" {{ old('role', $user->role) === 'end_user' ? 'selected' : '' }}>Resident</option>
                    <option value="expert" {{ old('role', $user->role) === 'expert' ? 'selected' : '' }}>Expert</option>
                </select>
                <div id="modal-role-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="modal-organization">Organization</label>
                <input id="modal-organization" name="organization" type="text" value="{{ old('organization', $user->organization) }}" placeholder="Organization name">
                <div id="modal-organization-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-group">
                <label for="modal-phone">Phone</label>
                <input id="modal-phone" name="phone" type="tel" inputmode="numeric" pattern="[0-9]*" maxlength="11" value="{{ old('phone', $user->phone) }}" placeholder="09123456789">
                <div id="modal-phone-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section password-section">
            <p class="password-hint"><i class="bi bi-lock"></i> Leave blank to keep current password</p>
            <div class="form-group">
                <label for="modal-password">New Password</label>
                <div class="password-wrapper">
                    <input id="modal-password" name="password" type="password" placeholder="Min. 8 characters" autocomplete="new-password">
                    <button type="button" class="toggle-pw" data-target="modal-password" tabindex="-1"><i class="bi bi-eye"></i></button>
                </div>
                <div id="modal-password-error" class="form-error error-message" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label for="modal-password_confirmation">Confirm New Password</label>
                <div class="password-wrapper">
                    <input id="modal-password_confirmation" name="password_confirmation" type="password" placeholder="Repeat new password" autocomplete="new-password">
                    <button type="button" class="toggle-pw" data-target="modal-password_confirmation" tabindex="-1"><i class="bi bi-eye"></i></button>
                </div>
                <div id="modal-password_confirmation-error" class="form-error error-message" style="display:none;"></div>
            </div>
        </div>
    </form>
</div>
<div class="modal-actions">
    <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
    <button type="submit" form="userModalForm" class="btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
</div>