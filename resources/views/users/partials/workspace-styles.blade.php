<style>
  *,
  *::before,
  *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  html,
  body {
    height: 100%;
    font-family: 'Manrope', system-ui, -apple-system, Segoe UI, sans-serif;
    background: #f0f4f0;
    color: #1a2e1a;
    overflow: hidden;
  }

  .header {
    height: 60px;
    background: #fff;
    border-bottom: 1px solid #e0e8e0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 2000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    gap: 8px;
  }

  .logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    font-size: 18px;
    color: #1a2e1a;
    white-space: nowrap;
  }

  .logo-mark {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #fff;
  }

  .header-center {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 1;
    overflow-x: auto;
    scrollbar-width: none;
    white-space: nowrap;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
  }

  .header-center::-webkit-scrollbar {
    display: none;
  }

  .h-tab {
    font-size: 14px;
    font-weight: 500;
    padding: 5px 10px;
    border-radius: 8px;
    cursor: pointer;
    color: #6a8a6a;
    transition: all .15s;
    border: none;
    background: none;
    font-family: 'Manrope', sans-serif;
  }

  .h-tab.active {
    color: #1e9e62;
    background: #edf7f2;
    font-weight: 600;
  }

  .nav-dropdown {
    display: none;
    font-size: 14px;
    padding: 5px 10px;
    border: 1px solid #d4dfd4;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    height: 100%;
  }

  .btn {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 8px;
    border: 1px solid #d4dfd4;
    background: #fff;
    transition: all .15s;
  }

  .btn-g {
    background: #1e9e62;
    color: #fff;
    border-color: #1e9e62;
  }

  .profile-dropdown-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    height: 100%;
  }

  .profile-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 8px;
    transition: all 0.15s;
    background: transparent;
    border: none;
    font-family: 'Manrope', sans-serif;
  }

  .profile-toggle:hover {
    background: #f5f7f6;
  }

  .profile-image {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e8e0;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
    color: #fff;
    font-weight: 700;
    font-size: 16px;
  }

  .profile-info {
    flex: 1;
    text-align: right;
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .profile-name {
    font-weight: 600;
    font-size: 13px;
    color: #1a2e1a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
  }

  .profile-role {
    font-size: 11px;
    color: #7a9a7a;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
  }

  .profile-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-radius: 10px;
    min-width: 220px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.22s;
    z-index: 999;
    overflow: hidden;
    pointer-events: none;
  }

  .profile-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
  }

  #notificationDropdown {
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.22s;
    pointer-events: none;
    display: block !important;
  }

  #notificationDropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
  }

  .dropdown-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e0e8e0;
    background: #f5f7f6;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .dropdown-header-image {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #d4e0d4;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1e9e62 0%, #16a34a 100%);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
  }

  .dropdown-header-text {
    flex: 1;
    min-width: 0;
  }

  .dropdown-header-name {
    font-weight: 600;
    font-size: 12px;
    color: #1a2e1a;
  }

  .dropdown-header-email {
    font-size: 10px;
    color: #7a9a7a;
    margin-top: 2px;
  }

  .dropdown-menu {
    padding: 8px 0;
    display: flex;
    flex-direction: column;
  }

  .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: #3a5a3a;
    text-decoration: none;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s;
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    cursor: pointer;
    font-family: 'Manrope', sans-serif;
  }

  .dropdown-item:hover {
    background: #f5f7f6;
    color: #1e9e62;
  }

  .dropdown-item i {
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
  }

  .dropdown-divider {
    height: 1px;
    background: #e0e8e0;
    margin: 8px 0;
  }

  .dropdown-item.danger {
    color: #d04030;
  }

  .dropdown-item.danger:hover {
    background: rgba(208, 64, 48, 0.08);
    color: #b83828;
  }

  .app {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: row;
    overflow: hidden;
  }

  .left-panel,
  .right-panel {
    width: 320px;
    flex-shrink: 0;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .left-panel {
    border-right: 1px solid #e0e8e0;
  }

  .left-panel.hide {
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    padding: 0 !important;
    border-right: none !important;
  }

  .left-panel.hide .scroll {
    display: none;
  }

  .right-panel {
    border-left: 1px solid #e0e8e0;
  }

  #mapRightPanel {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 340px;
    max-width: 90vw;
    height: 100%;
    background: #ffffff;
    border-left: 1px solid #e0e8e0;
    box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateX(105%);
    transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.32s;
    will-change: transform;
    visibility: hidden;
    pointer-events: none;
  }

  #mapRightPanel.open {
    transform: translateX(0);
    visibility: visible;
    pointer-events: auto;
  }

  #mapRightPanel .scroll {
    width: 100%;
    min-width: 100%;
    box-sizing: border-box;
    flex: 1;
    overflow-y: auto;
    padding: 14px;
  }

  .panel-close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    color: #7a9a7a;
    z-index: 10;
    transition: all 0.15s ease;
  }

  .panel-close-btn:hover {
    background: #eef4ee;
    color: #1a2e1a;
  }

  /* Mobile bottom sheet backdrop */
  .panel-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.38);
    z-index: 1199;
    opacity: 0;
    transition: opacity 0.28s ease;
    pointer-events: none;
  }

  .panel-backdrop.show {
    opacity: 1;
    pointer-events: auto;
  }

  @media (max-width: 780px) {
    .panel-backdrop {
      display: block;
    }
  }

  /* Desktop: header container takes 0 height so close button sits at top-right without taking layout space */
  .panel-header {
    position: relative;
    height: 0;
    overflow: visible;
    padding: 0;
    border: none;
  }

  .panel-drag-bar,
  .panel-header-info {
    display: none;
  }

  .main {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
  }

  .map-wrap {
    flex: 1;
    position: relative;
    overflow: hidden;
  }

  #mainMap,
  #plantMap {
    width: 100%;
    height: 100%;
  }

  .scroll {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
  }

  @media (max-width: 780px) {
    .app {
      flex-direction: column;
    }

    .left-panel,
    .right-panel {
      width: 100%;
      flex: 0 0 auto;
      max-height: 30%;
      border: none;
      border-top: 1px solid #e0e8e0;
      border-bottom: 1px solid #e0e8e0;
      overflow-y: auto;
    }

    .left-panel {
      order: 2;
      border-right: none;
    }

    .main {
      order: 1;
      flex: 1;
      min-height: 0;
    }

    .right-panel {
      order: 3;
      border-left: none;
    }

    #mapRightPanel {
      /* Bottom sheet on mobile — fixed to bottom of screen, around 2 inches initial height */
      position: fixed !important;
      top: auto !important;
      bottom: 0 !important;
      left: 0 !important;
      right: 0 !important;
      width: 100% !important;
      max-width: 100% !important;
      height: auto;
      max-height: 85dvh;
      max-height: 85vh;
      /* fallback */
      border-left: none;
      border-top: 1px solid #d4dfd4;
      border-radius: 20px 20px 0 0;
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.14);
      transform: translateY(105%);
      transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.32s, box-shadow 0.32s;
      z-index: 1200;
      flex-direction: column;
      display: flex;
      visibility: hidden;
      pointer-events: none;
    }

    /* Hide any residual ::before drag handle */
    #mapRightPanel::before {
      display: none;
    }

    /* Mobile: Initial peek popup — adjusted a little higher (~250px from the bottom) */
    #mapRightPanel.open.peek,
    #mapRightPanel.open:not(.expanded) {
      transform: translateY(calc(100% - 355px));
      visibility: visible;
      pointer-events: auto;
      box-shadow: 0 -4px 18px rgba(0, 0, 0, 0.16);
    }

    /* Mobile: Expanded state — only when adjusted upwards */
    #mapRightPanel.open.expanded {
      transform: translateY(0);
      visibility: visible;
      pointer-events: auto;
      box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.22);
    }

    #mapRightPanel .panel-header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px 16px 10px;
      flex-shrink: 0;
      position: relative;
      min-height: 56px;
      height: auto;
      box-sizing: border-box;
      border-bottom: 1px solid #edf2ed;
      background: #ffffff;
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
      touch-action: pan-y;
    }

    #mapRightPanel .panel-drag-bar {
      display: block;
      width: 40px;
      height: 4px;
      background: #c5d5c5;
      border-radius: 2px;
      position: absolute;
      top: 6px;
      left: 50%;
      transform: translateX(-50%);
      transition: background 0.2s, transform 0.2s;
    }

    #mapRightPanel .panel-header:active .panel-drag-bar {
      background: #7a9a7a;
      transform: translateX(-50%) scale(1.08);
    }

    #mapRightPanel .panel-header-info {
      display: flex;
      flex-direction: column;
      justify-content: center;
      flex: 1;
      min-width: 0;
      margin-top: 2px;
      overflow: visible;
    }

    #mapRightPanel .panel-header-title {
      font-size: 13.5px;
      font-weight: 700;
      color: #1b2e1b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.25;
    }

    #mapRightPanel .panel-header-sub {
      font-size: 11px;
      font-weight: 600;
      color: #2e6b3c;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: 2px;
      line-height: 1.2;
      overflow: visible;
    }

    #mapRightPanel .panel-header-sub i {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      width: 1em;
      height: 1em;
      font-size: 13px;
      line-height: 1;
    }

    #mapRightPanel .panel-close-btn {
      position: static;
      margin-left: auto;
      flex-shrink: 0;
      width: 32px;
      height: 32px;
      background: #f0f4f0;
      border-radius: 50%;
      color: #496349;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      border: none;
      cursor: pointer;
    }

    #mapRightPanel .panel-close-btn:active {
      background: #e2ebe2;
      color: #182918;
    }

    #mapRightPanel .scroll {
      flex: 1;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      padding: 10px 14px 20px;
    }

    #v-classify {
      flex-direction: column !important;
    }

    #v-classify .left-panel {
      order: 1 !important;
      max-height: 32% !important;
      border-bottom: 1px solid #e0e8e0;
    }

    #v-classify .main {
      order: 2 !important;
      flex: 1.5 !important;
    }

    #v-classify .right-panel {
      order: 3 !important;
      max-height: 28% !important;
      border-top: 1px solid #e0e8e0;
    }

    .classify-main {
      min-height: 140px;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .classify-bottom {
      max-height: 130px;
      overflow-y: auto;
      padding: 10px 12px;
    }

    .upload-zone {
      padding: 12px 10px !important;
    }

    .upload-zone h3 {
      font-size: 13px !important;
    }

    .upload-zone p {
      font-size: 11px !important;
    }

    .upload-zone .ico {
      font-size: 24px !important;
      margin-bottom: 5px !important;
    }

    .ubtn {
      padding: 5px 12px !important;
      font-size: 11px !important;
      margin-top: 8px !important;
    }

    .tbl th,
    .tbl td {
      font-size: 10px;
      padding: 4px 4px;
    }

    .conf-lbl {
      width: 100px;
      font-size: 11px;
    }

    .header {
      padding: 0 16px;
      height: 56px;
    }

    .app {
      top: 0;
    }

    .profile-toggle {
      padding: 6px;
      gap: 0;
    }

    .header-center {
      display: none;
    }

    .profile-info {
      display: none;
    }

    .nav-dropdown {
      display: block;
    }

    .stat-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 6px;
    }

    .stat-box {
      padding: 8px 10px;
    }

    .stat-num {
      font-size: 22px;
    }
  }

  @media (max-width: 580px) {

    .left-panel,
    .right-panel {
      max-height: 35%;
    }

    #v-classify .left-panel {
      max-height: 35% !important;
    }

    .classify-bottom {
      max-height: 140px;
      padding: 8px;
    }

    .conf-lbl {
      width: 85px;
      font-size: 10px;
    }

    .tbl th,
    .tbl td {
      font-size: 9px;
      padding: 3px 4px;
    }

    .btn,
    .btn-g {
      padding: 4px 7px;
      font-size: 11px;
    }

    .logo span {
      display: inline;
      font-size: inherit;
    }

    .header-right {
      gap: 12px;
    }

    .nav-dropdown {
      font-size: 12px;
      padding: 4px 6px;
      max-width: 110px;
    }

    .draw-btn span {
      display: none;
    }

    .draw-btn {
      padding: 8px 6px;
      min-width: 32px;
    }

    .toolbar-btn-group button {
      padding: 8px 6px;
    }

    .toolbar-save-btn {
      padding: 8px 6px;
    }

    .stat-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 5px;
    }

    .stat-box {
      padding: 7px 8px;
    }

    .stat-num {
      font-size: 19px;
    }

    .stat-lbl {
      font-size: 10px;
    }
  }

  .sec {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #9ab0a0;
    margin-bottom: 9px;
  }

  .stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 7px;
    margin-bottom: 16px;
  }

  .stat-box {
    background: #f7faf7;
    border: 1px solid #e8eee8;
    border-radius: 9px;
    padding: 9px 10px;
  }

  .stat-num {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
  }

  .g {
    color: #1e9e62;
  }

  .r {
    color: #d04030;
  }

  .a {
    color: #c07818;
  }

  .stat-lbl {
    font-size: 11px;
    color: #9ab0a0;
    margin-top: 3px;
    font-weight: 500;
    text-transform: uppercase;
  }

  .div {
    border-top: 1px solid #edf2ed;
    margin: 14px 0;
  }

  .zone-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }

  .zone-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid #edf2ed;
    border-radius: 9px;
    cursor: pointer;
    background: #fff;
    transition: all .15s;
  }

  .zone-row:hover {
    background: #f0faf5;
    border-color: #c0e8d0;
  }

  .zone-row.sel {
    background: #edf7f2;
    border-color: #1e9e62;
  }

  .z-pip {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .z-name {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
  }

  .z-ha {
    font-size: 12px;
    color: #9ab0a0;
    flex-shrink: 0;
  }

  .z-chip {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 100px;
    border: 1px solid;
  }

  .cg {
    background: #edf7f2;
    border-color: #b0e0c0;
    color: #1e9e62;
  }

  .ca {
    background: #fdf5e8;
    border-color: #e8cc98;
    color: #c07818;
  }

  .cr {
    background: #fdf0ee;
    border-color: #e8b8b0;
    color: #d04030;
  }

  .cp {
    background: #fdf5e8;
    border-color: #e8cc98;
    color: #c07818;
  }

  .delineation-rejection-box {
    margin-bottom: 12px;
    padding: 10px 12px;
    background: #fdf0ee;
    border: 1px solid #e8b8b0;
    border-radius: 10px;
    font-size: 12px;
    color: #8a4a42;
  }

  .delineation-rejection-box strong {
    display: block;
    margin-bottom: 4px;
    color: #d04030;
  }

  .d-card {
    background: #f7faf7;
    border: 1px solid #e0e8e0;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 14px;
  }

  .d-title {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 9px;
  }

  .d-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    padding: 5px 0;
    border-bottom: 1px solid #edf2ed;
  }

  .d-key {
    color: #9ab0a0;
  }

  .d-val {
    font-weight: 600;
  }

  .map-top-bar {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 800;
    display: flex;
    gap: 3px;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-radius: 10px;
    padding: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
  }

  .map-layer-control {
    position: absolute;
    top: 120px;
    left: 10px;
    z-index: 1000;
    display: inline-flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-radius: 0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
    overflow: visible;
  }

  .map-layer-control>button {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 34px;
    border: none;
    border-bottom: 1px solid #e0e8e0;
    background: #fff;
    color: #4c6b5d;
    transition: background .15s ease;
  }

  .map-layer-control>button:last-child {
    border-bottom: none;
  }

  .map-layer-control>button:hover {
    background: #f7faf7;
  }

  .layer-option {
    width: 100%;
    min-width: 120px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    color: #4c6b5d;
    padding: 8px 10px;
    border-radius: 9px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
  }

  .map-layer-control .bi {
    font-size: 18px;
  }

  .leaflet-control-locate-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    font-size: 18px;
    color: #4c6b5d;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-top: none;
    text-decoration: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
  }

  .leaflet-control-locate-button:hover {
    background: #f7faf7;
  }

  .layer-menu {
    position: absolute;
    top: 0;
    left: 100%;
    margin-left: 8px;
    display: none;
    flex-direction: column;
    gap: 4px;
    width: max-content;
    min-width: 130px;
    padding: 8px;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-radius: 12px;
    box-shadow: 0 5px 18px rgba(0, 0, 0, .12);
  }

  .layer-menu.show {
    display: flex;
  }

  .layer-option {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    color: #4c6b5d;
    padding: 8px 10px;
    border-radius: 9px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
  }

  .layer-option:hover {
    background: #f4fbf6;
  }

  .layer-option.active {
    background: #1e9e62;
    color: #fff;
  }

  .layer-btn {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    padding: 5px 13px;
    border-radius: 7px;
    border: none;
    background: transparent;
    color: #6a8a6a;
  }

  .layer-btn.active {
    background: #1e9e62;
    color: #fff;
  }

  .delineation-toolbar {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 900;
    display: none;
    gap: 8px;
    align-items: center;
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
  }

  .delineation-toolbar.active {
    display: flex;
  }

  .draw-select-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 0;
    padding: 0;
    border: none;
    background: transparent;
  }

  .draw-mode-group {
    display: inline-flex;
    align-items: center;
    gap: 0;
    border: 1px solid #d4dfd4;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
  }

  .draw-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: #4c6b5d;
    border: none;
    border-radius: 0;
    background: transparent;
    transition: all .15s ease;
    min-width: 0;
    margin: 0;
  }

  .draw-btn:not(:last-child) {
    border-right: 1px solid #d4dfd4;
  }

  .draw-btn:hover {
    background: rgba(30, 158, 98, 0.08);
  }

  .draw-btn.active {
    background: #1e9e62;
    color: #fff;
    border-radius: 0;
  }

  .draw-btn i {
    font-size: 16px;
    line-height: 1;
  }

  .draw-btn.active {
    background: #1e9e62;
    color: #fff;
    border-color: #1e9e62;
  }

  .draw-select {
    display: none;
  }

  .draw-select option {
    color: #1a2e1a;
  }

  .delineation-toolbar button {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    color: #4c6b5d;
    border: 1px solid #e0e8e0;
    border-radius: 6px;
    background: #fff;
    transition: all .15s ease;
  }

  .delineation-toolbar button:hover {
    background: #f7faf7;
    border-color: #d0d8d0;
  }

  .delineation-toolbar button.active {
    background: #1e9e62;
    color: #fff;
    border-color: #1e9e62;
  }

  .line-point,
  .area-point {
    color: #ff6b6b;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    line-height: 8px;
  }

  .area-point {
    color: #4ecdc4;
  }

  .delineation-separator {
    width: 1px;
    height: 24px;
    background: #e0e8e0;
    margin: 0 4px;
  }

  .delineation-toolbar .btn-add {
    background: #1e9e62;
    color: #fff;
    border-color: #1e9e62;
  }

  .delineation-toolbar .btn-add:hover {
    background: #16a34a;
  }

  .toolbar-right {
    margin-left: auto;
    display: inline-flex;
    gap: 8px;
    align-items: center;
  }

  .toolbar-btn-group {
    display: inline-flex;
    gap: 0;
    align-items: center;
    border: 1px solid #d4dfd4;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
  }

  .toolbar-btn-group button {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #4c6b5d;
    border: none;
    border-radius: 0;
    background: transparent;
    transition: all .15s ease;
    margin: 0;
  }

  .toolbar-btn-group button:not(:last-child) {
    border-right: 1px solid #d4dfd4;
  }

  .toolbar-btn-group button:hover {
    background: rgba(30, 158, 98, 0.08);
  }

  .toolbar-btn-group button.active {
    background: #1e9e62;
    color: #fff;
  }

  .toolbar-save-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 12px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #4c6b5d;
    border: 1px solid #d4dfd4;
    border-radius: 10px;
    background: #fff;
    transition: all .15s ease;
  }

  .toolbar-save-btn:hover {
    background: rgba(30, 158, 98, 0.08);
  }

  .toolbar-save-btn.active {
    background: #1e9e62;
    color: #fff;
    border-color: #1e9e62;
  }

  .toolbar-save-btn:disabled,
  .toolbar-save-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
    background: #f5f7f5;
    color: #a0b0a5;
    border-color: #e0e8e0;
  }

  .map-legend-float {
    position: absolute;
    bottom: 20px;
    left: 12px;
    z-index: 800;
    background: #fff;
    border: 1px solid #e0e8e0;
    border-radius: 10px;
    padding: 10px 13px;
  }

  .leg-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #9ab0a0;
    margin-bottom: 7px;
  }

  .leg-row {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 13px;
    margin: 3px 0;
  }

  .leg-dot {
    width: 13px;
    height: 13px;
    border-radius: 3px;
  }

  /* Map Temporal Timeline Slider */
  .map-timeline-control {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 850;
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(212, 223, 212, 0.9);
    border-radius: 16px;
    box-shadow: 0 14px 35px rgba(15, 30, 20, 0.16), 0 2px 8px rgba(0, 0, 0, 0.05);
    padding: 12px 18px;
    min-width: 520px;
    max-width: 92vw;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    user-select: none;
  }

  .map-timeline-control.collapsed {
    min-width: auto;
    padding: 7px 16px;
    border-radius: 999px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    cursor: pointer;
  }

  .map-timeline-control.collapsed .timeline-body {
    display: none;
  }

  .map-timeline-control.collapsed .timeline-header {
    margin-bottom: 0;
  }

  .map-timeline-control.collapsed .timeline-collapse-btn i {
    transform: rotate(180deg);
  }

  .timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    gap: 12px;
  }

  .timeline-title-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .timeline-title-wrap i {
    color: #1e9e62;
    font-size: 15px;
  }

  .timeline-title {
    font-size: 13px;
    font-weight: 700;
    color: #1a2e1a;
    letter-spacing: -0.2px;
  }

  .timeline-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: #edf7f2;
    border: 1px solid #bce6cf;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: #1e9e62;
    transition: all 0.2s ease;
  }

  .timeline-badge.badge-negative {
    background: #fdf0ee;
    border-color: #f5c2bc;
    color: #d04030;
  }

  .timeline-collapse-btn {
    all: unset;
    cursor: pointer;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6a8a6a;
    transition: background 0.15s, color 0.15s;
  }

  .timeline-collapse-btn:hover {
    background: #eef4ee;
    color: #1a2e1a;
  }

  .timeline-collapse-btn i {
    transition: transform 0.2s ease;
    font-size: 13px;
  }

  .timeline-controls {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 10px;
  }

  .timeline-play-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: #1e9e62;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(30, 158, 98, 0.35);
    transition: transform 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    flex-shrink: 0;
  }

  .timeline-play-btn:hover {
    background: #168652;
    transform: scale(1.06);
    box-shadow: 0 6px 14px rgba(30, 158, 98, 0.45);
  }

  .timeline-play-btn.playing {
    background: #c07818;
    box-shadow: 0 4px 12px rgba(192, 120, 24, 0.4);
  }

  .timeline-slider-track-wrap {
    flex: 1;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .timeline-range-input {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: #d4dfd4;
    outline: none;
    margin: 8px 0 6px 0;
    position: relative;
    z-index: 2;
    cursor: pointer;
  }

  .timeline-range-input::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #1e9e62;
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
  }

  .timeline-range-input::-webkit-slider-thumb:hover {
    transform: scale(1.2);
    box-shadow: 0 3px 10px rgba(30, 158, 98, 0.45);
  }

  .timeline-range-input::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #1e9e62;
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    cursor: pointer;
  }

  .timeline-marks {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-top: 2px;
    padding: 0 2px;
  }

  .timeline-mark {
    font-size: 11px;
    font-weight: 600;
    color: #7a9a7a;
    cursor: pointer;
    transition: color 0.15s ease, transform 0.15s ease;
    position: relative;
  }

  .timeline-mark:hover {
    color: #1e9e62;
    transform: translateY(-1px);
  }

  .timeline-mark.active {
    color: #1e9e62;
    font-weight: 800;
  }

  .timeline-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f4f8f4;
    border-radius: 10px;
    padding: 7px 12px;
    gap: 12px;
    font-size: 11px;
    color: #556b56;
  }

  .timeline-stat {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
  }

  .timeline-stat strong {
    color: #1a2e1a;
    font-weight: 700;
  }

  .timeline-stat strong.text-green {
    color: #1e9e62;
  }

  .map-layer-control>button.active-toggle {
    background: #edf7f2;
    color: #1e9e62;
  }

  @media (max-width: 780px) {

    /* Relocate map legend to top-right on mobile so bottom is reserved for the timeline slider */
    .map-legend-float {
      bottom: auto;
      top: 120px;
      right: 10px;
      left: auto;
      padding: 6px 10px;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      max-width: 120px;
    }

    .map-legend-float .leg-title {
      font-size: 10px;
      margin-bottom: 4px;
    }

    .map-legend-float .leg-row {
      font-size: 11px;
      margin: 2px 0;
      gap: 5px;
    }

    .map-legend-float .leg-dot {
      width: 10px;
      height: 10px;
    }

    /* Mobile Timeline Slider Control */
    .map-timeline-control {
      min-width: unset;
      width: calc(100% - 20px);
      left: 10px;
      transform: none;
      bottom: 14px;
      padding: 10px 12px;
      border-radius: 14px;
      box-shadow: 0 8px 24px rgba(15, 30, 20, 0.18);
      transition: bottom 0.32s cubic-bezier(0.16, 1, 0.3, 1), transform 0.32s, opacity 0.25s, visibility 0.25s, width 0.3s ease, left 0.3s ease, padding 0.3s ease, border-radius 0.3s ease;
    }

    /* Sleek Floating Pill when Collapsed on Mobile */
    .map-timeline-control.collapsed {
      width: auto !important;
      max-width: calc(100vw - 32px) !important;
      left: 50% !important;
      transform: translateX(-50%) !important;
      bottom: 14px !important;
      padding: 6px 14px !important;
      border-radius: 999px !important;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2) !important;
      overflow: hidden;
    }

    .map-timeline-control.collapsed .timeline-title-wrap {
      flex-wrap: nowrap !important;
      overflow: hidden;
    }

    .map-timeline-control.collapsed .timeline-title {
      font-size: 12px;
      white-space: nowrap;
    }

    .map-timeline-control.collapsed .timeline-badge {
      font-size: 10px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .map-timeline-control.collapsed .timeline-zone-focus-btn {
      display: none !important;
    }

    .timeline-header {
      margin-bottom: 6px;
      gap: 6px;
    }

    .timeline-title-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
      min-width: 0;
    }

    .timeline-title {
      font-size: 12px;
      font-weight: 700;
    }

    .timeline-badge {
      font-size: 10px;
      padding: 1px 6px;
    }

    .timeline-zone-focus-btn {
      padding: 2px 7px;
      font-size: 10px;
      gap: 3px;
      border-radius: 6px;
    }

    .timeline-collapse-btn {
      width: 26px;
      height: 26px;
    }

    .timeline-controls {
      gap: 10px;
      margin-bottom: 8px;
    }

    .timeline-play-btn {
      width: 34px;
      height: 34px;
      font-size: 15px;
      flex-shrink: 0;
    }

    .timeline-range-input {
      height: 8px;
      margin: 6px 0 4px 0;
      touch-action: pan-x;
    }

    .timeline-range-input::-webkit-slider-thumb {
      width: 20px;
      height: 20px;
    }

    .timeline-range-input::-moz-range-thumb {
      width: 20px;
      height: 20px;
    }

    .timeline-marks {
      padding: 0;
      margin-top: 2px;
    }

    .timeline-mark {
      font-size: 10px;
      padding: 2px 3px;
      touch-action: manipulation;
    }

    /* Compact single-row info bar on mobile to preserve vertical map height */
    .timeline-info-row {
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      padding: 5px 8px;
      font-size: 10px;
      gap: 4px;
      border-radius: 8px;
    }

    .timeline-stat {
      gap: 3px;
      font-size: 10px;
      white-space: nowrap;
    }

    .timeline-stat strong {
      font-size: 11px;
    }

    /* When bottom sheet is opened in peek mode (~355px), lift timeline cleanly above sheet */
    .map-timeline-control.above-peek {
      bottom: 365px !important;
    }

    .map-timeline-control.above-peek.collapsed {
      bottom: 365px !important;
    }

    /* When bottom sheet is expanded to full height, hide timeline so it doesn't obstruct */
    .map-timeline-control.hidden-expanded {
      opacity: 0 !important;
      pointer-events: none !important;
      visibility: hidden !important;
    }

    /* Temporal breakdown stepper cards inside mobile drawer */
    .temporal-epoch-row {
      padding: 6px 8px;
      gap: 8px;
    }

    .epoch-year-badge {
      font-size: 11px;
      padding: 2px 6px;
    }

    .epoch-headline {
      font-size: 11px;
    }

    .epoch-sub {
      font-size: 10px;
    }
  }

  /* Small mobile screens (<= 480px) */
  @media (max-width: 480px) {
    .timeline-title-wrap {
      gap: 4px;
    }

    .timeline-title {
      font-size: 11px;
    }

    .timeline-badge {
      font-size: 9px;
      padding: 1px 4px;
    }

    .timeline-zone-focus-btn {
      padding: 2px 5px;
      font-size: 9px;
    }

    .timeline-mark {
      font-size: 9px;
      padding: 1px 2px;
    }

    .timeline-info-row {
      font-size: 9px;
      padding: 4px 6px;
    }

    .timeline-stat strong {
      font-size: 10px;
    }

    .map-timeline-control.collapsed .timeline-badge {
      display: none !important;
    }

    .map-timeline-control.collapsed .timeline-title {
      font-size: 11px;
    }
  }

  .timeline-zone-focus-btn {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #1e9e62;
    background: #edf7f2;
    border: 1px solid #cbe9d8;
    transition: all 0.15s ease;
  }

  .timeline-zone-focus-btn:hover {
    background: #1e9e62;
    color: #fff;
  }

  .temporal-epoch-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
  }

  .temporal-epoch-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    background: #f8faf8;
    border: 1px solid #e0e8e0;
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .temporal-epoch-row:hover {
    background: #edf7f2;
    border-color: #bce6cf;
  }

  .temporal-epoch-row.active {
    background: #edf7f2;
    border-color: #1e9e62;
    box-shadow: 0 2px 6px rgba(30, 158, 98, 0.15);
  }

  .epoch-year-badge {
    font-weight: 800;
    font-size: 12px;
    padding: 3px 7px;
    border-radius: 6px;
    background: #fff;
    color: #1e9e62;
    border: 1px solid #bce6cf;
  }

  .temporal-epoch-row.active .epoch-year-badge {
    background: #1e9e62;
    color: #fff;
  }

  .epoch-content {
    flex: 1;
    min-width: 0;
  }

  .epoch-headline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 700;
    color: #1a2e1a;
  }

  .epoch-sub {
    font-size: 11px;
    color: #6a8a6a;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }



  .tag {
    display: inline-block;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 100px;
    border: 1px solid;
    margin: 2px;
  }

  .tg {
    background: #edf7f2;
    border-color: #b0e0c0;
    color: #1e9e62;
  }

  .ta {
    background: #fdf5e8;
    border-color: #e8cc98;
    color: #c07818;
  }

  .tb {
    background: #eef4ff;
    border-color: #b0c8f0;
    color: #3060b0;
  }

  .tbl {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }

  .tbl th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #9ab0a0;
    padding: 5px 8px;
    border-bottom: 1px solid #e0e8e0;
    text-align: left;
  }

  .tbl td {
    padding: 7px 8px;
    border-bottom: 1px solid #f0f4f0;
    color: #3a5a3a;
  }

  .mbar {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .mtrack {
    width: 48px;
    height: 3px;
    background: #e0e8e0;
    border-radius: 2px;
  }

  .mfill {
    height: 100%;
    border-radius: 2px;
  }

  .site-card {
    background: #f7faf7;
    border: 1px solid #e0e8e0;
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
  }

  .site-card:hover {
    border-color: #1e9e62;
    background: #edf7f2;
  }

  .sc-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
  }

  .sc-rank {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
  }

  .rg {
    background: #edf7f2;
    color: #1e9e62;
    border: 1px solid #b0e0c0;
  }

  .ra {
    background: #fdf5e8;
    color: #c07818;
    border: 1px solid #e8cc98;
  }

  .rb {
    background: #eef4ff;
    color: #3060b0;
    border: 1px solid #b0c8f0;
  }

  .sc-name {
    font-size: 14px;
    font-weight: 600;
  }

  .sc-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .sbar {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .strack {
    width: 52px;
    height: 4px;
    background: #e0e8e0;
    border-radius: 2px;
  }

  .sfill {
    height: 100%;
    border-radius: 2px;
  }

  .ptag {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 100px;
    border: 1px solid;
  }

  .factor-row {
    margin-bottom: 9px;
  }

  .factor-head {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 4px;
  }

  .factor-bar {
    height: 4px;
    background: #e0e8e0;
    border-radius: 2px;
  }

  .factor-fill {
    height: 4px;
    border-radius: 2px;
  }

  .view {
    display: none;
    overflow: hidden;
  }

  .view.on {
    display: flex;
    flex-direction: row;
    width: 100%;
    height: 100%;
    flex: 1;
    position: relative;
  }

  @media (max-width: 780px) {
    .view.on {
      flex-direction: column;
    }
  }

  .cw {
    position: relative;
    width: 100%;
  }

  .input-error {
    border-color: #dc2626 !important;
    box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
  }

  /* Custom scrollbar for notes and narrow scrollable containers */
  #delineationNotes {
    scrollbar-width: thin;
    scrollbar-color: #9ca3af #f0f4f0;
  }

  #delineationNotes::-webkit-scrollbar {
    width: 10px;
  }

  #delineationNotes::-webkit-scrollbar-track {
    background: #f0f4f0;
    border-radius: 999px;
  }

  #delineationNotes::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 999px;
    border: 2px solid #f0f4f0;
  }

  #delineationNotes::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
  }

  #delineationNotes::-webkit-scrollbar-button {
    display: none;
    height: 0;
    width: 0;
  }

  /* Processing Modal */
  .processing-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    opacity: 0;
    transition: opacity 0.25s ease;
  }

  .processing-modal-backdrop.active {
    display: flex;
    opacity: 1;
  }

  .processing-modal-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 32px 28px;
    text-align: center;
    max-width: 320px;
    width: 90%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.05);
    transform: scale(0.95);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .processing-modal-backdrop.active .processing-modal-card {
    transform: scale(1);
  }

  .processing-spinner {
    width: 44px;
    height: 44px;
    margin: 0 auto 16px;
    border: 3.5px solid #edf2f7;
    border-top-color: #1e9e62;
    border-radius: 50%;
    animation: processingSpin 0.75s linear infinite;
  }

  @keyframes processingSpin {
    to {
      transform: rotate(360deg);
    }
  }

  .processing-modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #1a2e1a;
    margin-bottom: 6px;
  }

  .processing-modal-subtitle {
    font-size: 13px;
    color: #64748b;
    margin: 0;
    line-height: 1.4;
  }

  .success-icon-wrapper {
    width: 52px;
    height: 52px;
    margin: 0 auto 16px;
    background: #edf7f2;
    border: 2px solid #c8e6d4;
    color: #1e9e62;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  }

  @keyframes popIn {
    0% {
      transform: scale(0.6);
      opacity: 0;
    }

    100% {
      transform: scale(1);
      opacity: 1;
    }
  }

  .success-modal-btn {
    margin-top: 18px;
    width: 100%;
    justify-content: center;
    padding: 10px 16px;
    font-weight: 700;
    border-radius: 10px;
  }


  .content.content-flush {
    padding: 0 !important;
    overflow: hidden;
  }

  .enduser-workspace {
    position: relative;
    width: 100%;
    height: 100%;
  }

  body[data-page='map'] #delineationToolbar,
  body[data-page='map'] #editModeBtn,
  body[data-page='map'] #classifyBtn,
  body[data-page='map'] .left-panel {
    display: none !important;
  }

  body[data-page='delineate'] #mapTimelineControl,
  body[data-page='delineate'] #timelineToggleBtn,
  body[data-page='delineate'] #classifyBtn,
  body[data-page='delineate'] #editModeBtn,
  body[data-page='delineate'] .left-panel {
    display: none !important;
  }

  body[data-page='delineate'] #delineationToolbar {
    display: flex !important;
  }
</style>