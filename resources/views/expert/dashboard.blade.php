@extends('layouts.enduser')

@section('title', 'Expert Review - MangroveMap')

@section('styles')
<style>
    .expert-header {
        margin-bottom: 24px;
    }

    .expert-header h1 {
        font-size: 24px;
        font-weight: 800;
        color: #1a2e1a;
        margin-bottom: 6px;
    }

    .expert-header p {
        color: #6a8a6a;
        font-size: 14px;
    }

    .review-card {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 16px;
    }

    .review-card h2 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .review-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .review-table th,
    .review-table td {
        padding: 12px 10px;
        text-align: left;
        border-bottom: 1px solid #edf2ed;
        vertical-align: top;
    }

    .review-table th {
        color: #6a8a6a;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 120px;
    }

    .btn-approve {
        background: #1e9e62;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }

    .btn-approve:hover {
        background: #178a54;
    }

    .btn-reject {
        background: #fff;
        color: #d04030;
        border: 1px solid #f0d0d0;
        border-radius: 8px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }

    .btn-reject:hover {
        background: #fef2f2;
        border-color: #d04030;
    }

    .reject-form {
        display: none;
        margin-top: 8px;
        padding: 12px;
        background: #fef8f8;
        border: 1px solid #f0d0d0;
        border-radius: 8px;
    }

    .reject-form.open {
        display: block;
    }

    .reject-form label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #6a8a6a;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .reject-form textarea {
        width: 100%;
        min-height: 72px;
        padding: 8px 10px;
        border: 1px solid #e0e8e0;
        border-radius: 6px;
        font-size: 12px;
        font-family: inherit;
        resize: vertical;
        margin-bottom: 8px;
    }

    .reject-form-actions {
        display: flex;
        gap: 8px;
    }

    .btn-submit-reject {
        background: #d04030;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        font-family: inherit;
    }

    .btn-cancel-reject {
        background: #fff;
        color: #6a8a6a;
        border: 1px solid #e0e8e0;
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 12px;
        cursor: pointer;
        font-family: inherit;
    }

    .empty-state {
        color: #7a9a7a;
        font-size: 14px;
        padding: 8px 0;
    }

    .badge-approved {
        display: inline-block;
        background: #edf7f2;
        color: #1e9e62;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .highlighted-delineation-row {
        animation: highlightGlow 4s ease-in-out;
        background: #ecfdf5;
    }

    @keyframes highlightGlow {
        0% {
            background: #f0fdf4;
        }

        50% {
            background: #d1fae5;
        }

        100% {
            background: #f0fdf4;
        }
    }

    .badge-rejected {
        display: inline-block;
        background: #fef2f2;
        color: #d04030;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }

    .rejection-notes {
        font-size: 12px;
        color: #6a8a6a;
        margin-top: 4px;
        max-width: 280px;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 16px;
        font-size: 13px;
    }

    .alert-success {
        background: #edf7f2;
        color: #1e9e62;
        border: 1px solid #c8e6d8;
    }

    .alert-info {
        background: #f0f4ff;
        color: #3a5a8a;
        border: 1px solid #d4e0f4;
    }

    .alert-error {
        background: #fef2f2;
        color: #d04030;
        border: 1px solid #f0d0d0;
    }
</style>
@endsection

@section('content')
<div class="expert-header">
    <h1>Delineation Review</h1>
    <p>Review and approve resident-submitted delineations before they appear on the public map.</p>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('info'))
<div class="alert alert-info">{{ session('info') }}</div>
@endif

@if(session('error'))
<div class="alert alert-error">{{ session('error') }}</div>
@endif

@if($errors->any())
<div class="alert alert-error">
    <ul style="margin:0;padding-left:18px;">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="review-card">
    <h2><i class="bi bi-hourglass-split"></i> Pending approval ({{ $pendingDelineations->count() }})</h2>

    @if($pendingDelineations->isEmpty())
    <p class="empty-state">No delineations waiting for review.</p>
    @else
    <table class="review-table">
        <thead>
            <tr>
                <th>Resident</th>
                <th>Delineation</th>
                <th>Submitted</th>
                <th>Features</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pendingDelineations as $delineation)
            <tr id="pending-delineation-{{ $delineation->id }}">
                <td>
                    <strong>{{ $delineation->user?->name ?? 'Unknown' }}</strong><br>
                    <span style="color:#7a9a7a;">{{ $delineation->user?->email }}</span>
                    @if($delineation->user?->organization)
                    <br><span style="color:#a8bfa8;font-size:11px;">{{ $delineation->user->organization }}</span>
                    @endif
                </td>
                <td>{{ $delineation->name }}</td>
                <td>{{ $delineation->created_at?->format('M j, Y g:i A') }}</td>
                <td>{{ is_array($delineation->features) ? count($delineation->features) : 0 }}</td>
                <td>
                    <div class="action-buttons">
                        <form method="POST" action="{{ route('expert.delineations.approve', $delineation) }}">
                            @csrf
                            <button type="submit" class="btn-approve">Approve</button>
                        </form>
                        <button type="button" class="btn-reject" data-reject-toggle="{{ $delineation->id }}">
                            Reject
                        </button>
                        <form method="POST" action="{{ route('expert.delineations.reject', $delineation) }}" class="reject-form" id="reject-form-{{ $delineation->id }}">
                            @csrf
                            <label for="rejection_notes_{{ $delineation->id }}">Rejection notes (required)</label>
                            <textarea id="rejection_notes_{{ $delineation->id }}" name="rejection_notes" required minlength="10" maxlength="2000" placeholder="Explain why this delineation was rejected...">{{ old('rejection_notes') }}</textarea>
                            <div class="reject-form-actions">
                                <button type="submit" class="btn-submit-reject">Submit rejection</button>
                                <button type="button" class="btn-cancel-reject" data-reject-cancel="{{ $delineation->id }}">Cancel</button>
                            </div>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

<div class="review-card">
    <h2><i class="bi bi-check-circle"></i> Recently approved</h2>

    @if($recentlyApproved->isEmpty())
    <p class="empty-state">No approved delineations yet.</p>
    @else
    <table class="review-table">
        <thead>
            <tr>
                <th>Resident</th>
                <th>Delineation</th>
                <th>Approved by</th>
                <th>Approved at</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentlyApproved as $delineation)
            <tr>
                <td>{{ $delineation->user?->name ?? 'Unknown' }}</td>
                <td>{{ $delineation->name }}</td>
                <td>{{ $delineation->approvedBy?->name ?? '—' }}</td>
                <td>{{ $delineation->approved_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td><span class="badge-approved">Approved</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>

<div class="review-card">
    <h2><i class="bi bi-x-circle"></i> Recently rejected</h2>

    @if($recentlyRejected->isEmpty())
    <p class="empty-state">No rejected delineations yet.</p>
    @else
    <table class="review-table">
        <thead>
            <tr>
                <th>Resident</th>
                <th>Delineation</th>
                <th>Rejected by</th>
                <th>Rejected at</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($recentlyRejected as $delineation)
            <tr>
                <td>{{ $delineation->user?->name ?? 'Unknown' }}</td>
                <td>{{ $delineation->name }}</td>
                <td>{{ $delineation->rejectedBy?->name ?? '—' }}</td>
                <td>{{ $delineation->rejected_at?->format('M j, Y g:i A') ?? '—' }}</td>
                <td>
                    <span class="badge-rejected">Rejected</span>
                    @if($delineation->rejection_notes)
                    <p class="rejection-notes">{{ $delineation->rejection_notes }}</p>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('[data-reject-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-reject-toggle');
            const form = document.getElementById('reject-form-' + id);
            document.querySelectorAll('.reject-form.open').forEach(f => {
                if (f !== form) f.classList.remove('open');
            });
            form?.classList.toggle('open');
        });
    });

    document.querySelectorAll('[data-reject-cancel]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-reject-cancel');
            document.getElementById('reject-form-' + id)?.classList.remove('open');
        });
    });

    const params = new URLSearchParams(window.location.search);
    const highlightId = params.get('highlight');
    if (highlightId) {
        const row = document.getElementById('pending-delineation-' + highlightId);
        if (row) {
            row.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            row.classList.add('highlighted-delineation-row');
            setTimeout(() => row.classList.remove('highlighted-delineation-row'), 4500);
        }
    }
</script>
@endsection