<style>
    .stat-sub {
        font-size: 18px;
        color: #9ab0a0;
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

    .action-btn.delete {
        color: var(--fg-d04030, #d04030);
        border-color: var(--bd-e8b8b0, #e8b8b0);
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
        max-height: calc(100dvh - 32px);
        overflow-y: auto;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 0;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        /* Without these the browser's own dialog colors show (white even in dark mode). */
        background: var(--bg-ffffff, #fff);
        color: var(--fg-3a5a3a, #3a5a3a);
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
        background: var(--bg-ffffff, #fff);
        resize: vertical;
    }

    .status-note {
        margin-top: 3px;
        max-width: 260px;
        overflow-wrap: anywhere;
    }

    .status-removable {
        color: var(--fg-b73318, #b73318);
        font-weight: 600;
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

        /* The label names the previewed layer; keep it, on its own line under the title. */
        .preview-section .section-title {
            flex-wrap: wrap;
            row-gap: 4px;
        }

        .preview-label {
            flex-basis: 100%;
            margin-left: 0;
            overflow-wrap: anywhere;
        }

        .tabs {
            overflow-x: auto;
            scrollbar-width: none;
        }

        .tab {
            flex: 1 0 auto;
            justify-content: center;
            white-space: nowrap;
            padding: 10px 12px;
        }

        /* Same as the Users page filter bar: fields stacked full width, same 12px text. */
        .filter-bar {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .filter-bar .search-input,
        .filter-bar .filter-select {
            flex: none;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .filter-bar .btn-primary,
        .filter-bar .btn-secondary {
            justify-content: center;
        }

        /* Tables become one card per layer: name on top, labelled details, actions at the bottom. */
        .layer-table-container {
            overflow-x: visible;
        }

        .layer-table thead {
            display: none;
        }

        .layer-table,
        .layer-table tbody,
        .layer-table tr,
        .layer-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .layer-table tbody tr.layer-row {
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
            padding: 12px 14px;
            background: var(--bg-ffffff, #fff);
        }

        .layer-table tbody tr + tr {
            margin-top: 10px;
        }

        .layer-table tbody tr:hover {
            background: var(--bg-ffffff, #fff);
        }

        .layer-table td {
            padding: 0;
            border-bottom: none;
        }

        .layer-table td.cell-title {
            padding-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .layer-table .clamp {
            max-width: none;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .layer-table td[data-label] {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 0;
            border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
            text-align: right;
        }

        .layer-table td[data-label]::before {
            content: attr(data-label);
            flex: none;
            padding-top: 3px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #9ab0a0;
            text-align: left;
        }

        .layer-table .cell-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .layer-table .status-note {
            max-width: none;
        }

        .layer-table td.cell-actions {
            padding-top: 10px;
            border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
        }

        .layer-table .row-actions {
            justify-content: flex-end;
            gap: 8px;
        }

        .layer-table .action-btn {
            width: 38px;
            height: 38px;
        }

        .layer-table td.empty-row {
            border: 1px dashed var(--bd-e0e8e0, #e0e8e0);
            border-radius: 12px;
        }

        .pagination {
            flex-wrap: wrap;
            gap: 10px;
        }

        .pagination-info {
            order: -1;
            flex-basis: 100%;
            text-align: center;
        }

        /* Dialogs: bottom sheet with full-width buttons. */
        .layer-dialog {
            width: 100%;
            max-width: 100%;
            margin: auto 0 0;
            border-radius: 16px 16px 0 0;
            border-bottom: none;
        }

        .layer-dialog form {
            padding: 20px 16px calc(16px + env(safe-area-inset-bottom, 0px));
        }

        .layer-dialog input,
        .layer-dialog textarea {
            font-size: 16px;
        }

        .dialog-actions {
            flex-direction: column-reverse;
        }

        .dialog-actions button {
            width: 100%;
            justify-content: center;
            min-height: 42px;
        }
    }
</style>
