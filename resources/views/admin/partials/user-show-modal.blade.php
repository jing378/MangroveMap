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
                <tr>
                    <th>Last login</th>
                    <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if(isset($userActivities) && $userActivities->isNotEmpty())
    <h4 style="margin:16px 0 8px;font-size:13px;font-weight:700;color:#1a2e1a;">Recent activity</h4>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Module</th>
                    <th>Status</th>
                    <th>When</th>
                </tr>
            </thead>
            <tbody>
                @foreach($userActivities->take(5) as $activity)
                <tr>
                    <td>{{ $activity->activity }}</td>
                    <td>{{ $activity->module }}</td>
                    <td><span class="badge {{ $activity->status }}">{{ ucfirst($activity->status) }}</span></td>
                    <td>{{ $activity->created_at?->diffForHumans() ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
<div class="modal-actions">
    <button type="button" class="btn-secondary" data-modal-close>Close</button>
    <button type="button" class="btn-primary modal-edit-button" data-url="{{ route('admin.users.edit', $user) }}">Edit</button>
</div>