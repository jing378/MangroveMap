@extends('layouts.admin')

@section('title', 'Log Details - MangroveMap')

@section('content')
<div id="activity-log-detail">
    @php
    // Return to the filtered/paged list the admin came from, but never to another detail page.
    $logsIndexUrl = route('admin.activity-logs.index');
    $previousUrl = url()->previous();
    $backUrl = preg_match('#^' . preg_quote($logsIndexUrl, '#') . '(\?|$)#', $previousUrl) ? $previousUrl : $logsIndexUrl;
    @endphp
    <a href="{{ $backUrl }}" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Activity Logs
    </a>
    <div class="page-title">Log Details</div>

    <div class="detail-grid">
        <div class="section">
            <div class="section-title"><i class="bi bi-journal-text"></i> Activity #{{ $activity->id }}</div>
            <dl class="detail-list">
                <dt>Activity</dt>
                <dd class="detail-strong">{{ $activity->activity }}</dd>

                <dt>Status</dt>
                <dd><span class="badge {{ $activity->status }}">{{ $activity->statusLabel() }}</span></dd>

                <dt>Module</dt>
                <dd>
                    <a href="{{ route('admin.activity-logs.index', ['module' => $activity->module]) }}">{{ $activity->module }}</a>
                </dd>

                <dt>Date &amp; time</dt>
                <dd>
                    {{ $activity->created_at?->format('l, F j, Y g:i:s A') ?? '—' }}
                    @if($activity->created_at)
                    <span class="muted">({{ $activity->created_at->diffForHumans() }})</span>
                    @endif
                </dd>

                <dt>IP address</dt>
                <dd>{{ $activity->ip_address ?? 'Not recorded' }}</dd>

                <dt>Browser / device</dt>
                <dd class="detail-wrap">{{ $activity->user_agent ?? 'Not recorded' }}</dd>
            </dl>
        </div>

        <div class="section">
            <div class="section-title"><i class="bi bi-person"></i> Performed By</div>
            @if($activity->user)
            <dl class="detail-list">
                <dt>Name</dt>
                <dd class="detail-strong">{{ $activity->user->name }}</dd>
                <dt>Email</dt>
                <dd>{{ $activity->user->email }}</dd>
                <dt>Role</dt>
                <dd>{{ $activity->user->isResident() ? 'Resident' : ucfirst($activity->user->role) }}</dd>
                <dt>Last login</dt>
                <dd>{{ $activity->user->last_login_at?->diffForHumans() ?? 'Never' }}</dd>
            </dl>
            <div class="detail-actions">
                <a href="{{ route('admin.users.show', $activity->user) }}" class="btn-secondary"><i class="bi bi-person-lines-fill"></i> Open user</a>
                <a href="{{ route('admin.activity-logs.index', ['user' => $activity->user_id]) }}" class="btn-secondary"><i class="bi bi-list-ul"></i> All activity by this user</a>
            </div>
            @else
            <p class="muted">This user account no longer exists.</p>
            @endif
        </div>
    </div>

    @if($subjectDetails)
    <div class="section">
        <div class="section-title"><i class="bi bi-link-45deg"></i> Related {{ $subjectDetails['type'] }}</div>
        @if($subjectDetails['exists'])
        <dl class="detail-list">
            @foreach($subjectDetails['rows'] as $label => $value)
            @continue($value === null || $value === '')
            <dt>{{ $label }}</dt>
            <dd class="detail-wrap">{{ $value }}</dd>
            @endforeach
        </dl>
        @if(!empty($subjectDetails['url']))
        <div class="detail-actions">
            <a href="{{ $subjectDetails['url'] }}" class="btn-secondary">{{ $subjectDetails['urlLabel'] }}</a>
        </div>
        @endif
        @else
        <p class="muted">This {{ strtolower($subjectDetails['type']) }} has since been deleted.</p>
        @endif
    </div>
    @endif

    <div class="section">
        <div class="section-title"><i class="bi bi-clock-history"></i> Other Recent Activity by This User</div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Activity</th>
                        <th>Module</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($relatedActivities as $related)
                    <tr>
                        <td class="nowrap">{{ $related->created_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td><a href="{{ route('admin.activity-logs.show', $related) }}">{{ $related->activity }}</a></td>
                        <td>{{ $related->module }}</td>
                        <td><span class="badge {{ $related->status }}">{{ $related->statusLabel() }}</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="muted">No other activity recorded.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('styles')
@include('admin.partials.activity-log-styles')
<style>
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 10px;
        font-size: 12px;
        font-weight: 700;
        color: #1e9e62;
        text-decoration: none;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 3fr 2fr;
        gap: 20px;
    }

    .detail-grid .section {
        margin-bottom: 20px;
    }

    .detail-list {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 10px 16px;
        margin: 0;
        font-size: 13px;
        color: #3a5a3a;
    }

    .detail-list dt {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ab0a0;
        padding-top: 3px;
    }

    .detail-list dd {
        margin: 0;
    }

    .detail-list a,
    .table a {
        color: #1e9e62;
        font-weight: 600;
        text-decoration: none;
    }

    .detail-strong {
        font-weight: 700;
        color: #1a2e1a;
    }

    .detail-wrap {
        overflow-wrap: anywhere;
        white-space: pre-line;
    }

    .detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }

    .nowrap {
        white-space: nowrap;
    }

    @media (max-width: 1024px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .detail-list {
            grid-template-columns: 1fr;
            gap: 2px;
        }

        .detail-list dd {
            margin-bottom: 8px;
        }
    }
</style>
@endsection
