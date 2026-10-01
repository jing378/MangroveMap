<style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --green: #1e9e62;
      --green-d: #16a34a;
      --green-l: #edf7f2;
      --border: #e0e8e0;
      --text: #1a2e1a;
      --muted: #9ab0a0;
      --danger: #d04030;
      --warn: #c07818;
      --bg: #f4f8f4;
      --card: #fff;
      --header-h: 62px;
    }

    body {
      font-family: 'Manrope', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* Layout header is owned by layouts.enduser — do not restyle .header here. */

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    .logo-icon {
      width: 34px;
      height: 34px;
      background: var(--green);
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 18px;
    }

    .logo span {
      font-size: 17px;
      font-weight: 800;
      color: var(--text);
    }

    .logo small {
      font-size: 11px;
      color: var(--muted);
      font-weight: 500;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 9px;
      background: #fff;
      cursor: pointer;
      text-decoration: none;
      transition: all .15s;
    }

    .btn-back:hover {
      background: var(--green-l);
      border-color: var(--green);
      color: var(--green);
    }

    /* ── LAYOUT ── */
    .page {
      margin-top: 0;
      padding: 28px 24px;
      max-width: 1280px;
      margin-left: auto;
      margin-right: auto;
    }

    .page-title {
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 4px;
    }

    .page-sub {
      font-size: 14px;
      color: var(--muted);
      margin-bottom: 24px;
    }

    .layout {
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 20px;
      align-items: start;
    }

    @media (max-width: 860px) {
      .layout {
        grid-template-columns: 1fr;
      }
    }

    /* ── CARDS ── */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .04);
    }

    .card+.card {
      margin-top: 16px;
    }

    .card-title {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .6px;
      color: var(--muted);
      margin-bottom: 14px;
    }

    /* ── UPLOAD ZONE ── */
    .drop-zone {
      border: 2px dashed var(--border);
      border-radius: 14px;
      padding: 32px 20px;
      text-align: center;
      cursor: pointer;
      background: #fff;
      transition: all .18s;
      position: relative;
    }

    .drop-zone.drag-over {
      border-color: var(--green);
      background: var(--green-l);
    }

    .drop-zone:hover {
      border-color: #b0d0b8;
      background: #fafdf9;
    }

    .drop-zone .dz-ico {
      font-size: 38px;
      color: var(--muted);
      margin-bottom: 10px;
      line-height: 1;
    }

    .drop-zone h3 {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 5px;
    }

    .drop-zone p {
      font-size: 13px;
      color: var(--muted);
    }

    #batchFileInput {
      display: none;
    }

    .choose-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 14px;
      padding: 8px 20px;
      background: var(--green);
      color: #fff;
      border-radius: 9px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: background .15s;
    }

    .choose-btn:hover {
      background: var(--green-d);
    }

    /* ── QUEUE ── */
    .queue-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 4px;
    }

    .queue-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      background: #f7faf7;
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: border-color .15s;
    }

    .queue-item:hover {
      border-color: #a0c8b0;
    }

    .queue-item.active {
      border-color: var(--green);
      background: var(--green-l);
    }

    .qi-thumb {
      width: 40px;
      height: 40px;
      border-radius: 7px;
      object-fit: cover;
      flex-shrink: 0;
      background: #e8eee8;
    }

    .qi-info {
      flex: 1;
      min-width: 0;
    }

    .qi-name {
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .qi-sub {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
    }

    .qi-progress {
      height: 4px;
      border-radius: 2px;
      background: var(--border);
      margin-top: 5px;
      overflow: hidden;
    }

    .qi-progress-fill {
      height: 100%;
      border-radius: 2px;
      background: var(--green);
      width: 0%;
      transition: width .4s;
    }

    .qi-badge {
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 100px;
      flex-shrink: 0;
      white-space: nowrap;
    }

    .qi-badge.queued {
      background: #f0f4f0;
      color: var(--muted);
    }

    .qi-badge.running {
      background: #fff8e8;
      color: var(--warn);
    }

    .qi-badge.done {
      background: var(--green-l);
      color: var(--green);
    }

    .qi-badge.failed {
      background: #fdf0ee;
      color: var(--danger);
    }

    /* ── ACTION BUTTONS ── */
    .action-row {
      display: flex;
      gap: 8px;
      margin-top: 14px;
    }

    .btn-primary {
      flex: 1;
      padding: 10px;
      font-size: 14px;
      font-weight: 700;
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background .15s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .btn-primary:hover {
      background: var(--green-d);
    }

    .btn-primary:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .btn-secondary {
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 600;
      background: #fff;
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      transition: all .15s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-secondary:hover {
      background: #f7faf7;
      border-color: #c0d0c0;
    }

    /* ── PREVIEW (right column) ── */
    .preview-card {
      min-height: 420px;
      display: flex;
      flex-direction: column;
    }

    .preview-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
      flex-wrap: wrap;
      gap: 8px;
    }

    .toggle-row {
      display: flex;
      gap: 6px;
    }

    .toggle-btn {
      padding: 5px 14px;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid var(--border);
      border-radius: 7px;
      background: #fff;
      color: var(--muted);
      cursor: pointer;
      transition: all .15s;
    }

    .toggle-btn.active {
      background: var(--green);
      color: #fff;
      border-color: var(--green);
    }

    .toggle-btn:disabled {
      opacity: .4;
      cursor: not-allowed;
    }

    .img-wrapper {
      flex: 1;
      background: #f2f6f2;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      min-height: 320px;
      position: relative;
    }

    .img-wrapper img {
      max-width: 100%;
      max-height: 480px;
      object-fit: contain;
      display: none;
    }

    .no-img-msg {
      text-align: center;
      color: var(--muted);
      padding: 24px;
    }

    .no-img-msg i {
      font-size: 52px;
      display: block;
      margin-bottom: 12px;
    }

    .no-img-msg p {
      font-size: 14px;
      font-weight: 500;
    }

    .no-img-msg small {
      font-size: 12px;
    }

    /* ── RESULT STATS ── */
    .result-stats {
      display: none;
      margin-top: 16px;
      padding: 14px 16px;
      background: var(--green-l);
      border: 1px solid #c0e0d0;
      border-radius: 12px;
    }

    .result-stats .rs-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      padding: 4px 0;
      border-bottom: 1px solid #d8eed8;
    }

    .result-stats .rs-row:last-child {
      border-bottom: none;
    }

    .result-stats .rs-key {
      color: #5a7a5a;
    }

    .result-stats .rs-val {
      font-weight: 700;
      color: var(--text);
    }

    .result-stats .rs-rec {
      font-size: 12px;
      color: #5a7a5a;
      margin-top: 10px;
      line-height: 1.6;
      font-style: italic;
    }

    .conf-row-stat {
      display: flex;
      align-items: center;
      gap: 8px;
      margin: 8px 0;
    }

    .conf-track {
      flex: 1;
      height: 5px;
      background: #d0e8d8;
      border-radius: 3px;
      overflow: hidden;
    }

    .conf-fill {
      height: 100%;
      border-radius: 3px;
    }

    .conf-pct {
      font-size: 12px;
      font-weight: 700;
      width: 32px;
      text-align: right;
    }

    /* ── HISTORY TABLE ── */
    .hist-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .tbl th {
      text-align: left;
      padding: 8px 10px;
      color: var(--muted);
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      border-bottom: 1px solid var(--border);
    }

    .tbl td {
      padding: 10px 10px;
      border-bottom: 1px solid #f0f4f0;
      vertical-align: middle;
    }

    .tbl tr:last-child td {
      border-bottom: none;
    }

    .tbl tr:hover td {
      background: #f9fbf9;
    }

    .st-badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 100px;
      font-size: 11px;
      font-weight: 700;
    }

    .st-completed {
      background: var(--green-l);
      color: var(--green);
    }

    .st-failed {
      background: #fdf0ee;
      color: var(--danger);
    }

    .st-processing {
      background: #fff8e8;
      color: var(--warn);
    }

    .view-btn {
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 600;
      border: 1px solid var(--border);
      border-radius: 7px;
      background: #fff;
      cursor: pointer;
      color: var(--text);
      transition: all .15s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .view-btn:hover {
      border-color: var(--green);
      color: var(--green);
      background: var(--green-l);
    }

    .hist-actions {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: nowrap;
    }

    .tbl th:last-child,
    .tbl td:last-child {
      white-space: nowrap;
      min-width: 168px;
    }

    .del-btn {
      color: var(--danger);
    }

    .del-btn:hover {
      border-color: var(--danger);
      color: var(--danger);
      background: #fdf0ee;
    }

    .btn-danger {
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 600;
      background: #fff;
      color: var(--danger);
      border: 1px solid #f0c8c0;
      border-radius: 10px;
      cursor: pointer;
      transition: all .15s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-danger:hover {
      background: #fdf0ee;
      border-color: var(--danger);
    }

    .hist-header-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .empty-hist {
      text-align: center;
      padding: 32px;
      color: var(--muted);
      font-size: 14px;
    }

    /* Table responsive wrapper */
    .tbl-responsive {
      width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 768px) {
      :root {
        --header-h: 56px;
      }


      .logo-icon {
        width: 30px;
        height: 30px;
        font-size: 15px;
        border-radius: 8px;
      }

      .logo span {
        font-size: 15px;
      }

      .logo small {
        display: none;
      }

      .btn-back {
        padding: 6px 10px;
        font-size: 12px;
      }

      .btn-back-text {
        display: none;
      }

      .page {
        padding: 16px 12px;
        margin-top: 0;
      }

      .page-title {
        font-size: 19px;
      }

      .page-sub {
        font-size: 12.5px;
        line-height: 1.4;
        margin-bottom: 14px;
      }

      .layout {
        gap: 14px;
      }

      .card {
        padding: 14px 12px;
        border-radius: 14px;
      }

      .card + .card {
        margin-top: 12px;
      }

      .card-title {
        font-size: 12px;
        margin-bottom: 10px;
      }

      .drop-zone {
        padding: 20px 12px;
        border-radius: 12px;
      }

      .drop-zone .dz-ico {
        font-size: 30px;
        margin-bottom: 6px;
      }

      .drop-zone h3 {
        font-size: 14px;
      }

      .drop-zone p {
        font-size: 12px;
      }

      .choose-btn {
        margin-top: 10px;
        padding: 7px 14px;
        font-size: 12px;
      }

      .action-row {
        margin-top: 10px;
        gap: 8px;
      }

      .btn-primary {
        padding: 9px;
        font-size: 13px;
      }

      .btn-secondary {
        padding: 9px 12px;
        font-size: 13px;
      }

      .queue-item {
        padding: 8px 10px;
        gap: 8px;
      }

      .qi-thumb {
        width: 36px;
        height: 36px;
      }

      .qi-name {
        font-size: 12.5px;
      }

      .preview-card {
        min-height: unset;
      }

      .preview-header {
        margin-bottom: 10px;
      }

      .toggle-btn {
        padding: 5px 10px;
        font-size: 12px;
      }

      .img-wrapper {
        min-height: 220px;
        max-height: 340px;
        border-radius: 10px;
      }

      .img-wrapper img {
        max-height: 340px;
      }

      .no-img-msg {
        padding: 20px 12px;
      }

      .no-img-msg i {
        font-size: 38px;
        margin-bottom: 8px;
      }

      .result-stats {
        margin-top: 12px;
        padding: 10px 12px;
        border-radius: 10px;
      }

      .result-stats .rs-row {
        font-size: 12px;
        padding: 4px 0;
      }

      .conf-row-stat {
        margin: 6px 0;
      }

      .conf-row-stat span:first-child {
        width: 78px !important;
        font-size: 11px !important;
      }

      .conf-pct {
        font-size: 11px;
        width: 28px;
      }

      .rs-rec {
        font-size: 11.5px;
        margin-top: 8px;
      }

      .hist-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }

      .hist-header-actions {
        width: 100%;
        justify-content: flex-start;
        gap: 6px;
      }

      .hist-header-actions .btn-danger,
      .hist-header-actions .btn-secondary {
        flex: 1;
        justify-content: center;
        padding: 7px 10px;
        font-size: 12px;
      }

      .tbl th, .tbl td {
        padding: 8px 8px;
        font-size: 12px;
      }

      .tbl th:last-child,
      .tbl td:last-child {
        min-width: 110px;
      }

      .view-btn {
        padding: 4px 8px;
        font-size: 11px;
      }
    }
  </style>
