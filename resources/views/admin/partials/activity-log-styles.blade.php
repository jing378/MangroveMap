<style>
    .page-title {
        font-size: 28px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 24px;
    }

    .grid {
        display: grid;
        gap: 20px;
        margin-bottom: 28px;
    }

    .grid.cols-4 {
        grid-template-columns: repeat(4, 1fr);
    }

    .stat-card {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
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
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 8px;
    }

    .stat-value.green {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .stat-value.red {
        color: var(--fg-d04030, #d04030);
    }

    .stat-change {
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    .section {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 28px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
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
        background: var(--bg-f5f7f6, #f5f7f6);
        padding: 12px 16px;
        text-align: left;
        font-weight: 700;
        color: #9ab0a0;
        border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 12px 16px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        color: var(--fg-3a5a3a, #3a5a3a);
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .muted {
        font-size: 11px;
        color: #9ab0a0;
    }

    .user-name {
        font-weight: 600;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
        background: var(--bg-f0f4f0, #f0f4f0);
        color: var(--fg-6a8a6a, #6a8a6a);
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
    }

    .badge.success {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        border-color: var(--bd-b0e0c0, #b0e0c0);
    }

    .badge.pending {
        background: var(--bg-fdf5e8, #fdf5e8);
        color: var(--fg-c07818, #c07818);
        border-color: var(--bd-e8cc98, #e8cc98);
    }

    .badge.rejected {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border-color: var(--bd-e8b8b0, #e8b8b0);
    }

    .action-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        background: var(--bg-ffffff, #fff);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--fg-3a5a3a, #3a5a3a);
        text-decoration: none;
    }

    .action-btn:hover {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
    }

    .btn-primary,
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 18px;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
        color: #fff;
        border: none;
    }

    .btn-secondary {
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
        border: 1.5px solid var(--bd-e0e8e0, #e0e8e0);
    }

    .btn-secondary:hover {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    @media (max-width: 1024px) {
        .grid.cols-4 {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .page-title {
            font-size: 22px;
            margin-bottom: 16px;
        }

        .grid {
            gap: 12px;
        }

        .section {
            padding: 16px;
        }

        .table th,
        .table td {
            padding: 10px 12px;
        }
    }
</style>
