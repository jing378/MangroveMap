@extends('layouts.admin')

@section('title', 'Users Management - MangroveMap')

@section('content')
<div id="users-section">
    <div class="page-title">Users Management</div>

    <div class="grid cols-4">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value green">{{ $totalUsers }}</div>
            <div class="stat-change positive">{{ $newSignups }} new this month</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Users</div>
            <div class="stat-value">{{ $activeUsers }}</div>
            <div class="stat-change positive">{{ $totalUsers ? round($activeUsers / $totalUsers * 100) : 0 }}% active rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">New Signups</div>
            <div class="stat-value">{{ $newSignups }}</div>
            <div class="stat-change positive">Since last 30 days</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Admin Users</div>
            <div class="stat-value">{{ $adminUsers }}</div>
            <div class="stat-change">System administrators</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-people"></i> User Directory</div>
            <div class="section-actions">
                <button class="btn-primary"><i class="bi bi-plus-lg"></i> Add User</button>
                <button class="btn-secondary"><i class="bi bi-arrow-clockwise"></i> Sync Users</button>
            </div>
        </div>

        <div class="filter-bar">
            <input type="text" class="search-input" placeholder="Search users by name or email...">
            <select class="filter-select">
                <option value="">All Roles</option>
                <option value="admin">Administrator</option>
                <option value="manager">Manager</option>
                <option value="scientist">Scientist</option>
                <option value="analyst">Analyst</option>
                <option value="viewer">Viewer</option>
            </select>
            <select class="filter-select">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="pending">Pending</option>
                <option value="suspended">Suspended</option>
            </select>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    @php
                    $initials = collect(explode(' ', $user->name))
                    ->map(fn($part) => strtoupper(substr($part, 0, 1)))
                    ->join('');
                    $roleClass = match ($user->role) {
                    'admin' => 'admin',
                    'manager' => 'manager',
                    'scientist' => 'scientist',
                    'analyst' => 'analyst',
                    'viewer' => 'viewer',
                    default => 'secondary',
                    };
                    $roleLabel = match ($user->role) {
                    'admin' => 'Administrator',
                    'end_user' => 'Resident',
                    'expert' => 'Expert',
                    default => ucwords(str_replace('_', ' ', $user->role)),
                    };
                    $statusLabel = $user->email_verified_at ? 'Active' : 'Pending';
                    $statusClass = $user->email_verified_at ? 'success' : 'secondary';
                    @endphp
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="user-name">{{ $user->name }}</div>
                                    <div class="user-info">{{ $user->organization ?? $roleLabel }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user->email }}</td>
                        <td><span class="role-badge {{ $roleClass }}">{{ $roleLabel }}</span></td>
                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                        <td>{{ $user->created_at?->format('M j, Y') ?? 'Unknown' }}</td>
                        <td>{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</td>
                        <td class="actions-cell">
                            <a class="action-btn view" href="{{ route('admin.users.show', $user) }}" title="View" data-action="show"><i class="bi bi-eye"></i></a>
                            <a class="action-btn edit" href="{{ route('admin.users.edit', $user) }}" title="Edit" data-action="edit"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-btn delete" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">No users found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button class="pagination-btn{{ $users->onFirstPage() ? ' disabled' : '' }}">← Previous</button>
            <div class="pagination-info">Page {{ $users->currentPage() }} of {{ $users->lastPage() }} ({{ $users->total() }} users)</div>
            <button class="pagination-btn{{ $users->hasMorePages() ? '' : ' disabled' }}">Next →</button>
        </div>
    </div>

    <div class="grid cols-2">
        <div class="section">
            <div class="section-title"><i class="bi bi-graph-up"></i> Users by Role</div>
            @php
            $roleStats = [
            'admin' => $roleCounts['admin'] ?? 0,
            'manager' => $roleCounts['manager'] ?? 0,
            'scientist' => $roleCounts['scientist'] ?? 0,
            'analyst' => $roleCounts['analyst'] ?? 0,
            'viewer' => $roleCounts['viewer'] ?? 0,
            ];
            $totalRoleCount = array_sum($roleStats) ?: 1;
            @endphp
            <div class="role-stats">
                <div class="role-stat">
                    <div class="role-stat-label">Administrator</div>
                    <div class="role-stat-bar">
                        <div class="role-stat-fill admin" style="--w: {{ round($roleStats['admin'] / $totalRoleCount * 100) }}%"></div>
                    </div>
                    <div class="role-stat-count">{{ $roleStats['admin'] }} users</div>
                </div>
                <div class="role-stat">
                    <div class="role-stat-label">Manager</div>
                    <div class="role-stat-bar">
                        <div class="role-stat-fill manager" style="--w: {{ round($roleStats['manager'] / $totalRoleCount * 100) }}%"></div>
                    </div>
                    <div class="role-stat-count">{{ $roleStats['manager'] }} users</div>
                </div>
                <div class="role-stat">
                    <div class="role-stat-label">Scientist</div>
                    <div class="role-stat-bar">
                        <div class="role-stat-fill scientist" style="--w: {{ round($roleStats['scientist'] / $totalRoleCount * 100) }}%"></div>
                    </div>
                    <div class="role-stat-count">{{ $roleStats['scientist'] }} users</div>
                </div>
                <div class="role-stat">
                    <div class="role-stat-label">Analyst</div>
                    <div class="role-stat-bar">
                        <div class="role-stat-fill analyst" style="--w: {{ round($roleStats['analyst'] / $totalRoleCount * 100) }}%"></div>
                    </div>
                    <div class="role-stat-count">{{ $roleStats['analyst'] }} users</div>
                </div>
                <div class="role-stat">
                    <div class="role-stat-label">Viewer</div>
                    <div class="role-stat-bar">
                        <div class="role-stat-fill viewer" style="--w: {{ round($roleStats['viewer'] / $totalRoleCount * 100) }}%"></div>
                    </div>
                    <div class="role-stat-count">{{ $roleStats['viewer'] }} users</div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title"><i class="bi bi-shield-lock"></i> Access Permissions</div>
            <div class="permissions-list">
                <div class="permission-item">
                    <input type="checkbox" id="perm1" checked disabled>
                    <label for="perm1">View Dashboard</label>
                </div>
                <div class="permission-item">
                    <input type="checkbox" id="perm2" checked disabled>
                    <label for="perm2">Access Data</label>
                </div>
                <div class="permission-item">
                    <input type="checkbox" id="perm3" disabled>
                    <label for="perm3">Upload Datasets</label>
                </div>
                <div class="permission-item">
                    <input type="checkbox" id="perm4" disabled>
                    <label for="perm4">Manage Models</label>
                </div>
                <div class="permission-item">
                    <input type="checkbox" id="perm5" disabled>
                    <label for="perm5">Configure System</label>
                </div>
                <div class="permission-item">
                    <input type="checkbox" id="perm6" disabled>
                    <label for="perm6">Manage Users</label>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title"><i class="bi bi-clipboard-check"></i> Recent User Activity</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Activity</th>
                        <th>Module</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dr. Maria Santos</td>
                        <td>Trained AI Model</td>
                        <td>Model Training</td>
                        <td><span class="badge success">Success</span></td>
                        <td>Today 2:30 PM</td>
                    </tr>
                    <tr>
                        <td>Juan Dela Cruz</td>
                        <td>Uploaded Dataset</td>
                        <td>Dataset Management</td>
                        <td><span class="badge success">Success</span></td>
                        <td>Today 1:15 PM</td>
                    </tr>
                    <tr>
                        <td>Rina Gonzales</td>
                        <td>Viewed Report</td>
                        <td>Analytics</td>
                        <td><span class="badge success">Success</span></td>
                        <td>Today 12:45 PM</td>
                    </tr>
                    <tr>
                        <td>Carlos Mendoza</td>
                        <td>Updated User Profile</td>
                        <td>Account Management</td>
                        <td><span class="badge success">Success</span></td>
                        <td>Yesterday 4:20 PM</td>
                    </tr>
                    <tr>
                        <td>Patricia Lim</td>
                        <td>Account Activation</td>
                        <td>User Management</td>
                        <td><span class="badge secondary">Pending</span></td>
                        <td>2 hours ago</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="userModalOverlay" class="modal-overlay" aria-hidden="true">
        <div class="modal-window" role="dialog" aria-modal="true" aria-labelledby="userModalTitle">
            <div id="userModalContent" class="modal-content">
                <div class="modal-loading">Loading...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
    .page-title {
        font-size: 28px;
        font-weight: 800;
        color: #1a2e1a;
        margin-bottom: 24px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .grid.cols-4 {
        grid-template-columns: repeat(4, 1fr);
    }

    .grid.cols-2 {
        grid-template-columns: repeat(2, 1fr);
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        transition: all 0.15s;
    }

    .stat-card:hover {
        border-color: #1e9e62;
        box-shadow: 0 4px 16px rgba(30, 158, 98, 0.1);
    }

    .stat-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ab0a0;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 800;
        color: #1a2e1a;
        margin-bottom: 8px;
    }

    .stat-value.green {
        color: #1e9e62;
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    .stat-change.positive {
        color: #1e9e62;
    }

    .section {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 28px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title-with-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        font-size: 16px;
        font-weight: 700;
        color: #1a2e1a;
    }

    .section-actions {
        display: flex;
        gap: 12px;
    }

    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .search-input,
    .filter-select {
        padding: 10px 14px;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        background: #fff;
        color: #3a5a3a;
        transition: all 0.15s;
    }

    .search-input {
        flex: 1;
        min-width: 200px;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .filter-select {
        min-width: 140px;
        cursor: pointer;
    }

    .table-container {
        overflow-x: auto;
        margin-bottom: 20px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .table th {
        background: #f5f7f6;
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        color: #9ab0a0;
        border-bottom: 1px solid #e0e8e0;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f0;
        color: #3a5a3a;
    }

    .table tr:hover {
        background: #f5f7f6;
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
    }

    .badge.success {
        background: #edf7f2;
        color: #1e9e62;
        border: 1px solid #b0e0c0;
    }

    .badge.warning {
        background: #fdf5e8;
        color: #c07818;
        border: 1px solid #e8cc98;
    }

    .badge.danger {
        background: #fdf0ee;
        color: #d04030;
        border: 1px solid #e8b8b0;
    }

    .badge.secondary {
        background: #f0f4f0;
        color: #6a8a6a;
        border: 1px solid #d4e0d4;
    }

    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px;
        aspect-ratio: 1 / 1;
        flex-shrink: 0;
        border-radius: 50%;
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
    }

    .user-name {
        font-weight: 600;
        color: #1a2e1a;
        margin-bottom: 2px;
    }

    .user-info {
        font-size: 11px;
        color: #9ab0a0;
    }

    .role-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid;
    }

    .role-badge.admin {
        background: #fdf0ee;
        color: #d04030;
        border-color: #e8b8b0;
    }

    .role-badge.manager {
        background: #fdf5e8;
        color: #c07818;
        border-color: #e8cc98;
    }

    .role-badge.scientist {
        background: #edf7f2;
        color: #1e9e62;
        border-color: #b0e0c0;
    }

    .role-badge.analyst {
        background: #eff6ff;
        color: #1e62d4;
        border-color: #93c5e8;
    }

    .role-badge.viewer {
        background: #f0f4f0;
        color: #6a8a6a;
        border-color: #d4e0d4;
    }

    .actions-cell {
        display: flex;
        gap: 8px;
        justify-content: center;
        align-items: center;
    }

    .actions-cell form {
        display: inline;
        margin: 0;
        padding: 0;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid #e0e8e0;
        background: #fff;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
        text-decoration: none;
        color: #3a5a3a;
    }

    .action-btn:hover {
        border-color: #1e9e62;
        background: #edf7f2;
    }

    .action-btn.delete:hover {
        border-color: #d04030;
        background: #fdf0ee;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 16px;
        margin-top: 20px;
    }

    .pagination-btn {
        padding: 8px 16px;
        border: 1px solid #e0e8e0;
        background: #fff;
        border-radius: 8px;
        cursor: pointer;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
        transition: all 0.15s;
    }

    .pagination-btn:hover:not(.disabled) {
        border-color: #1e9e62;
        background: #edf7f2;
        color: #1e9e62;
    }

    .pagination-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-info {
        font-size: 12px;
        color: #6a8a6a;
        font-weight: 500;
    }

    .role-stats {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .role-stat {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .role-stat-label {
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
    }

    .role-stat-bar {
        height: 8px;
        background: #e0e8e0;
        border-radius: 4px;
        overflow: hidden;
    }

    .role-stat-fill {
        height: 100%;
        width: var(--w, 0%);
        transition: width 0.3s ease;
    }

    .role-stat-fill.admin {
        background: #d04030;
    }

    .role-stat-fill.manager {
        background: #c07818;
    }

    .role-stat-fill.scientist {
        background: #1e9e62;
    }

    .role-stat-fill.analyst {
        background: #1e62d4;
    }

    .role-stat-fill.viewer {
        background: #6a8a6a;
    }

    .role-stat-count {
        font-size: 11px;
        color: #9ab0a0;
    }

    .permissions-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .permission-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #f5f7f6;
        border-radius: 8px;
        border: 1px solid #e0e8e0;
    }

    .permission-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #1e9e62;
    }

    .permission-item label {
        flex: 1;
        font-size: 12px;
        font-weight: 500;
        color: #3a5a3a;
        cursor: pointer;
    }

    .btn-primary {
        padding: 10px 20px;
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(30, 158, 98, 0.3);
    }

    .btn-secondary {
        padding: 10px 20px;
        background: #fff;
        color: #3a5a3a;
        border: 1.5px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-secondary:hover {
        border-color: #1e9e62;
        background: #edf7f2;
        color: #1e9e62;
    }

    .modal-overlay {
        position: fixed;
        inset: 0;
        display: none;
        justify-content: center;
        align-items: center;
        background: rgba(26, 46, 26, 0.5);
        padding: 20px;
        z-index: 9999;
        backdrop-filter: blur(2px);
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-window {
        width: min(100%, 820px);
        max-height: min(100%, 90vh);
        overflow: hidden;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        animation: modalFadeIn 0.2s ease;
        border: 1px solid #e0e8e0;
    }

    .modal-content {
        display: flex;
        flex-direction: column;
        min-height: 220px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 24px 24px 20px;
        gap: 16px;
        border-bottom: 1px solid #e0e8e0;
    }

    .modal-header h3 {
        font-size: 18px;
        margin: 0;
        color: #1a2e1a;
        font-weight: 700;
    }

    .modal-subtitle {
        margin: 4px 0 0;
        color: #9ab0a0;
        font-size: 12px;
    }

    .modal-close {
        border: none;
        background: transparent;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        color: #6a8a6a;
        padding: 0;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .modal-close:hover {
        color: #1a2e1a;
        background: #f5f7f6;
        border-radius: 6px;
    }

    .modal-body {
        padding: 24px;
        overflow-y: auto;
        max-height: calc(90vh - 180px);
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid #e0e8e0;
    }

    .modal-loading {
        padding: 40px 24px;
        text-align: center;
        color: #3a5a3a;
        font-weight: 600;
        font-size: 14px;
    }

    .modal-errors {
        margin-bottom: 16px;
    }

    .modal-body .table {
        margin: 0;
    }

    .modal-body .table th {
        background: #f5f7f6;
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        color: #9ab0a0;
        border-bottom: 1px solid #e0e8e0;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    .modal-body .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f0;
        color: #3a5a3a;
    }

    .modal-body .form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        margin-bottom: 0;
    }

    .modal-body .form-section {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .modal-body .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .modal-body .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .modal-body .form-group .required {
        color: #d04030;
        font-weight: 700;
    }

    .modal-body .form-group input,
    .modal-body .form-group select {
        padding: 10px 12px;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: #3a5a3a;
        background: #fff;
        transition: all 0.15s;
    }

    .modal-body .form-group input::placeholder,
    .modal-body .form-group select::placeholder {
        color: #9ab0a0;
    }

    .modal-body .form-error {
        margin-top: 6px;
        font-size: 11px;
        color: #d04030;
        line-height: 1.4;
    }

    .modal-body .error-message {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .modal-body .error-message::before {
        content: "!";
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #d04030;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
    }

    .modal-body .form-group input:focus,
    .modal-body .form-group select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .modal-body .password-wrapper {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-body .password-wrapper input {
        flex: 1;
    }

    .modal-body .toggle-pw {
        width: 42px;
        height: 42px;
        border: 1px solid #e0e8e0;
        border-radius: 10px;
        background: #fff;
        color: #3a5a3a;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
    }

    .modal-body .toggle-pw:hover {
        border-color: #1e9e62;
        color: #1e9e62;
        background: #f3fbf6;
    }

    .modal-body .form-section.password-section {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        margin-top: 20px;
    }

    .modal-body .form-section.password-section .password-hint {
        grid-column: 1 / -1;
        background: #f5f7f6;
        border: 1px solid #e0e8e0;
        border-radius: 10px;
        padding: 12px 14px;
        color: #3a5a3a;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 0;
    }

    .modal-body .form-section.password-section .form-group {
        min-width: 0;
    }

    .modal-body .alert {
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 13px;
    }

    .modal-body .alert-success {
        background: #ecf9f0;
        color: #145a33;
        border: 1px solid #b8e0c4;
    }

    .modal-body .alert-danger {
        background: #fdf0ee;
        color: #d04030;
        border: 1px solid #e8b8b0;
    }

    .modal-body .alert-danger ul {
        margin: 0;
        padding-left: 20px;
    }

    .modal-body .alert-danger li {
        margin: 4px 0;
    }

    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 1024px) {
        .modal-window {
            width: min(100%, 90vw);
        }

        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid.cols-2 {
            grid-template-columns: 1fr;
        }

        .section-title-with-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .section-actions {
            width: 100%;
        }

        .section-actions .btn-primary,
        .section-actions .btn-secondary {
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        .page-title {
            font-size: 22px;
            margin-bottom: 16px;
        }

        .modal-overlay {
            padding: 12px;
        }

        .modal-window {
            width: 100%;
            max-height: 85vh;
            border-radius: 12px;
        }

        .modal-header {
            padding: 16px 16px 12px;
        }

        .modal-header h3 {
            font-size: 16px;
        }

        .modal-body {
            padding: 16px;
            max-height: calc(85vh - 140px);
        }

        .modal-actions {
            padding: 12px 16px;
            gap: 8px;
        }

        .modal-actions button {
            flex: 1;
        }

        .modal-body .form-row {
            grid-template-columns: 1fr;
        }

        .modal-body .form-section.password-section {
            grid-template-columns: 1fr;
        }

        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid {
            gap: 12px;
        }

        .section {
            padding: 16px;
            margin-bottom: 16px;
        }

        .filter-bar {
            flex-direction: column;
        }

        .search-input,
        .filter-select {
            width: 100%;
        }

        .table {
            font-size: 11px;
        }

        .table th,
        .table td {
            padding: 10px 12px;
        }

        .user-cell {
            flex-direction: column;
            gap: 6px;
            align-items: flex-start;
        }

        .actions-cell {
            gap: 4px;
        }

        .action-btn {
            width: 28px;
            height: 28px;
            font-size: 12px;
        }

        .pagination {
            flex-direction: column;
            gap: 12px;
        }

        .pagination-btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    function updateSearchFilter() {
        const searchTerm = document.querySelector('.search-input').value.toLowerCase();
        const tableRows = document.querySelectorAll('.table tbody tr');

        tableRows.forEach(row => {
            const userName = row.cells[0].textContent.toLowerCase();
            const userEmail = row.cells[1].textContent.toLowerCase();
            row.style.display = (userName.includes(searchTerm) || userEmail.includes(searchTerm)) ? '' : 'none';
        });
    }

    document.querySelector('.search-input').addEventListener('keyup', updateSearchFilter);

    document.querySelectorAll('.filter-select')[0].addEventListener('change', function(e) {
        console.log('Role filter:', e.target.value);
    });

    document.querySelectorAll('.filter-select')[1].addEventListener('change', function(e) {
        console.log('Status filter:', e.target.value);
    });

    const userModalOverlay = document.getElementById('userModalOverlay');
    const userModalContent = document.getElementById('userModalContent');

    function openModalLoading() {
        userModalContent.innerHTML = '<div class="modal-loading">Loading...</div>';
        userModalOverlay.classList.add('active');
        userModalOverlay.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        userModalOverlay.classList.remove('active');
        userModalOverlay.setAttribute('aria-hidden', 'true');
        userModalContent.innerHTML = '<div class="modal-loading">Loading...</div>';
    }

    function renderModalErrors(errors) {
        const errorContainer = userModalContent.querySelector('#userModalError');
        if (!errorContainer) return;

        // Clear any previous inline field errors.
        userModalContent.querySelectorAll('.form-error').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });

        const messages = [];
        if (Array.isArray(errors)) {
            messages.push(...errors);
        } else if (errors && typeof errors === 'object') {
            Object.entries(errors).forEach(([field, value]) => {
                if (Array.isArray(value)) {
                    const fieldError = userModalContent.querySelector(`#modal-${field}-error`);
                    if (fieldError) {
                        fieldError.textContent = value.join(' ');
                        fieldError.style.display = 'block';
                    }
                    messages.push(...value);
                }
            });
        }

        if (messages.length === 0) {
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
            return;
        }

        errorContainer.innerHTML = '<ul>' + messages.map(message => '<li>' + message + '</li>').join('') + '</ul>';
        errorContainer.style.display = 'block';
    }

    function bindModalActions() {
        userModalContent.querySelectorAll('[data-modal-close]').forEach(button => {
            button.addEventListener('click', closeModal);
        });

        const editButton = userModalContent.querySelector('.modal-edit-button');
        if (editButton) {
            editButton.addEventListener('click', function() {
                loadModalContent(editButton.dataset.url, 'edit');
            });
        }

        bindPhoneInput(userModalContent);
        bindPasswordToggles(userModalContent);

        const form = userModalContent.querySelector('form#userModalForm');
        if (form) {
            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                const submitButton = event.submitter || userModalContent.querySelector('button[type="submit"], input[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }
                renderModalErrors([]);

                try {
                    const formData = new FormData(form);
                    const response = await axios.post(form.action, formData);

                    const successMessage = document.createElement('div');
                    successMessage.className = 'alert alert-success';
                    successMessage.textContent = response.data.success || 'User updated successfully.';
                    form.parentElement.insertBefore(successMessage, form);

                    setTimeout(() => {
                        closeModal();
                        window.location.reload();
                    }, 900);
                } catch (error) {
                    if (error.response && error.response.status === 422) {
                        renderModalErrors(error.response.data.errors);
                    } else {
                        renderModalErrors(['Unable to save changes. Please try again.']);
                    }
                } finally {
                    submitButton.disabled = false;
                }
            });
        }
    }

    function bindPasswordToggles(root) {
        root.querySelectorAll('.toggle-pw').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.target;
                const targetInput = root.querySelector(`#${targetId}`);
                if (!targetInput) {
                    return;
                }

                const show = targetInput.type === 'password';
                targetInput.type = show ? 'text' : 'password';
                button.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        });
    }

    function bindPhoneInput(root) {
        const phoneInput = root.querySelector('input[name="phone"]');
        if (!phoneInput) {
            return;
        }

        phoneInput.setAttribute('type', 'tel');
        phoneInput.setAttribute('inputmode', 'numeric');
        phoneInput.setAttribute('pattern', '[0-9]*');
        phoneInput.setAttribute('maxlength', '11');

        const normalizePhone = value => value.replace(/[^0-9]/g, '').slice(0, 11);

        phoneInput.addEventListener('input', function(e) {
            e.target.value = normalizePhone(e.target.value);
        });

        phoneInput.addEventListener('paste', function() {
            setTimeout(() => {
                phoneInput.value = normalizePhone(phoneInput.value);
            }, 10);
        });
    }

    async function loadModalContent(url, action = 'show') {
        openModalLoading();

        try {
            const response = await axios.get(url);
            userModalContent.innerHTML = response.data;
            bindModalActions();
        } catch (error) {
            userModalContent.innerHTML = '<div class="modal-loading">Unable to load content. Please refresh.</div>';
            console.error(error);
        }
    }

    document.querySelectorAll('.action-btn[data-action="show"], .action-btn[data-action="edit"]').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            loadModalContent(button.href, button.dataset.action);
        });
    });

    userModalOverlay.addEventListener('click', function(event) {
        if (event.target === userModalOverlay) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && userModalOverlay.classList.contains('active')) {
            closeModal();
        }
    });
</script>
@endsection