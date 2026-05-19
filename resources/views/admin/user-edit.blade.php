@extends('layouts.admin')

@section('title', 'Edit User - ' . $user->name)

@section('content')
<div id="user-edit-section">
    <div class="page-title">Edit User</div>

    <div class="section">
        <div class="section-title-with-actions">
            <div><i class="bi bi-pencil-square"></i> Update User Information</div>
            <div class="section-actions">
                <a href="{{ route('admin.users') }}" class="btn-secondary"><i class="bi bi-arrow-left"></i> Back to Users</a>
            </div>
        </div>

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('admin.users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrator</option>
                        <option value="end_user" {{ old('role', $user->role) === 'end_user' ? 'selected' : '' }}>Resident</option>
                        <option value="expert" {{ old('role', $user->role) === 'expert' ? 'selected' : '' }}>Expert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="organization">Organization</label>
                    <input id="organization" name="organization" type="text" value="{{ old('organization', $user->organization) }}">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone) }}">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('admin.users') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
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
        border: none;
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

    .alert-danger ul {
        margin: 0;
        padding-left: 20px;
    }

    .alert-danger li {
        margin: 4px 0;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #3a5a3a;
        margin-bottom: 6px;
    }

    .form-group input,
    .form-group select {
        padding: 10px 12px;
        border: 1px solid #e0e8e0;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        color: #3a5a3a;
        background: #fff;
        transition: all 0.15s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .form-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .form-actions button,
    .form-actions a {
        flex: 0 1 auto;
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

        .form-row {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions button,
        .form-actions a {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endsection