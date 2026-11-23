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
        /* Grows with the window on big monitors, never smaller than before. */
        height: clamp(380px, 45vh, 600px);
        border-radius: 10px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        z-index: 0;
    }

    /* While the next tab / filter / page loads in the background (the list is swapped in place). */
    .layer-list {
        transition: opacity 0.15s ease;
    }

    .layer-list.is-loading {
        opacity: 0.55;
        pointer-events: none;
    }

    /* Expanded (button in the map corner): the preview card fills the window. */
    .preview-section.is-expanded {
        position: fixed;
        inset: 0;
        z-index: 3000;
        margin: 0;
        border-radius: 0;
        display: flex;
        flex-direction: column;
        padding: 14px 16px 16px;
    }

    .preview-section.is-expanded .section-title {
        margin-bottom: 10px;
    }

    .preview-section.is-expanded .preview-map {
        flex: 1 1 auto;
        height: auto;
        min-height: 0;
    }

    html.preview-expanded,
    html.preview-expanded body {
        overflow: hidden;
    }

    .preview-expand {
        font-size: 14px;
        display: flex !important;
        align-items: center;
        justify-content: center;
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

    a.stat-card {
        display: block;
        color: inherit;
        text-decoration: none;
        transition: border-color 0.15s ease;
    }

    a.stat-card:hover {
        border-color: var(--fg-1e9e62, #1e9e62);
    }

    .stat-value.amber {
        color: var(--fg-c07818, #c07818);
    }

    /* An AI draft its owner has left for over STALE_DRAFT_DAYS. */
    .status-stale {
        color: var(--fg-c07818, #c07818) !important;
        font-weight: 600;
    }

    tr.layer-row[data-preview] {
        cursor: pointer;
    }

    tr.layer-row[data-preview]:focus-visible {
        outline: 2px solid var(--fg-1e9e62, #1e9e62);
        outline-offset: -2px;
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

    /* ---- Phones: a compact page built around a sticky preview map ----
       The list scrolls under a short map that stays at the top, so tapping a card shows it straight away. */
    @media (max-width: 768px) {
        .page-sub {
            display: none;
        }

        /* Stat cards: a tight 2x2 grid. */
        .grid.cols-4 {
            gap: 8px;
            margin-bottom: 12px;
        }

        .stat-card {
            padding: 10px 12px;
            border-radius: 12px;
        }

        .stat-label {
            font-size: 9.5px;
            margin-bottom: 2px;
        }

        .stat-value {
            font-size: 22px;
            margin-bottom: 2px;
        }

        .stat-change {
            font-size: 10.5px;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Preview: sticks to the top of the scrolling content box, the layer's name on one line. */
        .preview-section:not(.is-expanded) {
            position: sticky;
            /* Sticky offsets count from inside the scroll box's padding (the layout's .content, 20px on phones),
               so pull it up by that padding to sit flush under the header with nothing showing above it. */
            top: -20px;
            z-index: 20;
            padding: 8px;
            margin: 0 -16px 12px;
            border-radius: 0 0 14px 14px;
            border-left: none;
            border-right: none;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
        }

        .preview-section .section-title {
            font-size: 12px;
            margin-bottom: 6px;
            gap: 6px;
            min-width: 0;
            flex-wrap: nowrap;
        }

        .preview-label {
            flex: 1 1 auto;
            min-width: 0;
            margin-left: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: right;
            font-size: 11.5px;
        }

        .preview-map {
            height: 200px;
            border-radius: 10px;
        }

        .layer-list {
            padding: 12px;
        }

        /* Tabs: a two-way switch. */
        .tabs {
            gap: 4px;
            padding: 4px;
            margin-bottom: 12px;
            border-bottom: none;
            border-radius: 10px;
            background: var(--bg-f0f4f0, #f0f4f0);
        }

        .tabs .tab {
            flex: 1 1 0;
            justify-content: center;
            gap: 6px;
            margin: 0;
            padding: 9px 8px;
            border: none;
            border-radius: 8px;
            font-size: 12.5px;
            white-space: nowrap;
        }

        .tabs .tab.active {
            background: var(--bg-ffffff, #fff);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        /* Filters: search on its own row, then two per row; a lone dropdown or an odd last button takes the row. */
        .layer-toolbar {
            gap: 8px;
            margin-bottom: 12px;
        }

        .layer-toolbar .filter-bar {
            flex-basis: 100%;
        }

        .filter-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .filter-bar .search-input,
        .filter-bar .filter-select {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding: 10px 12px;
            font-size: 13px;
        }

        .filter-bar .search-input,
        .filter-bar .filter-select:only-of-type,
        .filter-bar .btn-primary:last-child {
            grid-column: 1 / -1;
        }

        .filter-bar .btn-primary,
        .filter-bar .btn-secondary {
            justify-content: center;
            padding: 10px 12px;
        }

        .export-actions {
            width: 100%;
            gap: 8px;
        }

        .export-actions .btn-secondary {
            flex: 1 1 0;
            justify-content: center;
            padding: 8px 10px;
            font-size: 11.5px;
        }

        /* Rows: one compact card each. Name on top with the status line under it, place/AI and size/date below, owner and
           the actions along the bottom. The cells are placed by class (see the two list partials). */
        .layer-table-container {
            overflow-x: visible;
        }

        .layer-table thead {
            display: none;
        }

        .layer-table,
        .layer-table tbody,
        .layer-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .layer-table tbody tr.layer-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                "title title"
                "status status"
                "meta meta"
                "area area"
                "owner actions";
            column-gap: 10px;
            row-gap: 6px;
            padding: 12px;
            border: 1px solid var(--bd-e0e8e0, #e0e8e0);
            border-radius: 14px;
            background: var(--bg-ffffff, #fff);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .layer-table tbody tr {
            display: block;
        }

        .layer-table tbody tr + tr {
            margin-top: 10px;
        }

        .layer-table tbody tr:hover {
            background: var(--bg-ffffff, #fff);
        }

        /* The card on the map: a green outline instead of a tinted background. */
        .layer-table tbody tr.layer-row.previewing {
            background: var(--bg-ffffff, #fff) !important;
            border-color: #1e9e62;
            box-shadow: 0 0 0 2px rgba(30, 158, 98, 0.18);
        }

        .layer-table td {
            padding: 0;
            border-bottom: none;
            min-width: 0;
        }

        .layer-table td.cell-title {
            grid-area: title;
            overflow-wrap: anywhere;
        }

        .layer-table td.cell-title .user-name {
            font-size: 14px;
            line-height: 1.3;
        }

        .layer-table td.cell-status {
            grid-area: status;
            text-align: left;
            font-size: 11px;
        }

        .layer-table td.cell-status .badge {
            white-space: normal;
        }

        .layer-table td.cell-meta {
            grid-area: meta;
        }

        .layer-table td.cell-area {
            grid-area: area;
        }

        .layer-table td.cell-owner {
            grid-area: owner;
            align-self: center;
        }

        .layer-table td.cell-actions {
            grid-area: actions;
            align-self: center;
            justify-self: end;
            width: auto;
        }

        /* A thin rule above the details and one under them. Both sit on full-width cells: on the owner and
           actions cells the rule would break at the column gap and at the taller Delete button. */
        .layer-table td.cell-meta {
            padding-top: 8px;
            border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
        }

        .layer-table td.cell-area {
            padding-bottom: 8px;
            border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        }

        /* Detail cells: a small label, then the value on the same line. */
        .layer-table td[data-label] {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-size: 12px;
            text-align: left;
        }

        .layer-table td[data-label]::before {
            content: attr(data-label);
            flex: none;
            width: 58px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #9ab0a0;
        }

        .layer-table td.cell-status {
            display: block;
        }

        .layer-table td.cell-status::before {
            display: none;
        }

        .layer-table td.cell-status .cell-value {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 3px 8px;
        }

        .layer-table .cell-value {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        /* Owner: "Alice · Resident" on one line. */
        .layer-table td.cell-owner .cell-value {
            display: flex;
            flex-wrap: wrap;
            gap: 0 4px;
            font-weight: 600;
        }

        .layer-table td.cell-owner .cell-value > div + div::before {
            content: "· ";
        }

        .layer-table td.cell-owner .muted {
            font-weight: 500;
        }

        .layer-table .clamp {
            max-width: none;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .layer-table .status-note {
            max-width: none;
        }

        .layer-table .row-actions {
            gap: 6px;
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
    }

    .page-sub {
        margin: -8px 0 22px;
        max-width: 760px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--fg-6a8a6a, #6a8a6a);
    }


    /* Search + status on the left, exports on the right. */
    .layer-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .layer-toolbar .filter-bar {
        flex: 1 1 420px;
        margin-bottom: 0;
    }

    .export-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .export-actions .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        white-space: nowrap;
    }


    /* Links between a delineation and its drone imagery (each opens the other tab on that record). */
    .cross-link {
        color: var(--fg-1e9e62, #1e9e62);
        font-weight: 600;
        text-decoration: none;
    }

    .cross-link:hover {
        text-decoration: underline;
    }

    /* Where a delineation came from: hand-drawn, or a drone survey (links to its imagery). */
    .source-line {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 6px;
        margin-top: 4px;
    }

    .source-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        background: var(--bg-f0f4f0, #f0f4f0);
        color: var(--fg-4a6a4a, #4a6a4a);
        font-size: 11px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }

    .source-tag.drone {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    a.source-tag.drone:hover {
        border-color: var(--fg-1e9e62, #1e9e62);
    }

    .linked-record {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 6px;
        margin-top: 4px;
    }

</style>
