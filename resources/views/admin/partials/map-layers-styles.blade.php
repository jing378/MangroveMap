<style>
    .stat-sub {
        font-size: 18px;
        color: #9ab0a0;
    }

    .page-alert {
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    .page-alert-success {
        background: var(--bg-ecf9f0, #ecf9f0);
        color: var(--fg-145a33, #145a33);
        border: 1px solid var(--bd-b8e0c4, #b8e0c4);
    }

    .page-alert-error {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
        border: 1px solid var(--bd-e8b8b0, #e8b8b0);
    }

    .preview-label {
        margin-left: auto;
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    .preview-map {
        height: 380px;
        border-radius: 10px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        z-index: 0;
    }

    .tabs {
        display: flex;
        gap: 4px;
        border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
        margin-bottom: 18px;
    }

    .tab {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 700;
        color: #7a9a7a;
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
    }

    .tab:hover {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .tab.active {
        color: var(--fg-1e9e62, #1e9e62);
        border-bottom-color: #1e9e62;
    }

    .filter-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-input,
    .filter-select {
        padding: 10px 14px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
    }

    .search-input {
        flex: 1;
        min-width: 220px;
    }

    .filter-select {
        min-width: 150px;
    }

    .search-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .layer-row.previewing {
        background: var(--bg-edf7f2, #edf7f2) !important;
    }

    .clamp {
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .nowrap {
        white-space: nowrap;
    }

    .row-actions {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .row-actions form {
        margin: 0;
    }

    .action-btn {
        cursor: pointer;
        font-size: 14px;
    }

    .action-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .action-btn.delete:hover {
        border-color: #d04030;
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
    }

    .empty-row {
        text-align: center;
        padding: 32px 16px !important;
        color: #7a9a7a;
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
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        background: var(--bg-ffffff, #fff);
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-3a5a3a, #3a5a3a);
        text-decoration: none;
    }

    a.pagination-btn:hover {
        border-color: #1e9e62;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    .pagination-btn.disabled {
        opacity: 0.5;
    }

    .pagination-info {
        font-size: 12px;
        color: var(--fg-6a8a6a, #6a8a6a);
        font-weight: 500;
    }

    .pagination-total {
        margin-top: 14px;
    }

    .btn-danger {
        padding: 10px 18px;
        background: #d04030;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .layer-dialog {
        width: min(480px, calc(100vw - 32px));
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    }

    .layer-dialog::backdrop {
        background: rgba(26, 46, 26, 0.5);
    }

    .layer-dialog form {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 24px;
    }

    .layer-dialog h3 {
        margin: 0 0 4px;
        font-size: 18px;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .layer-dialog label {
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-3a5a3a, #3a5a3a);
        margin-top: 6px;
    }

    .layer-dialog input,
    .layer-dialog textarea {
        padding: 10px 12px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        font-family: 'Manrope', sans-serif;
        font-size: 13px;
        color: var(--fg-1a2e1a, #1a2e1a);
        resize: vertical;
    }

    .layer-dialog input:focus,
    .layer-dialog textarea:focus {
        outline: none;
        border-color: #1e9e62;
        box-shadow: 0 0 0 3px rgba(30, 158, 98, 0.1);
    }

    .dialog-note {
        margin: 0;
        font-size: 12px;
        line-height: 1.5;
        color: var(--fg-6a8a6a, #6a8a6a);
    }

    .dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 14px;
    }

    @media (max-width: 768px) {
        .preview-map {
            height: 260px;
        }

        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .preview-label {
            display: none;
        }
    }
</style>
