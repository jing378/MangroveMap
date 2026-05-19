<div class="modal-header">
    <div>
        <h3>User Details</h3>
        <p class="modal-subtitle">{{ $user->email }}</p>
    </div>
    <button type="button" class="modal-close" data-modal-close>&times;</button>
</div>
<div class="modal-body">
    <div class="table-container">
        <table class="table">
            <tbody>
                <tr>
                    <th>Name</th>
                    <td>{{ $user->name }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ $user->email }}</td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td>{{ ucfirst(str_replace('_', ' ', $user->role)) }}</td>
                </tr>
                <tr>
                    <th>Organization</th>
                    <td>{{ $user->organization ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td>{{ $user->phone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>{{ $user->email_verified_at ? 'Active' : 'Pending' }}</td>
                </tr>
                <tr>
                    <th>Joined</th>
                    <td>{{ $user->created_at?->format('M j, Y') ?? 'Unknown' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<div class="modal-actions">
    <button type="button" class="btn-secondary" data-modal-close>Close</button>
    <button type="button" class="btn-primary modal-edit-button" data-url="{{ route('admin.users.edit', $user) }}">Edit</button>
</div>