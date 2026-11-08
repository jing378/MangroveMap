@php
    $initials = collect(explode(' ', $user->name))->filter()->map(fn ($part) => strtoupper(substr($part, 0, 1)))->take(2)->join('');
    $roleClass = match (true) {
        $user->isAdmin() => 'admin',
        $user->isExpert() => 'scientist',
        default => 'viewer',
    };
    $applicationLabels = [
        \App\Models\User::EXPERT_APPLICATION_PENDING => ['Pending review', 'manager'],
        \App\Models\User::EXPERT_APPLICATION_APPROVED => ['Approved', 'scientist'],
        \App\Models\User::EXPERT_APPLICATION_REJECTED => ['Rejected', 'admin'],
    ];
    $application = $applicationLabels[$user->expert_application_status] ?? null;
@endphp
<div class="modal-header">
    <div class="modal-user-heading">
        <div class="user-avatar">{{ $initials }}</div>
        <div>
            <h3>User Details</h3>
            <p class="modal-subtitle">{{ $user->name }}</p>
        </div>
    </div>
    <button type="button" class="modal-close" data-modal-close aria-label="Close" title="Close">&times;</button>
</div>
<div class="modal-body">
    <section class="detail-group">
        <h4><i class="bi bi-person-vcard"></i> Account</h4>
        <dl class="detail-grid">
            <div>
                <dt>Name</dt>
                <dd>{{ $user->name }}</dd>
            </div>
            <div>
                <dt>Email</dt>
                <dd class="detail-wrap">{{ $user->email }}</dd>
            </div>
            <div>
                <dt>Role</dt>
                <dd><span class="role-badge {{ $roleClass }}">{{ $user->roleLabel() }}</span></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd><span class="badge {{ $user->email_verified_at ? 'success' : 'secondary' }}">{{ $user->email_verified_at ? 'Active' : 'Pending' }}</span></dd>
            </div>
            <div>
                <dt>Organization</dt>
                <dd class="detail-wrap">{{ $user->organization ?: '—' }}</dd>
            </div>
            <div>
                <dt>Contact No.</dt>
                <dd>{{ $user->phone ?: '—' }}</dd>
            </div>
            <div>
                <dt>Joined</dt>
                <dd>{{ $user->created_at?->format('M j, Y') ?? 'Unknown' }}</dd>
            </div>
            <div>
                <dt>Last login</dt>
                <dd>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
            </div>
        </dl>
    </section>

    @if ($application)
    <section class="detail-group">
        <h4><i class="bi bi-person-badge"></i> Expert application</h4>
        <dl class="detail-grid">
            <div>
                <dt>Status</dt>
                <dd><span class="role-badge {{ $application[1] }}">{{ $application[0] }}</span></dd>
            </div>
            <div>
                <dt>Applied</dt>
                <dd>{{ $user->expert_applied_at?->format('M j, Y') ?? '—' }}</dd>
            </div>
            <div class="detail-full">
                <dt>Certificate</dt>
                <dd>
                    @if ($user->expert_certificate_path)
                    <a href="{{ route('admin.expert-applications.certificate', $user) }}" target="_blank" rel="noopener" class="application-certificate">
                        <i class="bi bi-file-earmark-text"></i> {{ $user->expert_certificate_name ?: 'View certificate' }}
                    </a>
                    @else
                    —
                    @endif
                </dd>
            </div>
            @if ($user->expert_qualifications)
            <div class="detail-full">
                <dt>Qualifications &amp; experience</dt>
                <dd class="detail-wrap application-qualifications">{{ $user->expert_qualifications }}</dd>
            </div>
            @endif
            @if ($user->expert_rejection_notes)
            <div class="detail-full">
                <dt>Rejection reason</dt>
                <dd class="detail-wrap">{{ $user->expert_rejection_notes }}</dd>
            </div>
            @endif
        </dl>
    </section>
    @endif

    @if (isset($userActivities) && $userActivities->isNotEmpty())
    <section class="detail-group">
        <h4><i class="bi bi-clock-history"></i> Recent activity</h4>
        <ul class="activity-list">
            @foreach ($userActivities->take(5) as $activity)
            <li>
                <div class="activity-main">
                    <span class="activity-text">{{ $activity->activity }}</span>
                    <span class="badge {{ $activity->status }}">{{ ucfirst($activity->status) }}</span>
                </div>
                <div class="activity-meta">{{ $activity->module }} · {{ $activity->created_at?->diffForHumans() ?? '—' }}</div>
            </li>
            @endforeach
        </ul>
    </section>
    @endif
</div>
<div class="modal-actions">
    <button type="button" class="btn-secondary" data-modal-close>Close</button>
    <button type="button" class="btn-primary modal-edit-button" data-url="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil"></i> Edit</button>
</div>
