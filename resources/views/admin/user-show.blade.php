@extends('layouts.admin')

@section('title', 'User Details - ' . $user->name)

@section('content')
<div id="user-details-section">
    <div class="page-title">User Details</div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-person-badge"></i> {{ $user->name }}</div>
            <div class="section-actions">
                <a href="{{ route('admin.users.edit', $user) }}" class="btn-primary"><i class="bi bi-pencil"></i> Edit</a>
                <a href="{{ route('admin.users') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

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

    .section {
        background: #fff;
        border: 1px solid #e0e8e0;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 28px;
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

    .btn-primary,
    .btn-secondary {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(30, 158, 98, 0.3);
    }

    .btn-secondary {
        background: #fff;
        color: #3a5a3a;
        border: 1.5px solid #e0e8e0;
    }

    .btn-secondary:hover {
        border-color: #1e9e62;
        background: #edf7f2;
        color: #1e9e62;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 12px;
    }

    .alert-success {
        background: #edf7f2;
        color: #1e9e62;
        border: 1px solid #b0e0c0;
    }

    .alert-danger {
        background: #fdf0ee;
        color: #d04030;
        border: 1px solid #e8b8b0;
    }

    .table-container {
        overflow-x: auto;
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
        color: #3a5a3a;
        border-bottom: 1px solid #e0e8e0;
    }

    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f0f4f0;
        color: #3a5a3a;
    }

    @media (max-width: 768px) {
        .section-title-with-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }

        .section-actions {
            width: 100%;
        }

        .section-actions a {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endsection