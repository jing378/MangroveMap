@extends('layouts.enduser')

@section('title', 'Dashboard - MangroveMap')

@section('styles')
<style>
    .dash-container {
        max-width: 1300px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    /* Hero greeting */
    .dash-hero {
        background: linear-gradient(135deg, #1b382b 0%, #165337 100%);
        border-radius: 16px;
        padding: 32px 36px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(22, 83, 55, 0.15);
    }

    .dash-hero::after {
        content: "";
        position: absolute;
        right: -40px;
        bottom: -40px;
        width: 240px;
        height: 240px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(30, 158, 98, 0.25) 0%, transparent 70%);
        pointer-events: none;
    }

    .hero-content h1 {
        font-size: 26px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .hero-content p {
        font-size: 14px;
        color: #b8d9c6;
        max-width: 600px;
        line-height: 1.5;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 12px;
        color: #a3e6be;
    }

    .dash-hero {
        gap: 28px;
    }

    .hero-content {
        flex: 1 1 auto;
        min-width: 0;
        position: relative;
        z-index: 1;
    }

    /* KPI Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
        gap: 16px;
    }

    .kpi-card {
        min-width: 0;
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1e9e62, #1e9e62);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .kpi-info {
        flex: 1;
        min-width: 0;
    }

    .kpi-val {
        font-size: 22px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
        line-height: 1.2;
    }

    .kpi-label {
        font-size: 12px;
        color: #7a9a7a;
        margin-top: 4px;
        font-weight: 500;
    }

    .kpi-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 12px;
        margin-top: 6px;
    }

    .kpi-badge.g { background: var(--bg-edf7f2, #edf7f2); color: var(--fg-1e9e62, #1e9e62); }
    .kpi-badge.a { background: var(--bg-fef7ed, #fef7ed); color: var(--fg-c07818, #c07818); }

    /* Visual Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(420px, 100%), 1fr));
        gap: 20px;
    }

    .chart-card {
        min-width: 0;
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
    }

    .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .chart-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .chart-canvas-wrap {
        position: relative;
        height: 240px;
        width: 100%;
    }

    /* Side by side (types + trend): same height, the chart fills its card. The types card keeps a steady
       height (a type's tip shows in .gen-hint), so the chart only resizes when the municipality changes. */
    .charts-grid > .chart-card {
        display: flex;
        flex-direction: column;
    }

    .charts-grid > .chart-card > .chart-canvas-wrap {
        flex: 1 1 auto;
        min-height: 240px;
    }

    /* Fixed size: six types fit; more scroll inside the list, so neither card ever changes height. */
    .charts-grid > .chart-card:not(.chart-card-wide) {
        height: 456px;
    }

    #genusCard .gen-list {
        flex: 1 1 auto;
        min-height: 0;
        align-content: start;
        overflow-y: auto;
        padding-right: 6px;
        scrollbar-width: thin;
        scrollbar-color: var(--bg-c9dccf, #c9dccf) transparent;
    }

    /* More types below: the list fades out at the bottom until it is scrolled to the end. */
    #genusCard .gen-list.has-more {
        -webkit-mask-image: linear-gradient(to bottom, #000 calc(100% - 36px), transparent);
        mask-image: linear-gradient(to bottom, #000 calc(100% - 36px), transparent);
    }

    .chart-card-wide {
        grid-column: 1 / -1;
    }

    .chart-card-wide .chart-header {
        flex-wrap: wrap;
        gap: 10px;
    }

    .chart-select {
        max-width: 100%;
        padding: 6px 10px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        background: var(--bg-ffffff, #fff);
        color: var(--fg-1a2e1a, #1a2e1a);
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
    }

    .chart-note {
        margin: 10px 0 0;
        font-size: 12px;
        line-height: 1.5;
        color: #7a9a7a;
    }

    .chart-note strong {
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .trend-key {
        display: inline-block;
        width: 14px;
        height: 3px;
        border-radius: 2px;
        vertical-align: middle;
    }

    .trend-key-previous {
        background: #c98f00;
    }

    .trend-key-own {
        background: #1e9e62;
    }

    .trend-range {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--fg-4a6a4a, #4a6a4a);
    }

    .trend-range[hidden] {
        display: none;
    }

    .trend-range label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .trend-range .chart-select {
        padding: 3px 6px;
        font-size: 12px;
    }

    .trend-previous-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-1e7a4f, #1e7a4f);
        cursor: pointer;
        white-space: nowrap;
    }

    .trend-previous-toggle input {
        accent-color: #1e9e62;
        margin: 0;
    }

    .trend-previous-note a {
        color: var(--fg-1e9e62, #1e9e62);
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }

    .trend-previous-note a:hover {
        text-decoration: underline;
    }

    .chart-note a {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .chart-empty {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 13px;
        color: var(--fg-7a9a7a, #7a9a7a);
    }

    /* Submissions Table */
    .table-card {
        background: var(--bg-ffffff, #fff);
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 14px;
        padding: 24px;
    }

    .table-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
    }

    .table-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    /* Bootstrap's CSS is not loaded (only its icons), so its me-* spacing classes do nothing here. */
    .bi.me-1 { margin-right: 4px; }
    .bi.me-2 { margin-right: 6px; }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .custom-table th {
        text-align: left;
        padding: 12px 14px;
        color: #7a9a7a;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    }

    .custom-table td {
        padding: 14px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .custom-table tr:hover td {
        background: var(--bg-fbfdfb, #fbfdfb);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .status-badge.approved {
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
    }

    .status-badge.pending {
        background: var(--bg-fef7ed, #fef7ed);
        color: var(--fg-c07818, #c07818);
    }

    .status-badge.rejected {
        background: var(--bg-fdf0ee, #fdf0ee);
        color: var(--fg-d04030, #d04030);
    }

    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: var(--fg-1e9e62, #1e9e62);
        background: var(--bg-edf7f2, #edf7f2);
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .btn-table-action:hover {
        background: #1e9e62;
        color: #fff;
    }

    /* Top Contributors */
    .contributor-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .contributor-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        font-size: 13px;
        border-radius: 8px;
    }

    .contributor-row:last-child {
        border-bottom: none;
    }

    .contributor-row.is-me {
        background: var(--bg-edf7f2, #edf7f2);
    }

    .contributor-rank {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1e9e62, #1e9e62);
        font-weight: 800;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .contributor-row:first-child .contributor-rank {
        background: #1e9e62;
        color: #fff;
    }

    .contributor-name {
        flex: 1;
        font-weight: 700;
        color: var(--fg-1a2e1a, #1a2e1a);
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .contributor-you {
        margin-left: 6px;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        background: #1e9e62;
        color: #fff;
    }

    .contributor-role {
        font-size: 11px;
        color: #7a9a7a;
        font-weight: 600;
    }

    .contributor-count {
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-1e9e62, #1e9e62);
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .dash-container {
            gap: 18px;
        }

        .dash-hero {
            padding: 22px 20px;
        }

        .hero-content h1 {
            font-size: 21px;
            overflow-wrap: anywhere;
        }

        .hero-content p {
            font-size: 13px;
        }

        .contributor-role {
            display: none;
        }

        /* KPI cards: compact 2-column tiles, icon above text */
        .kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .kpi-card {
            flex-direction: column;
            gap: 10px;
            padding: 14px;
        }

        .kpi-card:last-child:nth-child(odd) {
            grid-column: 1 / -1;
        }

        .kpi-icon {
            width: 36px;
            height: 36px;
            font-size: 17px;
        }

        .kpi-val {
            font-size: 19px;
            overflow-wrap: anywhere;
        }

        .kpi-badge {
            white-space: normal;
            line-height: 1.35;
        }

        .charts-grid {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .chart-card,
        .table-card {
            padding: 16px;
        }

        .chart-header,
        .table-header {
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .chart-canvas-wrap {
            height: 220px;
        }

        .contributor-row {
            gap: 10px;
            padding: 10px 8px;
        }

        /* Recent delineations: "View on Map" becomes "View" to save table width. */
        .action-label-extra {
            display: none;
        }

        .btn-table-action {
            white-space: nowrap;
        }
    }

    @media (max-width: 360px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    /* "Total Monitored Area" card: opens the Mangrove Report below the cards */
    .kpi-card-toggle {
        width: 100%;
        color: inherit;
        font-family: inherit;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .kpi-card-toggle .kpi-info,
    .kpi-card-toggle .kpi-val,
    .kpi-card-toggle .kpi-label {
        display: block;
    }

    .kpi-card-toggle:hover {
        border-color: var(--bd-b8d9c6, #b8d9c6);
        box-shadow: 0 4px 14px rgba(30, 158, 98, 0.10);
    }

    .kpi-card-toggle[aria-expanded="true"] {
        border-color: #1e9e62;
        background: var(--bg-f5f7f6, #f5f7f6);
        box-shadow: 0 0 0 2px rgba(30, 158, 98, 0.15);
    }

    .kpi-card-toggle[aria-expanded="true"] .kpi-icon {
        background: var(--bg-edf7f2, #edf7f2);
    }

    .kpi-report-hint {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 8px;
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .kpi-report-hint i {
        font-size: 11px;
        transition: transform 0.15s ease;
    }

    .kpi-card-toggle[aria-expanded="true"] .kpi-report-hint i {
        transform: rotate(180deg);
    }

    .rep-panel {
        border-color: #1e9e62;
        animation: rep-panel-in 0.2s ease-out;
    }

    .rep-panel[hidden] {
        display: none;
    }

    @keyframes rep-panel-in {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .rep-panel {
            animation: none;
        }
    }

    .rep-close {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border: 1px solid var(--bd-e0e8e0, #e0e8e0);
        border-radius: 8px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-4a6a4a, #4a6a4a);
        cursor: pointer;
    }

    .rep-close:hover {
        background: var(--bg-f5f7f6, #f5f7f6);
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    /* Mangrove report: a bar per municipality, each area under it */
    button.btn-table-action {
        border: 0;
        font-family: inherit;
        cursor: pointer;
    }

    .rep-head-tools {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* Mangrove report: a histogram (one column per municipality) over a table of the figures */
    .rep-histogram {
        position: relative;
        height: 240px;
        margin-bottom: 6px;
    }

    .rep-hint {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 12px;
        font-size: 12px;
        color: #7a9a7a;
    }

    .rep-table {
        table-layout: fixed;
    }

    .rep-col-name { width: auto; }
    .rep-col-ha { width: 92px; }
    .rep-col-share { width: 72px; }
    .rep-col-count { width: 64px; }

    .rep-table th,
    .rep-table td {
        padding: 9px 12px;
        vertical-align: middle;
    }

    .rep-table thead th {
        padding-top: 0;
    }

    .rep-table tbody th {
        text-align: left;
        font-weight: inherit;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
    }

    .rep-num {
        text-align: right !important;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .rep-ha {
        font-weight: 800;
    }

    .rep-row {
        cursor: pointer;
    }

    .rep-group.is-open .rep-row td,
    .rep-group.is-open .rep-row th {
        background: var(--bg-f5f7f6, #f5f7f6);
    }

    .rep-place-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        min-width: 0;
        padding: 0;
        border: 0;
        background: none;
        color: var(--fg-1a2e1a, #1a2e1a);
        font-family: inherit;
        text-align: left;
        cursor: pointer;
    }

    .rep-place-name {
        min-width: 0;
        font-size: 13px;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rep-place-name.is-unknown {
        font-weight: 600;
        color: #7a9a7a;
    }

    .rep-bar {
        display: block;
        height: 12px;
        border-radius: 6px;
        background: var(--bg-edf7f2, #edf7f2);
        overflow: hidden;
    }

    .rep-bar span {
        display: block;
        height: 100%;
        min-width: 4px;
        border-radius: 6px;
        background: #1e9e62;
    }

    .rep-chevron {
        flex: 0 0 auto;
        font-size: 11px;
        color: #7a9a7a;
        transition: transform 0.15s ease;
    }

    .rep-place-btn[aria-expanded="true"] .rep-chevron {
        transform: rotate(90deg);
    }

    .rep-table tfoot th,
    .rep-table tfoot td {
        border-top: 2px solid var(--bd-e0e8e0, #e0e8e0);
        border-bottom: 0;
        font-weight: 800;
        text-align: left;
    }

    .type-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        flex: 0 0 10px;
        border-radius: 50%;
    }

    .rep-areas[hidden] {
        display: none;
    }

    .rep-area th,
    .rep-area td {
        padding-top: 7px;
        padding-bottom: 7px;
        font-size: 12px;
    }

    .rep-area th {
        padding-left: 31px;
    }

    .rep-area-name {
        display: block;
        min-width: 0;
        font-weight: 700;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .rep-area-name a {
        color: var(--fg-1a2e1a, #1a2e1a);
        text-decoration: none;
    }

    .rep-area-name a:hover {
        color: var(--fg-1e9e62, #1e9e62);
        text-decoration: underline;
    }

    .rep-area-brgy {
        display: block;
        font-weight: 500;
        color: #7a9a7a;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .rep-area-map {
        display: inline-grid;
        place-items: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-1e9e62, #1e9e62);
        text-decoration: none;
    }

    .rep-area-map:hover {
        background: #1e9e62;
        color: #ffffff;
    }

    .rep-area-all td {
        padding: 8px 12px 12px 31px;
    }

    /* Mangrove types: one bar per type */
    .gen-caption {
        margin: -10px 0 14px;
        font-size: 12px;
        color: #7a9a7a;
    }

    /* The tapped type's tip shows here, in a slot of fixed height, so the card never changes height. */
    .gen-hint {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        min-height: calc(2 * 1.45em);
        line-height: 1.45;
    }

    .gen-hint strong {
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .gen-hint .type-dot {
        margin-top: 3px;
    }

    .gen-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr));
        gap: 4px 28px;
    }

    .gen-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 5px 10px;
        align-items: center;
        width: 100%;
        padding: 6px 0;
        border: 0;
        border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        background: none;
        color: inherit;
        font-family: inherit;
        text-align: left;
        cursor: pointer;
    }

    .gen-row[aria-pressed="true"] .gen-name {
        color: var(--fg-1e9e62, #1e9e62);
    }

    .gen-name {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
        font-size: 14px;
        font-weight: 800;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .gen-name small {
        font-size: 11px;
        font-weight: 600;
        font-style: italic;
        color: #7a9a7a;
    }

    .gen-count {
        font-size: 12px;
        font-weight: 700;
        color: var(--fg-4a6a4a, #4a6a4a);
        white-space: nowrap;
    }

    .gen-count strong {
        font-size: 15px;
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    .gen-row .rep-bar {
        grid-column: 1 / -1;
        height: 8px;
        background: var(--bg-f0f4f0, #f0f4f0);
    }

    @media (max-width: 768px) {

        /* Phones: a shorter histogram, and the table drops the Share column. */
        .rep-histogram {
            height: 200px;
        }

        .rep-col-ha { width: 76px; }
        .rep-col-count { width: 52px; }

        .rep-cell-share {
            display: none;
        }

        .rep-table th,
        .rep-table td {
            padding-left: 6px;
            padding-right: 6px;
        }

        .rep-area th,
        .rep-area-all td {
            padding-left: 25px;
        }
    }

    .rep-subtitle {
        margin-top: 4px;
        font-size: 12px;
        font-weight: 600;
        color: #7a9a7a;
    }

    /* Phones: compact cards, report close button in the corner, natural-height charts, and the recent
       delineations as stacked rows instead of a table wider than the screen. */
    @media (max-width: 768px) {
        /* The icon sits in the corner, so the figures get the card's full width. */
        .kpi-card {
            position: relative;
            display: block;
            padding: 12px;
        }

        .kpi-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 28px;
            height: 28px;
            font-size: 13px;
        }

        .kpi-val {
            padding-right: 32px;
        }

        .kpi-val {
            font-size: 18px;
        }

        .kpi-label {
            margin-top: 2px;
            font-size: 11px;
            line-height: 1.3;
        }

        .kpi-badge {
            margin-top: 4px;
            font-size: 10px;
        }

        .kpi-report-hint {
            margin-top: 6px;
        }

        .rep-panel {
            position: relative;
        }

        .rep-panel .table-header {
            padding-right: 40px;
        }

        .rep-panel .rep-close {
            position: absolute;
            top: 14px;
            right: 14px;
        }

        .charts-grid > .chart-card:not(.chart-card-wide) {
            height: auto;
        }

        /* Phones: every type shows and the page scrolls normally (no scroll area inside the card that
           would catch the swipe). */
        #genusCard .gen-list,
        #genusCard .gen-list.has-more {
            max-height: none;
            overflow: visible;
            -webkit-mask-image: none;
            mask-image: none;
            padding-right: 0;
        }

        .charts-grid > .chart-card > .chart-canvas-wrap {
            flex: none;
            min-height: 0;
            height: 220px;
        }
    }

    @media (max-width: 640px) {
        .recent-table,
        .recent-table tbody {
            display: block;
        }

        .recent-table thead {
            display: none;
        }

        .recent-table tr {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 6px 12px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
        }

        .recent-table tr:hover td {
            background: none;
        }

        .recent-table td {
            display: block;
            padding: 0;
            border: 0;
        }

        .recent-table td:nth-child(1) {
            grid-column: 1 / -1;
        }

        .recent-table td:nth-child(2) {
            display: none;
        }

        .recent-table td:nth-child(3) {
            grid-column: 1;
            grid-row: 2;
        }

        .recent-table td:nth-child(4) {
            grid-column: 1;
            grid-row: 3;
        }

        .recent-table td:nth-child(5) {
            grid-column: 2;
            grid-row: 2 / span 2;
        }
    }
</style>
@endsection

@section('content')
<div class="dash-container">
    <!-- Hero Banner -->
    <div class="dash-hero">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="bi bi-shield-check"></i>
                <span>{{ Auth::user()->isExpert() ? 'Expert Reviewer' : 'Resident Mapper' }}</span>
            </div>
            <h1>Welcome back, {{ Auth::user()->name }}</h1>
            <p>Monitor local mangrove health, delineate geospatial forest boundaries, and perform high-precision canopy segmentations with our AI models.</p>
        </div>

        @include('partials.field-conditions-card')
    </div>

    @php
        $myDelineations = collect($delineations ?? []);
        $recentDelineations = collect($dashboardDelineations ?? $delineations ?? []);
        $totalDels = $myDelineations->count();
        $approvedDels = $myDelineations->filter(fn($d) => !empty($d['is_approved']))->count();
        $pendingDels = $myDelineations->filter(fn($d) => empty($d['is_approved']) && empty($d['is_rejected']))->count();
    @endphp

    <!-- KPI Statistics Grid -->
    <div class="kpi-grid">
        {{-- Opens the Mangrove Report (by municipality) right below the cards. --}}
        <button type="button" class="kpi-card kpi-card-toggle" id="reportToggle" aria-expanded="false" aria-controls="municipalityReport">
            <span class="kpi-icon"><i class="bi bi-pie-chart-fill"></i></span>
            <span class="kpi-info">
                {{-- Expert-approved delineations, system-wide. --}}
                <span class="kpi-val">{{ number_format($totalCoverage ?? 0, ($totalCoverage ?? 0) < 100 ? 2 : 0) }} ha</span>
                <span class="kpi-label">Total Monitored Area</span>
                @if(isset($coverageChangePct))
                    <span class="kpi-badge {{ $coverageChangePct >= 0 ? 'g' : 'a' }}">{{ $coverageChangePct >= 0 ? '+' : '' }}{{ number_format($coverageChangePct, 1) }}% since last year</span>
                @else
                    <span class="kpi-badge g">{{ number_format($approvedAreaCount ?? 0) }} approved {{ Str::plural('area', $approvedAreaCount ?? 0) }}</span>
                @endif
                <span class="kpi-report-hint"><span data-label-closed>View report</span><span data-label-open hidden>Hide report</span> <i class="bi bi-chevron-down"></i></span>
            </span>
        </button>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-flower1"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $genusCount ?? 0 }}</div>
                <div class="kpi-label">Mangrove Genera Found</div>
                <span class="kpi-badge g">In approved areas</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-vector-pen"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $totalDels }}</div>
                <div class="kpi-label">Your Delineations</div>
                <span class="kpi-badge {{ $approvedDels > 0 ? 'g' : 'a' }}">{{ $approvedDels }} Approved · {{ $pendingDels }} Pending</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-camera-fill"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ $droneUploadCount ?? 0 }}</div>
                <div class="kpi-label">Drone Images Uploaded</div>
                <span class="kpi-badge g">{{ $droneAiMappedCount ?? 0 }} Mapped by AI</span>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <div class="kpi-info">
                <div class="kpi-val">{{ number_format($communityContributors ?? 0) }}</div>
                <div class="kpi-label">Community Contributors</div>
                @if(!empty($myContributorRank))
                    <span class="kpi-badge g">You're #{{ $myContributorRank }} of {{ $communityContributors }}</span>
                @else
                    <span class="kpi-badge a">Get a delineation approved to join</span>
                @endif
            </div>
        </div>
    </div>

    @php
        $mapRoute = auth()->user()->isExpert() ? route('expert.map') : route('map');
        $report = collect($municipalityReport ?? []);
        $reportPlaces = $report->whereNotNull('name');
        $provinceTotal = (float) ($totalCoverage ?? 0);
        $formatHa = fn ($ha) => number_format((float) $ha, $ha < 100 ? 2 : 0);
        $municipalityTotal = $reportPlaces->count() + count($municipalitiesWithoutAreas ?? []);
    @endphp

    <!-- Mangrove report by municipality: approved areas only, shown as tiles and bars -->
    {{-- Hidden until the "Total Monitored Area" card is clicked. --}}
    <section class="table-card rep-panel" id="municipalityReport" aria-labelledby="municipalityReportTitle" hidden>
        <div class="table-header">
            <div>
                <div class="table-title" id="municipalityReportTitle"><i class="bi bi-geo-alt me-2"></i> Mangrove Report</div>
                <div class="rep-subtitle">
                    Mapped areas @if($report->isNotEmpty())· {{ $reportPlaces->count() }}{{ !empty($municipalitiesWithoutAreas) ? ' of '.$municipalityTotal : '' }} {{ Str::plural('municipality', $municipalityTotal ?: $reportPlaces->count()) }} @endif
                </div>
            </div>
            <div class="rep-head-tools">
                @if($report->isNotEmpty())
                    <button type="button" class="btn-table-action" id="municipalityReportCsv" title="Download as a spreadsheet (CSV)">
                        <i class="bi bi-download"></i> <span>CSV</span>
                    </button>
                @endif
                <button type="button" class="rep-close" id="reportClose" title="Hide the report" aria-label="Hide the report"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>

        @if($report->isEmpty())
            <div class="chart-empty" style="padding: 24px 0;">No approved mangrove areas yet.</div>
        @else
            {{-- A histogram (one column per municipality, hectares) over a table with the figures.
                 Tap a column or a row for that municipality's areas, one by one. --}}
            @php
                $reportHa = max(0.0001, (float) $report->sum('area_ha'));
                $formatShare = fn ($pct) => number_format($pct, $pct < 10 ? 1 : 0).'%';
            @endphp
            <div class="rep-histogram">
                <canvas id="reportHistogram" role="img" aria-label="Histogram of approved mangrove hectares per municipality"></canvas>
            </div>
            <p class="rep-hint"><i class="bi bi-hand-index"></i> Tap a column or a row to see that municipality's areas.</p>
            <div class="rep-table-wrap">
                <table class="custom-table rep-table">
                    <thead>
                        <tr>
                            <th scope="col" class="rep-col-name">Municipality</th>
                            <th scope="col" class="rep-num rep-col-ha">Hectares</th>
                            <th scope="col" class="rep-num rep-cell-share rep-col-share">Share</th>
                            <th scope="col" class="rep-num rep-col-count">Areas</th>
                        </tr>
                    </thead>
                    @foreach($report as $rowIndex => $row)
                        @php
                            $placeKey = $row['name'] ?? '__unknown';
                            $placeName = $row['name'] ?? 'Location not identified';
                            $share = $row['area_ha'] / $reportHa * 100;
                            $placeHa = max(0.0001, (float) $row['area_ha']);
                        @endphp
                        <tbody class="rep-group" data-place="{{ $placeKey }}">
                            <tr class="rep-row">
                                <th scope="row">
                                    <button type="button" class="rep-place-btn" aria-expanded="false" aria-controls="reportAreas{{ $rowIndex }}" data-place="{{ $placeKey }}"
                                        title="{{ $placeName }}: {{ $formatHa($row['area_ha']) }} ha, {{ $formatShare($share) }} of all mapped">
                                        <i class="bi bi-chevron-right rep-chevron" aria-hidden="true"></i>
                                        <span class="rep-place-name {{ $row['name'] ? '' : 'is-unknown' }}">{{ $placeName }}</span>
                                    </button>
                                </th>
                                <td class="rep-num rep-ha">{{ $formatHa($row['area_ha']) }}</td>
                                <td class="rep-num rep-cell-share">{{ $formatShare($share) }}</td>
                                <td class="rep-num">{{ number_format($row['area_count']) }}</td>
                            </tr>
                        </tbody>
                        <tbody class="rep-areas" id="reportAreas{{ $rowIndex }}" hidden>
                            @foreach($row['areas'] ?? [] as $area)
                                @php
                                    $areaShare = $area['area_ha'] / $placeHa * 100;
                                @endphp
                                <tr class="rep-area">
                                    <th scope="row">
                                        <span class="rep-area-name">
                                            <a href="{{ $mapRoute }}?delineation={{ $area['id'] }}" title="View {{ $area['name'] ?: 'this area' }} on the map">{{ $area['name'] ?: 'Unnamed area' }}</a>
                                            <span class="rep-area-brgy"><i class="bi bi-geo-alt"></i> {{ !empty($area['barangays']) ? implode(', ', $area['barangays']) : '—' }}</span>
                                        </span>
                                    </th>
                                    <td class="rep-num rep-ha">{{ $formatHa($area['area_ha']) }}</td>
                                    <td class="rep-num rep-cell-share" title="Share of {{ $placeName }}">{{ $formatShare($areaShare) }}</td>
                                    <td class="rep-num">
                                        <a href="{{ $mapRoute }}?delineation={{ $area['id'] }}" class="rep-area-map" title="View on map" aria-label="View {{ $area['name'] ?: 'this area' }} on the map"><i class="bi bi-map"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                            @if($row['name'])
                                <tr class="rep-area-all">
                                    <td colspan="4">
                                        <a href="{{ $mapRoute }}?municipality={{ urlencode($row['name']) }}" class="btn-table-action"><i class="bi bi-map"></i> All of {{ $row['name'] }} on the map</a>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    @endforeach
                    <tfoot>
                        <tr>
                            <th scope="row">Total</th>
                            <td class="rep-num rep-ha">{{ $formatHa($report->sum('area_ha')) }}</td>
                            <td class="rep-num rep-cell-share">100%</td>
                            <td class="rep-num">{{ number_format($report->sum('area_count')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        @endif
    </section>


    <!-- Charts Row -->
    <div class="charts-grid">
        <!-- Mangrove types (genus distribution): one bar per type -->
        <section class="chart-card" id="genusCard" aria-labelledby="genusCardTitle">
            <div class="chart-header" style="flex-wrap: wrap; gap: 10px;">
                <div class="chart-title" id="genusCardTitle"><i class="bi bi-tree me-2"></i> Mangrove Types (Genus)</div>
                @if(!empty($generaCounts) && $reportPlaces->count() > 0)
                    <select id="genusPlace" class="chart-select" aria-label="Show mangrove types for">
                        <option value="">All municipalities</option>
                        @foreach($reportPlaces->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $row)
                            <option value="{{ $row['name'] }}">{{ $row['name'] }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            @if(empty($generaCounts))
                <div class="chart-empty" style="padding: 24px 0;">No mangrove types recorded yet.</div>
            @else
                <p class="gen-caption gen-hint" id="genusHint" aria-live="polite"><i class="bi bi-info-circle"></i> <span>How many approved areas each type was found in. One area can have several types. Tap a type to learn how to recognise it.</span></p>
                <ul class="gen-list" id="genusList"></ul>
            @endif
        </section>

        <div class="chart-card">
            <div class="chart-header" style="flex-wrap: wrap; gap: 8px 12px;">
                <div class="chart-title"><i class="bi bi-graph-up me-2"></i> Mangrove Area Over Time (ha)</div>
                {{-- Off by default: one line from 1985, previous data (satellite) then MangroveMap's own years. --}}
                @if(!empty($trendValues) && !empty($previousDataTrend))
                    <label class="trend-previous-toggle" title="Continue the line back to {{ $previousDataTrend['years'][0] }} with the satellite record for {{ $previousDataTrend['province'] }}">
                        <input type="checkbox" id="trendPreviousToggle"> Include previous data
                    </label>
                @endif
                @if(!empty($previousDataTrend))
                    {{-- Year filter: which years the chart shows (filled by the script from the chart's years). --}}
                    <span class="trend-range" id="trendRange" hidden>
                        <label>From <select id="trendFrom" class="chart-select" aria-label="Show from year"></select></label>
                        <label>to <select id="trendTo" class="chart-select" aria-label="Show to year"></select></label>
                    </span>
                @endif
            </div>
            <div class="chart-canvas-wrap">
                {{-- No approved areas yet: the chart still shows the previous data (satellite) on its own. --}}
                @if(!empty($trendValues) || !empty($previousDataTrend))
                    <canvas id="coverageTrendChart"></canvas>
                @else
                    <div class="chart-empty">No approved mangrove areas yet.</div>
                @endif
            </div>
            {{-- With "Include previous data" on, the chart is one line: previous data (satellite) up to its latest year
                 in amber, then MangroveMap's own years in green. The note explains the drop where it turns green. --}}
            @if(!empty($previousDataTrend) && empty($trendValues))
                <p class="chart-note trend-previous-explain">
                    <span class="trend-key trend-key-previous" aria-hidden="true"></span> Previous data (satellite, about 30 m), {{ $previousDataTrend['province'] }}.
                    No MangroveMap areas are approved yet; they will continue this line in green.
                    <span class="trend-previous-note"><a href="{{ $mapRoute }}?compare=previous">Compare on the map <i class="bi bi-arrow-right" aria-hidden="true"></i></a></span>
                </p>
            @elseif(!empty($previousDataTrend))
                <p class="chart-note trend-previous-explain" id="trendPreviousExplain" hidden>
                    <span class="trend-key trend-key-previous" aria-hidden="true"></span> Previous data (satellite, about 30 m), {{ $previousDataTrend['province'] }}
                    · <span class="trend-key trend-key-own" aria-hidden="true"></span> MangroveMap.
                    The drop after {{ end($previousDataTrend['years']) }} is a change of method, not mangrove loss: MangroveMap counts only the areas surveyed so far.
                    <span class="trend-previous-note"><a href="{{ $mapRoute }}?compare=previous">Compare on the map <i class="bi bi-arrow-right" aria-hidden="true"></i></a></span>
                </p>
            @endif
            @if(!empty($comparison['satellite']) && !empty($trendValues))
                <p class="chart-note trend-previous-note" id="trendPreviousNote">
                    Previous data (satellite, {{ $comparison['satellite']['latest_year'] }}):
                    <strong>{{ rtrim(rtrim(number_format($comparison['satellite']['latest_hectares'], 2), '0'), '.') }} ha</strong>
                    · <a href="{{ $mapRoute }}?compare=previous">Compare on the map <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </p>
            @endif
        </div>

        {{-- Satellite estimate for the whole province: a different measure from the approved-area trend above. --}}
        @if(!empty($mangroveHistory))
            <div class="chart-card chart-card-wide">
                <div class="chart-header">
                    <div class="chart-title"><i class="bi bi-globe-asia-australia me-2"></i> Mangrove Extent by Year, Satellite (ha)</div>
                    <select id="satelliteExtentPlace" class="chart-select" aria-label="Place">
                        <option value="">{{ $mangroveHistory['province'] }}</option>
                        @foreach($mangroveHistory['municipalities'] as $index => $place)
                            <option value="{{ $index }}">{{ $place['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="chart-canvas-wrap">
                    <canvas id="satelliteExtentChart"></canvas>
                </div>
                <p class="chart-note" id="satelliteExtentSummary" aria-live="polite"></p>
                <p class="chart-note">
                    All mangroves seen by satellite at about 30 m, not only the areas mapped in this system. Small or narrow stands can be missed.
                    Source:
                    @if(!empty($mangroveHistory['source_url']))
                        <a href="{{ $mangroveHistory['source_url'] }}" target="_blank" rel="noopener">{{ $mangroveHistory['source'] }}</a>@else{{ $mangroveHistory['source'] }}@endif{{ !empty($mangroveHistory['license']) ? ' ('.$mangroveHistory['license'].')' : '' }}.
                </p>
            </div>
        @endif
    </div>

    @if(!empty($comparison))
        @include('partials.mangrove-comparison', [
            'comparison' => $comparison,
            'cardClass' => 'table-card',
            'showSurveys' => auth()->user()->isExpert(),
            'mapUrl' => $mapRoute,
        ])
    @endif

    <!-- Top Contributors Leaderboard -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="bi bi-trophy me-2"></i> Top Contributors</div>
            <span class="kpi-badge g">{{ $activeContributorsThisMonth ?? 0 }} active this month</span>
        </div>

        @if(collect($topContributors ?? [])->isEmpty())
            <div style="text-align: center; padding: 24px 20px; color: #7a9a7a; font-size: 14px;">
                No approved delineations yet. Be the first contributor!
            </div>
        @else
            <ol class="contributor-list">
                @foreach($topContributors as $index => $contributor)
                    @php $isMe = $contributor->user_id === auth()->id(); @endphp
                    <li class="contributor-row {{ $isMe ? 'is-me' : '' }}">
                        <span class="contributor-rank">{{ $index + 1 }}</span>
                        <span class="contributor-name">
                            {{ $contributor->user?->name ?? 'Unknown' }}
                            @if($isMe)<span class="contributor-you">You</span>@endif
                        </span>
                        <span class="contributor-role">{{ $contributor->user?->roleLabel() ?? 'Resident' }}</span>
                        <span class="contributor-count">{{ $contributor->approved_count }} approved</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <!-- Recent Submissions Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="bi bi-clock-history me-2"></i> Recent Delineations</div>
            <a href="{{ auth()->user()->isExpert() ? route('expert.delineate') : route('delineate') }}" class="btn-table-action">
                <i class="bi bi-plus-lg"></i>
                <span>New Delineation</span>
            </a>
        </div>

        @if($recentDelineations->isEmpty())
            <div style="text-align: center; padding: 40px 20px; color: #7a9a7a;">
                <i class="bi bi-bounding-box" style="font-size: 36px; display: block; margin-bottom: 10px; color: #b8d9c6;"></i>
                <p style="margin: 0; font-size: 14px;">{{ auth()->user()->isExpert() ? 'No resident delineations are available to review yet.' : "You haven't submitted any zone delineations yet." }}</p>
                <a href="{{ auth()->user()->isExpert() ? route('expert.delineate') : route('delineate') }}" style="display: inline-block; margin-top: 12px; color: var(--fg-1e9e62, #1e9e62); font-weight: 700; text-decoration: none;">
                    Start drawing your first boundary &rarr;
                </a>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="custom-table recent-table">
                    <thead>
                        <tr>
                            <th>Zone Name / Notes</th>
                            <th>Features</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDelineations->take(8) as $d)
                            @php
                                $isApp = !empty($d['is_approved']);
                                $isRej = !empty($d['is_rejected']);
                                $featureCount = count($d['features'] ?? []);
                                $dateStr = !empty($d['created_at']) ? \Carbon\Carbon::parse($d['created_at'])->format('M d, Y') : '—';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--fg-1a2e1a, #1a2e1a);">{{ $d['name'] ?? 'Unnamed zone' }}</div>
                                    @if(!empty($d['notes']))
                                        <div style="font-size: 11px; color: #7a9a7a; margin-top: 2px;">{{ Str::limit($d['notes'], 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: var(--fg-4a6a4a, #4a6a4a); font-weight: 600;">
                                        <i class="bi bi-layers me-1"></i> {{ $featureCount }} {{ Str::plural('geometry', $featureCount) }}
                                    </span>
                                </td>
                                <td>
                                    @if($isApp)
                                        <span class="status-badge approved"><i class="bi bi-check-circle-fill"></i> Approved</span>
                                    @elseif($isRej)
                                        <span class="status-badge rejected"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                        @if(!auth()->user()->isExpert() && !empty($d['removable_after']))
                                            @php $removableAt = \Carbon\Carbon::parse($d['removable_after'])->timezone(config('app.timezone')); @endphp
                                            <div style="font-size: 11px; color: var(--fg-d04030, #d04030); margin-top: 4px;">
                                                @if($removableAt->isFuture())
                                                    Revise by {{ $removableAt->format('M d, Y') }} or an expert may remove it
                                                @else
                                                    Revision period over, so an expert may remove it
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <span class="status-badge pending"><i class="bi bi-hourglass-split"></i> Under Review</span>
                                    @endif
                                </td>
                                <td style="color: #7a9a7a; font-size: 12px;">{{ $dateStr }}</td>
                                <td style="text-align: right;">
                                    {{-- Opens the record on the Delineate page (review, revise or edit it there). --}}
                                    <a href="{{ \App\Models\Delineation::delineateUrlForId($d['id'], auth()->user(), false) }}" class="btn-table-action" title="Open delineation" aria-label="Open delineation">
                                        <i class="bi bi-eye"></i> View<span class="action-label-extra"> Delineation</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Mangrove types: one bar per type (share of approved areas where it grows), for all or one municipality.
        // Tapping a type shows how to recognise it.
        const genusList = document.getElementById('genusList');
        if (genusList) {
            const genusInfo = @json(\App\Support\MangroveGenera::INFO);
            const otherColor = @json(\App\Support\MangroveGenera::OTHER_COLOR);
            const all = {
                area_count: @json((int) ($approvedAreaCount ?? 0)),
                genera: @json((object) ($generaCounts ?? [])),
                genera_ha: @json((object) ($generaHectares ?? [])),
            };
            const places = Object.fromEntries(@json($municipalityReport ?? [])
                .filter(row => row.name)
                .map(row => [row.name, row]));
            const placeEl = document.getElementById('genusPlace');
            const formatHa = value => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: value < 100 ? 2 : 0 });
            const make = (tag, className, text) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            };

            const hintEl = document.getElementById('genusHint');
            const defaultHint = hintEl.innerHTML;
            let pickedGenus = null;
            // Tap a type: its tip replaces the caption (tap it again for the caption back).
            const showHint = (genus, info) => {
                pickedGenus = pickedGenus === genus ? null : genus;
                genusList.querySelectorAll('.gen-row').forEach(button => {
                    button.setAttribute('aria-pressed', button.dataset.genus === pickedGenus ? 'true' : 'false');
                });
                if (!pickedGenus) {
                    hintEl.innerHTML = defaultHint;
                    return;
                }
                const dot = make('span', 'type-dot');
                dot.style.background = info.color || otherColor;
                const text = make('span');
                text.append(make('strong', '', info.local ? `${info.local}:` : `${genus}:`), ` ${info.about}`);
                hintEl.replaceChildren(dot, text);
            };

            const render = () => {
                pickedGenus = null;
                hintEl.innerHTML = defaultHint;
                const data = placeEl?.value ? places[placeEl.value] : all;
                if (!data) return;
                const total = data.area_count || 0;
                const entries = Object.entries(data.genera || {});
                if (!entries.length) {
                    genusList.replaceChildren(make('li', 'gen-caption', 'No mangrove types recorded here yet.'));
                    return;
                }

                genusList.replaceChildren(...entries.map(([genus, count]) => {
                    const info = genusInfo[genus] || {};
                    const share = total ? Math.round(count / total * 100) : 0;
                    const item = make('li');
                    const row = make('button', 'gen-row');
                    row.type = 'button';
                    row.dataset.genus = genus;
                    row.setAttribute('aria-pressed', 'false');
                    row.title = `${info.local ? `${info.local} (${genus})` : genus}: found in ${count} of ${total} approved ${total === 1 ? 'area' : 'areas'} (${share}%), which cover about ${formatHa(data.genera_ha?.[genus])} ha${info.about ? `. ${info.about}` : ''}`;

                    const name = make('span', 'gen-name');
                    const dot = make('span', 'type-dot');
                    dot.style.background = info.color || otherColor;
                    name.append(dot, info.local || genus);
                    if (info.local) name.append(make('small', '', genus));

                    const figure = make('span', 'gen-count');
                    // Spelled out ("found in 5 of 12 areas"), not "5 / 12 · 42%", so nobody has to guess what it counts.
                    figure.append('in ', make('strong', '', String(count)), ` of ${total} ${total === 1 ? 'area' : 'areas'}`);

                    const bar = make('span', 'rep-bar');
                    const fill = make('span');
                    fill.style.width = `${share}%`;
                    fill.style.background = info.color || otherColor;
                    bar.append(fill);

                    row.append(name, figure, bar);
                    item.append(row);
                    if (info.about) row.addEventListener('click', () => showHint(genus, info));
                    return item;
                }));
            };

            // Fade the bottom while more types are hidden below (the list scrolls inside its fixed-size card).
            const markMore = () => {
                genusList.classList.toggle('has-more', genusList.scrollTop + genusList.clientHeight < genusList.scrollHeight - 2);
            };
            genusList.addEventListener('scroll', markMore, { passive: true });
            window.addEventListener('resize', markMore);

            placeEl?.addEventListener('change', () => { render(); genusList.scrollTop = 0; markMore(); });
            render();
            markMore();
        }

        // "Total Monitored Area" card opens and closes the Mangrove Report (remembered in this browser).
        (() => {
            const toggle = document.getElementById('reportToggle');
            const panel = document.getElementById('municipalityReport');
            if (!toggle || !panel) return;
            const KEY = 'mangrovemap.dashboardReportOpen';
            const setOpen = (open, { scroll = false, remember = true } = {}) => {
                panel.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.querySelector('[data-label-closed]').hidden = open;
                toggle.querySelector('[data-label-open]').hidden = !open;
                if (remember) {
                    try { localStorage.setItem(KEY, open ? '1' : '0'); } catch (e) { /* storage unavailable */ }
                }
                if (open && scroll) {
                    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    panel.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
                }
            };
            toggle.addEventListener('click', () => setOpen(panel.hidden, { scroll: true }));
            document.getElementById('reportClose')?.addEventListener('click', () => {
                setOpen(false);
                toggle.focus();
            });
            let remembered = null;
            try { remembered = localStorage.getItem(KEY); } catch (e) { /* storage unavailable */ }
            if (remembered === '1' || window.location.hash === '#municipalityReport') {
                setOpen(true, { remember: false });
            }
        })();

        // Mangrove report: a bar per municipality; tapping it opens its areas one by one. CSV: everything.
        const reportEl = document.getElementById('municipalityReport');
        const csvButton = document.getElementById('municipalityReportCsv');
        if (reportEl && csvButton) {
            const rows = @json($municipalityReport ?? []);
            const groups = [...reportEl.querySelectorAll('.rep-group')];
            let histogram = null;
            const setPlaceOpen = (group, open) => {
                const button = group.querySelector('.rep-place-btn');
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                group.classList.toggle('is-open', open);
                document.getElementById(button.getAttribute('aria-controls')).hidden = !open;
                paintHistogram();
            };

            // The whole table row opens it, not only the name; the button keeps it keyboard-accessible.
            groups.forEach(group => group.querySelector('.rep-row').addEventListener('click', () => {
                setPlaceOpen(group, !group.classList.contains('is-open'));
            }));

            // Histogram: one column per municipality (hectares). Open municipalities stay dark green,
            // the rest turn lighter once any is open. Tapping a column opens its rows in the table.
            const BAR = '#1e9e62';
            const BAR_DIM = 'rgba(30, 158, 98, 0.35)';
            function paintHistogram() {
                if (!histogram) return;
                const anyOpen = groups.some(group => group.classList.contains('is-open'));
                histogram.data.datasets[0].backgroundColor = groups.map(group =>
                    !anyOpen || group.classList.contains('is-open') ? BAR : BAR_DIM);
                histogram.update('none');
            }
            const drawHistogram = () => {
                const canvas = document.getElementById('reportHistogram');
                if (histogram || !canvas || reportEl.hidden || typeof Chart === 'undefined') return;
                const total = rows.reduce((sum, row) => sum + Number(row.area_ha || 0), 0) || 1;
                const ha = value => Number(value).toLocaleString(undefined, { maximumFractionDigits: value < 100 ? 2 : 0 });
                const gridColor = () => (window.MangroveTheme?.get() === 'dark' ? 'rgba(255, 255, 255, 0.07)' : '#f0f4f0');
                histogram = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: rows.map(row => row.name || 'Not identified'),
                        datasets: [{
                            label: 'Approved mangrove area (ha)',
                            data: rows.map(row => Number(row.area_ha || 0)),
                            backgroundColor: BAR,
                            hoverBackgroundColor: '#16824f',
                            borderRadius: 4,
                            categoryPercentage: 0.96,
                            barPercentage: 0.96,
                            minBarLength: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => {
                                        const row = rows[ctx.dataIndex];
                                        const pct = row.area_ha / total * 100;
                                        return [
                                            `${ha(row.area_ha)} ha (${pct.toFixed(pct < 10 ? 1 : 0)}%)`,
                                            `${row.area_count} approved ${row.area_count === 1 ? 'area' : 'areas'}`,
                                        ];
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Hectares', font: { family: 'Manrope', size: 11, weight: '600' } },
                                grid: { color: gridColor },
                                ticks: { font: { family: 'Manrope', size: 11 } },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { family: 'Manrope', size: 11 }, autoSkip: false, maxRotation: 60 },
                            },
                        },
                        onHover: (event, elements) => {
                            event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        },
                        onClick: (event, elements) => {
                            if (!elements.length) return;
                            const group = groups[elements[0].index];
                            if (!group) return;
                            const opening = !group.classList.contains('is-open');
                            setPlaceOpen(group, opening);
                            if (opening) {
                                const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                                group.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
                            }
                        },
                    },
                });
                paintHistogram();
            };
            // The report starts hidden; Chart.js needs a visible canvas, so draw it the first time it opens.
            drawHistogram();
            new MutationObserver(drawHistogram).observe(reportEl, { attributes: true, attributeFilter: ['hidden'] });

            // As a spreadsheet (opens in Excel or Google Sheets): municipality totals, then each area.
            csvButton.addEventListener('click', () => {
                const cell = value => {
                    const text = String(value ?? '');
                    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
                };
                const lines = [
                    ['Municipality', 'Approved areas', 'Mangrove area (ha)', 'Mangrove types found (areas)', 'Mappers', 'Last approved'],
                    ...rows.map(row => [
                        row.name || 'Location not identified',
                        row.area_count,
                        Number(row.area_ha).toFixed(2),
                        Object.entries(row.genera || {}).map(([genus, count]) => `${genus} (${count})`).join('; '),
                        row.contributors,
                        row.last_approved_at ? row.last_approved_at.slice(0, 10) : '',
                    ]),
                    [],
                    ['Each approved area'],
                    ['Municipality', 'Area', 'Barangay', 'Mangrove area (ha)', 'Mangrove types found', 'Mapped by', 'Approved'],
                    ...rows.flatMap(row => (row.areas || []).map(area => [
                        row.name || 'Location not identified',
                        area.name || 'Unnamed area',
                        (area.barangays || []).join('; '),
                        Number(area.area_ha).toFixed(2),
                        (area.genera || []).join('; '),
                        area.mapped_by || '',
                        area.approved_at ? area.approved_at.slice(0, 10) : '',
                    ])),
                ].map(line => line.map(cell).join(','));
                const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `mangrove-report-by-municipality-${new Date().toISOString().slice(0, 10)}.csv`;
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            });
        }

        // Mangrove Area Over Time line
        // Mangrove Area Over Time: MangroveMap's mapped hectares per year. "Include previous data" (off by default,
        // remembered per browser) turns it into one line from the satellite's first year: previous data (amber)
        // up to the satellite's latest year, then MangroveMap's later years (green), joined by a dashed segment
        // and a dashed marker where the method changes; the note under the chart explains the drop there.
        const trendCtx = document.getElementById('coverageTrendChart');
        if (trendCtx) {
            const years = @json($trendYears ?? []);
            const values = @json($trendValues ?? []);
            const previous = @json($previousDataTrend ?? null);
            const GREEN = '#1e9e62', AMBER = '#c98f00';
            const KEY = 'mangrovemap-trend-previous';
            const toggle = document.getElementById('trendPreviousToggle');
            const explain = document.getElementById('trendPreviousExplain');
            const note = document.getElementById('trendPreviousNote');
            const gridColor = () => (window.MangroveTheme?.get() === 'dark' ? 'rgba(255, 255, 255, 0.07)' : '#f0f4f0');
            const tickFont = { family: 'Manrope', size: 11 };
            const formatHa = value => Number(value).toLocaleString(undefined, { maximumFractionDigits: value < 100 ? 2 : 1 });

            function series(withPrevious) {
                if (!withPrevious || !previous) {
                    return { labels: years, data: values, switchAt: null };
                }
                const lastPrevious = previous.years[previous.years.length - 1];
                const own = years.map((year, index) => [year, values[index]]).filter(([year]) => year > lastPrevious);
                return {
                    labels: [...previous.years, ...own.map(([year]) => year)],
                    data: [...previous.hectares, ...own.map(([, value]) => value)],
                    // Index of the last previous-data point: later points are MangroveMap's.
                    switchAt: previous.years.length - 1,
                };
            }

            const isOwn = (chart, index) => chart.$switchAt === null || chart.$switchAt === undefined || index > chart.$switchAt;

            // Dashed vertical marker between the satellite's latest year and MangroveMap's first.
            const methodBreak = {
                id: 'methodBreak',
                afterDatasetsDraw(chart) {
                    const index = chart.$switchAt;
                    if (index === null || index === undefined || index < 0 || index + 1 >= chart.data.labels.length) return;
                    const x = (chart.scales.x.getPixelForValue(index) + chart.scales.x.getPixelForValue(index + 1)) / 2;
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.strokeStyle = '#6a8a6a';
                    ctx.setLineDash([3, 3]);
                    ctx.beginPath();
                    ctx.moveTo(x, chart.chartArea.top);
                    ctx.lineTo(x, chart.chartArea.bottom);
                    ctx.stroke();
                    ctx.restore();
                },
            };

            // Nothing approved yet: show the previous data alone (no switch then).
            const onlyPrevious = !values.length && !!previous;
            let showPrevious = onlyPrevious;
            try { showPrevious = onlyPrevious || (!!toggle && localStorage.getItem(KEY) === '1'); } catch (error) { /* private mode */ }
            const initial = series(showPrevious);

            const trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: initial.labels,
                    datasets: [{
                        label: 'MangroveMap (ha)',
                        data: initial.data,
                        borderColor: GREEN,
                        backgroundColor: 'rgba(30, 158, 98, 0.08)',
                        fill: true,
                        tension: 0.3,
                        // Amber for previous data, green for MangroveMap; the joining segment is dashed.
                        segment: {
                            borderColor: ctx => (isOwn(ctx.chart, ctx.p1DataIndex) ? GREEN : AMBER),
                            borderDash: ctx => (ctx.chart.$switchAt !== null && ctx.chart.$switchAt !== undefined
                                && ctx.p0DataIndex === ctx.chart.$switchAt ? [4, 4] : undefined),
                        },
                        pointBackgroundColor: ctx => (isOwn(ctx.chart, ctx.dataIndex) ? GREEN : AMBER),
                        pointBorderColor: ctx => (isOwn(ctx.chart, ctx.dataIndex) ? GREEN : AMBER),
                        pointRadius: ctx => (isOwn(ctx.chart, ctx.dataIndex) ? 4 : 0),
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: item => `${isOwn(item.chart, item.dataIndex) ? 'MangroveMap' : 'Previous data (satellite)'}: ${formatHa(item.raw)} ha`,
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: showPrevious,
                            grid: { color: gridColor },
                            ticks: { font: tickFont }
                        },
                        x: {
                            grid: { display: false },
                            // Long ranges (with previous data, 1985 →) label only round years, every 5, plus the
                            // last year (e.g. 1985, 1990, … 2025, 2026); short ranges label every year.
                            ticks: {
                                font: tickFont,
                                maxRotation: 0,
                                autoSkip: false,
                                callback(value, index, ticks) {
                                    const year = Number(this.getLabelForValue(value));
                                    if (ticks.length <= 12) return year;
                                    const isLast = index === ticks.length - 1;
                                    // Keep the last year apart from a round year right before it, unless they'd collide.
                                    if (isLast) return year;
                                    if (year % 5 !== 0) return null;
                                    const lastYear = Number(this.getLabelForValue(ticks[ticks.length - 1].value));
                                    return lastYear - year === 1 && ticks.length > 30 ? null : year;
                                },
                            }
                        }
                    }
                },
                plugins: [methodBreak],
            });
            trendChart.$switchAt = initial.switchAt;

            // Year filter (From / To): which years the chart shows while previous data is on. Reset to all
            // years whenever the switch changes.
            const rangeEl = document.getElementById('trendRange');
            const fromEl = document.getElementById('trendFrom');
            const toEl = document.getElementById('trendTo');

            function fillRange(labels) {
                if (!fromEl || !toEl) return;
                const options = labels.map(year => `<option value="${year}">${year}</option>`).join('');
                fromEl.innerHTML = options;
                toEl.innerHTML = options;
                fromEl.value = String(labels[0]);
                toEl.value = String(labels[labels.length - 1]);
            }

            function apply(on) {
                const full = series(on);
                let from = Number(fromEl?.value) || full.labels[0];
                let to = Number(toEl?.value) || full.labels[full.labels.length - 1];
                if (!on) {
                    from = full.labels[0];
                    to = full.labels[full.labels.length - 1];
                }
                if (from > to) [from, to] = [to, from];
                const start = Math.max(0, full.labels.findIndex(year => year >= from));
                let end = full.labels.length - 1;
                while (end > start && full.labels[end] > to) end--;

                trendChart.data.labels = full.labels.slice(start, end + 1);
                trendChart.data.datasets[0].data = full.data.slice(start, end + 1);
                trendChart.$switchAt = full.switchAt === null ? null : full.switchAt - start;
                trendChart.options.scales.y.beginAtZero = on;
                trendChart.update();
                if (explain) explain.hidden = !on;
                if (note) note.hidden = on;
                if (rangeEl) rangeEl.hidden = !on;
            }

            if (toggle) {
                toggle.checked = showPrevious;
                fillRange(series(showPrevious).labels);
                apply(showPrevious);
                toggle.addEventListener('change', () => {
                    fillRange(series(toggle.checked).labels);
                    apply(toggle.checked);
                    try { localStorage.setItem(KEY, toggle.checked ? '1' : '0'); } catch (error) { /* private mode */ }
                });
                fromEl?.addEventListener('change', () => apply(toggle.checked));
                toEl?.addEventListener('change', () => apply(toggle.checked));
            } else if (onlyPrevious) {
                fillRange(series(true).labels);
                apply(true);
                fromEl?.addEventListener('change', () => apply(true));
                toEl?.addEventListener('change', () => apply(true));
            }
        }

        // Mangrove extent by year (satellite): province total, or one municipality from the picker.
        const satelliteCtx = document.getElementById('satelliteExtentChart');
        const satellite = @json($mangroveHistory ?? null);
        if (satelliteCtx && satellite) {
            const placeEl = document.getElementById('satelliteExtentPlace');
            const summaryEl = document.getElementById('satelliteExtentSummary');
            const hectares = () => satellite.municipalities[placeEl.value]?.ha || satellite.total_ha;
            const format = value => value.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 });

            function summarise(values) {
                const firstYear = satellite.years[0];
                const lastYear = satellite.years[satellite.years.length - 1];
                const first = values[0];
                const last = values[values.length - 1];
                const change = last - first;
                const sign = change > 0 ? '+' : '';
                const percent = first > 0 ? ` (${sign}${(change / first * 100).toFixed(1)}%)` : '';
                const peak = Math.max(...values);
                const peakYear = satellite.years[values.indexOf(peak)];

                summaryEl.replaceChildren();
                const latest = document.createElement('strong');
                latest.textContent = `${format(last)} ha in ${lastYear}`;
                summaryEl.append(latest, ` · ${sign}${format(change)} ha${percent} since ${firstYear} · highest ${format(peak)} ha in ${peakYear}`);
            }

            const satelliteChart = new Chart(satelliteCtx, {
                type: 'line',
                data: {
                    labels: satellite.years,
                    datasets: [{
                        label: 'Mangrove extent (ha)',
                        data: hectares(),
                        borderColor: '#2b8fb8',
                        backgroundColor: 'rgba(43, 143, 184, 0.10)',
                        fill: true,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: item => `${format(item.parsed.y)} ha` } }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: () => (window.MangroveTheme?.get() === 'dark' ? 'rgba(255, 255, 255, 0.07)' : '#f0f4f0') },
                            ticks: { font: { family: 'Manrope', size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Manrope', size: 11 }, maxTicksLimit: 9, maxRotation: 0 }
                        }
                    }
                }
            });
            summarise(hectares());

            placeEl.addEventListener('change', () => {
                satelliteChart.data.datasets[0].data = hectares();
                satelliteChart.update();
                summarise(hectares());
            });
        }
    });
</script>
@endsection
