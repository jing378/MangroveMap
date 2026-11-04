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
    background: var(--bg-f0f4f0, #f0f4f0);
    color: var(--fg-1a2e1a, #1a2e1a);
    overflow: hidden;
  }

  .header {
    height: 60px;
    background: var(--bg-ffffff, #fff);
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    color: var(--fg-1a2e1a, #1a2e1a);
    white-space: nowrap;
  }

  .logo-mark img {
    width: 100%;
    height: 100%;
    display: block;
    border-radius: inherit;
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
    color: var(--fg-6a8a6a, #6a8a6a);
    transition: all .15s;
    border: none;
    background: none;
    font-family: 'Manrope', sans-serif;
  }

  .h-tab.active {
    color: var(--fg-1e9e62, #1e9e62);
    background: var(--bg-edf7f2, #edf7f2);
    font-weight: 600;
  }

  .nav-dropdown {
    display: none;
    font-size: 14px;
    padding: 5px 10px;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 8px;
    background: var(--bg-ffffff, #fff);
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
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    background: var(--bg-ffffff, #fff);
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
    background: var(--bg-f5f7f6, #f5f7f6);
  }

  .profile-image {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--bd-e0e8e0, #e0e8e0);
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
    color: var(--fg-1a2e1a, #1a2e1a);
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
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    background: var(--bg-f5f7f6, #f5f7f6);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .dropdown-header-image {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--bd-d4e0d4, #d4e0d4);
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
    color: var(--fg-1a2e1a, #1a2e1a);
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
    color: var(--fg-3a5a3a, #3a5a3a);
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
    background: var(--bg-f5f7f6, #f5f7f6);
    color: var(--fg-1e9e62, #1e9e62);
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
    background: var(--bg-e0e8e0, #e0e8e0);
    margin: 8px 0;
  }

  .dropdown-item.danger {
    color: var(--fg-d04030, #d04030);
  }

  .dropdown-item.danger:hover {
    background: rgba(208, 64, 48, 0.08);
    color: var(--fg-b83828, #b83828);
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
    background: var(--bg-ffffff, #fff);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .left-panel {
    border-right: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    border-left: 1px solid var(--bd-e0e8e0, #e0e8e0);
  }

  #mapRightPanel {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 340px;
    max-width: 90vw;
    height: 100%;
    background: var(--bg-ffffff, #ffffff);
    border-left: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    min-height: 0;
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
    background: var(--bg-fff1f0, #fff1f0);
    border: 1px solid var(--bd-f2c2bd, #f2c2bd);
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    color: var(--fg-c0392b, #c0392b);
    z-index: 10;
    transition: all 0.15s ease;
  }

  .panel-close-btn:hover {
    background: #d04030;
    border-color: #d04030;
    color: #ffffff;
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

  .panel-header {
    position: relative;
    display: flex;
    align-items: center;
    flex-shrink: 0;
    min-height: 56px;
    box-sizing: border-box;
    padding: 12px 52px 12px 16px;
    border-bottom: 1px solid var(--bd-edf2ed, #edf2ed);
    background: var(--bg-ffffff, #ffffff);
  }

  .panel-drag-bar {
    display: none;
  }

  .panel-header-info {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 0;
  }

  .panel-header-title {
    overflow: hidden;
    color: var(--fg-1b2e1b, #1b2e1b);
    font-size: 14px;
    font-weight: 700;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .panel-header-sub {
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

  .map-quick-jump {
    position: absolute;
    top: 12px;
    left: 50%;
    z-index: 1000;
    width: min(390px, calc(100% - 24px));
    transform: translateX(-50%);
    font-family: 'Manrope', sans-serif;
  }

  @media (max-width: 780px) {
    .map-quick-jump {
      right: auto;
      left: calc(50% + 24px);
      width: min(320px, calc(100% - 76px));
      transform: translateX(-50%);
    }

    .map-quick-jump .quick-jump-field {
      gap: 8px;
      min-height: 40px;
      padding: 0 11px;
    }

    .map-quick-jump .quick-jump-field > i {
      font-size: 15px;
    }

    .map-quick-jump .quick-jump-field input {
      padding: 7px 0;
      font-size: 12px;
    }
  }

  .quick-jump-field {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 44px;
    padding: 0 14px;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 8px;
    background: var(--bg-ffffff, #fff);
    box-shadow: 0 2px 12px rgba(0, 0, 0, .14);
  }

  .quick-jump-field > i {
    color: var(--fg-4c6b5d, #4c6b5d);
    font-size: 16px;
  }

  .quick-jump-field input {
    width: 100%;
    min-width: 0;
    padding: 10px 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--fg-182918, #182918);
    font: inherit;
    font-size: 13px;
  }

  .quick-jump-field input::placeholder {
    color: var(--fg-718271, #718271);
  }

  .quick-jump-results {
    max-height: min(320px, 50vh);
    margin-top: 6px;
    overflow-y: auto;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 8px;
    background: var(--bg-ffffff, #fff);
    box-shadow: 0 4px 16px rgba(0, 0, 0, .16);
  }

  .quick-jump-result {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 3px;
    padding: 10px 14px;
    border: 0;
    border-bottom: 1px solid var(--bd-edf1ed, #edf1ed);
    background: var(--bg-ffffff, #fff);
    color: var(--fg-182918, #182918);
    cursor: pointer;
    font: inherit;
    text-align: left;
  }

  .quick-jump-result:last-child {
    border-bottom: 0;
  }

  .quick-jump-result:hover,
  .quick-jump-result:focus-visible {
    outline: 0;
    background: var(--bg-f1f7f2, #f1f7f2);
  }

  .quick-jump-result-name {
    overflow: hidden;
    font-size: 13px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .quick-jump-result-heading {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 7px;
  }

  .quick-jump-result-heading > i {
    flex: 0 0 auto;
    color: var(--fg-36835b, #36835b);
    font-size: 13px;
  }

  .quick-jump-result-meta,
  .quick-jump-empty {
    color: var(--fg-718271, #718271);
    font-size: 11px;
  }

  .quick-jump-empty {
    padding: 12px 14px;
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
      border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
      border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
      height: min(85vh, calc(100vh - 76px));
      max-height: min(85vh, calc(100vh - 76px));
      border-left: none;
      border-top: 1px solid var(--bd-d4dfd4, #d4dfd4);
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

    @supports (height: 100dvh) {
      #mapRightPanel {
        height: min(85dvh, calc(100dvh - 76px));
        max-height: min(85dvh, calc(100dvh - 76px));
      }

      #mapRightPanel.open.peek,
      #mapRightPanel.open:not(.expanded) {
        transform: translateY(calc(100% - min(355px, 45dvh)));
      }
    }

    /* Hide any residual ::before drag handle */
    #mapRightPanel::before {
      display: none;
    }

    /* Mobile: Initial peek popup — adjusted a little higher (~250px from the bottom) */
    #mapRightPanel.open.peek,
    #mapRightPanel.open:not(.expanded) {
      transform: translateY(calc(100% - min(355px, 45vh)));
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
      border-bottom: 1px solid var(--bd-edf2ed, #edf2ed);
      background: var(--bg-ffffff, #ffffff);
      cursor: pointer;
      user-select: none;
      -webkit-user-select: none;
      touch-action: pan-y;
    }

    #mapRightPanel .panel-drag-bar {
      display: block;
      width: 40px;
      height: 4px;
      background: var(--bg-c5d5c5, #c5d5c5);
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
      color: var(--fg-1b2e1b, #1b2e1b);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      line-height: 1.25;
    }

    #mapRightPanel .panel-header-sub {
      font-size: 11px;
      font-weight: 600;
      color: var(--fg-2e6b3c, #2e6b3c);
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
      background: var(--bg-fff1f0, #fff1f0);
      border-radius: 50%;
      color: var(--fg-c0392b, #c0392b);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      border: 1px solid var(--bd-f2c2bd, #f2c2bd);
      cursor: pointer;
    }

    #mapRightPanel .panel-close-btn:active {
      background: #d04030;
      border-color: #d04030;
      color: #ffffff;
    }

    #mapRightPanel .scroll {
      min-height: 0;
      flex: 1;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
      padding: 10px 14px 20px;
    }

    #mapRightPanel .delineation-save-btn {
      position: sticky;
      bottom: -10px;
      z-index: 2;
      margin-top: 8px;
      padding: 10px;
      background: #1e9e62;
      box-shadow: 0 -6px 12px rgba(var(--bd-f7faf7-rgb, 247, 250, 247), 0.95);
    }

    /* A sticky button only floats within its parent. These wrappers hold just a few buttons, so let their
       buttons belong to the scrolling panel instead: Resubmit / Save Changes then float like Save. */
    #mapRightPanel #rejectedRevisionActions,
    #mapRightPanel #boundaryEditActions {
      display: contents;
    }

    #mapRightPanel #cancelRejectedRevisionBtn,
    #mapRightPanel #cancelBoundaryEditBtn {
      margin-bottom: 12px !important;
    }

    #v-classify {
      flex-direction: column !important;
    }

    #v-classify .left-panel {
      order: 1 !important;
      max-height: 32% !important;
      border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    }

    #v-classify .main {
      order: 2 !important;
      flex: 1.5 !important;
    }

    #v-classify .right-panel {
      order: 3 !important;
      max-height: 28% !important;
      border-top: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    background: var(--bg-f7faf7, #f7faf7);
    border: 1px solid var(--bd-e8eee8, #e8eee8);
    border-radius: 9px;
    padding: 9px 10px;
  }

  .stat-num {
    font-size: 24px;
    font-weight: 700;
    line-height: 1;
  }

  .g {
    color: var(--fg-1e9e62, #1e9e62);
  }

  .r {
    color: var(--fg-d04030, #d04030);
  }

  .a {
    color: var(--fg-c07818, #c07818);
  }

  .stat-lbl {
    font-size: 11px;
    color: #9ab0a0;
    margin-top: 3px;
    font-weight: 500;
    text-transform: uppercase;
  }

  .div {
    border-top: 1px solid var(--bd-edf2ed, #edf2ed);
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
    border: 1px solid var(--bd-edf2ed, #edf2ed);
    border-radius: 9px;
    cursor: pointer;
    background: var(--bg-ffffff, #fff);
    transition: all .15s;
  }

  .zone-row:hover {
    background: var(--bg-f0faf5, #f0faf5);
    border-color: var(--bd-c0e8d0, #c0e8d0);
  }

  .zone-row.sel {
    background: var(--bg-edf7f2, #edf7f2);
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
    background: var(--bg-edf7f2, #edf7f2);
    border-color: var(--bd-b0e0c0, #b0e0c0);
    color: var(--fg-1e9e62, #1e9e62);
  }

  .ca {
    background: var(--bg-fdf5e8, #fdf5e8);
    border-color: var(--bd-e8cc98, #e8cc98);
    color: var(--fg-c07818, #c07818);
  }

  .cr {
    background: var(--bg-fdf0ee, #fdf0ee);
    border-color: var(--bd-e8b8b0, #e8b8b0);
    color: var(--fg-d04030, #d04030);
  }

  .cp {
    background: var(--bg-fdf5e8, #fdf5e8);
    border-color: var(--bd-e8cc98, #e8cc98);
    color: var(--fg-c07818, #c07818);
  }

  .delineation-rejection-box {
    margin-bottom: 12px;
    padding: 10px 12px;
    background: var(--bg-fdf0ee, #fdf0ee);
    border: 1px solid var(--bd-e8b8b0, #e8b8b0);
    border-radius: 10px;
    font-size: 12px;
    color: var(--fg-8a4a42, #8a4a42);
  }

  .delineation-rejection-box strong {
    display: block;
    margin-bottom: 4px;
    color: var(--fg-d04030, #d04030);
  }

  .d-card {
    background: var(--bg-f7faf7, #f7faf7);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    align-items: flex-start;
    gap: 12px;
    font-size: 13px;
    padding: 5px 0;
    border-bottom: 1px solid var(--bd-edf2ed, #edf2ed);
  }

  .d-row-genera {
    flex-direction: column;
    gap: 4px;
  }

  .d-row-genera .d-val {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    text-align: left;
  }

  .d-row-recommendation {
    flex-direction: column;
    gap: 4px;
  }

  .d-row-recommendation .d-val {
    width: 100%;
    text-align: left;
  }

  .planting-rec-entry {
    background: var(--bg-f7faf7, #f7faf7);
    border: 1px solid var(--bd-e3ece3, #e3ece3);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 12px;
    white-space: normal;
  }

  .planting-rec-entry + .planting-rec-entry {
    margin-top: 6px;
  }

  /* Location pin, same as the landing page (.map-pin in index.blade.php). --map-pin-color carries
     the review status (approved green, pending orange) or the recommendation's suitability. */
  .map-pin {
    position: relative;
    display: block;
    width: 30px;
    height: 30px;
    filter: drop-shadow(0 2px 3px rgba(0, 0, 0, 0.35));
    transition: transform 0.15s ease;
  }

  .map-pin:hover {
    transform: translateY(-2px) scale(1.08);
  }

  .map-pin i {
    font-size: 30px;
    line-height: 1;
    color: var(--map-pin-color, var(--fg-1e9e62, #1e9e62));
    -webkit-text-stroke: 1px var(--bg-ffffff, #fff);
  }

  .planting-rec-marker {
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    background: var(--planting-rec-color);
    border: 2px solid var(--bd-ffffff, #fff);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .35);
    font-size: 13px;
  }

  .planting-rec-popup {
    font-size: 12px;
    min-width: 200px;
  }

  .d-key {
    color: #9ab0a0;
    flex-shrink: 0;
  }

  .d-val {
    font-weight: 600;
    text-align: right;
    word-break: break-word;
  }

  .genus-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 100px;
    background: var(--bg-edf7f2, #edf7f2);
    border: 1px solid var(--bd-b0e0c0, #b0e0c0);
    color: var(--fg-1e9e62, #1e9e62);
    line-height: 1.2;
    margin: 2px 1px;
  }

  .genus-local-name {
    font-weight: 400;
    opacity: 0.75;
    font-style: italic;
    font-size: 0.92em;
  }

  .genus-tag-remove {
    cursor: pointer;
    font-size: 12px;
    margin-left: 2px;
    opacity: 0.7;
    display: inline-flex;
    align-items: center;
  }

  .genus-tag-remove:hover {
    opacity: 1;
    color: var(--fg-d04030, #d04030);
  }

  .genus-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 9px;
    border-radius: 100px;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    background: var(--bg-f8faf7, #f8faf7);
    color: var(--fg-445944, #445944);
    cursor: pointer;
    transition: all 0.15s ease;
    user-select: none;
  }

  .genus-pill-btn:hover {
    border-color: #1e9e62;
    background: var(--bg-f0f7f2, #f0f7f2);
    color: var(--fg-1e9e62, #1e9e62);
  }

  .genus-pill-btn.active {
    background: #1e9e62;
    border-color: #1e9e62;
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(30, 158, 98, 0.25);
  }

  .map-top-bar {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 800;
    display: flex;
    gap: 3px;
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    background: var(--bg-ffffff, #fff);
    color: var(--fg-4c6b5d, #4c6b5d);
    transition: background .15s ease;
  }

  .map-layer-control>button:last-child {
    border-bottom: none;
  }

  .map-layer-control>button:hover {
    background: var(--bg-f7faf7, #f7faf7);
  }

  .layer-option {
    width: 100%;
    min-width: 120px;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    color: var(--fg-4c6b5d, #4c6b5d);
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
    color: var(--fg-4c6b5d, #4c6b5d);
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-top: none;
    text-decoration: none;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
  }

  .leaflet-control-locate-button:hover {
    background: var(--bg-f7faf7, #f7faf7);
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
    min-width: 260px;
    max-width: min(340px, calc(100vw - 80px));
    max-height: min(70vh, 560px);
    overflow-y: auto;
    padding: 8px;
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 12px;
    box-shadow: 0 5px 18px rgba(0, 0, 0, .12);
  }

  .layer-menu.show {
    display: flex;
  }

  .drone-imagery-menu {
    top: 38px;
  }

  .drone-imagery-panel {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 4px 2px 2px;
    color: var(--fg-4c6b5d, #4c6b5d);
    font-family: 'Manrope', sans-serif;
  }

  .drone-imagery-panel h3 {
    margin: 0;
    color: var(--fg-1a2e1a, #1a2e1a);
    font-size: 13px;
    font-weight: 700;
  }

  .drone-imagery-panel form {
    display: grid;
    gap: 6px;
  }

  .drone-imagery-panel label {
    font-size: 11px;
    font-weight: 600;
  }

  .drone-imagery-panel input[type="text"],
  .drone-imagery-panel input[type="file"] {
    width: 100%;
    min-width: 0;
    padding: 7px;
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 7px;
    background: var(--bg-ffffff, #fff);
    color: var(--fg-334b3c, #334b3c);
    font: 12px 'Manrope', sans-serif;
  }

  .drone-imagery-panel input[type="file"] {
    font-size: 11px;
  }

  .drone-imagery-upload-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 10px;
    border: 0;
    border-radius: 7px;
    background: #1e9e62;
    color: #fff;
    font: 600 12px 'Manrope', sans-serif;
    text-decoration: none;
    cursor: pointer;
  }

  .drone-imagery-upload-button:hover {
    background: #16824f;
    color: #fff;
  }

  .drone-imagery-message {
    font-size: 11px;
    line-height: 1.4;
    overflow-wrap: anywhere;
  }

  .drone-imagery-message:empty {
    display: none;
  }

  .drone-imagery-layer {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    padding: 7px 0;
    border-top: 1px solid var(--bd-edf1ed, #edf1ed);
    font-size: 11px;
  }

  .drone-imagery-layer input {
    margin: 2px 0 0;
    accent-color: #1e9e62;
  }

  .drone-imagery-layer-info {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  .drone-imagery-layer-name {
    color: var(--fg-263d2e, #263d2e);
    font-weight: 600;
    overflow-wrap: anywhere;
  }

  .drone-imagery-layer-state {
    color: var(--fg-73877a, #73877a);
    overflow-wrap: anywhere;
  }

  .drone-imagery-layer-remove {
    flex: 0 0 auto;
    padding: 4px 7px;
    border: 1px solid var(--bd-e8c2bd, #e8c2bd);
    border-radius: 6px;
    background: var(--bg-fff7f6, #fff7f6);
    color: var(--fg-a63b30, #a63b30);
    cursor: pointer;
    font: inherit;
    font-size: 10px;
  }

  .drone-imagery-layer-remove:hover:not(:disabled) {
    background: var(--bg-fdf0ee, #fdf0ee);
  }

  .drone-imagery-layer-remove:disabled {
    cursor: not-allowed;
    opacity: .55;
  }

  /* Floating upload/processing progress beside the map layer control. */
  .drone-progress-chip {
    position: absolute;
    top: 38px;
    left: calc(100% + 8px);
    display: flex;
    align-items: center;
    gap: 9px;
    width: 250px;
    padding: 8px 11px;
    border: 1px solid var(--bd-d6e6dc, #d6e6dc);
    border-radius: 10px;
    background: var(--bg-ffffff, #fff);
    box-shadow: 0 4px 14px rgba(0, 0, 0, .14);
    color: var(--fg-24563c, #24563c);
    cursor: pointer;
    font-family: 'Manrope', sans-serif;
  }

  .drone-progress-chip[hidden],
  .layer-menu.show~.drone-progress-chip {
    display: none;
  }

  .drone-progress-chip:focus-visible {
    outline: 2px solid #1e9e62;
    outline-offset: 2px;
  }

  .drone-progress-chip .bi {
    flex: 0 0 auto;
    color: var(--fg-1e9e62, #1e9e62);
    font-size: 18px;
  }

  .drone-progress-chip-body {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
  }

  .drone-progress-chip-label {
    overflow: hidden;
    font-size: 11px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .drone-progress-chip-track {
    position: relative;
    display: block;
    height: 6px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--bg-e5eee7, #e5eee7);
  }

  .drone-progress-chip-fill {
    display: block;
    width: 0;
    height: 100%;
    border-radius: inherit;
    background: #1e9e62;
    transition: width .15s ease;
  }

  .drone-progress-chip-track.is-indeterminate .drone-progress-chip-fill {
    width: 35%;
    transition: none;
    animation: drone-progress-slide 1.3s ease-in-out infinite;
  }

  @keyframes drone-progress-slide {
    from {
      transform: translateX(-100%);
    }

    to {
      transform: translateX(290%);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .drone-progress-chip-track.is-indeterminate .drone-progress-chip-fill {
      width: 100%;
      animation: none;
      opacity: .55;
    }
  }

  .drone-pipeline {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 2px;
  }

  .drone-pipeline-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
  }

  .drone-pipeline-step {
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--bg-eef3ef, #eef3ef);
    color: #8a9b90;
    font-size: 9px;
    font-weight: 600;
  }

  .drone-pipeline-step.done {
    background: var(--bg-dff2e7, #dff2e7);
    color: var(--fg-1e7a4c, #1e7a4c);
  }

  .drone-pipeline-step.done::before {
    content: '✓ ';
  }

  .drone-pipeline-step.active {
    background: #1e9e62;
    color: #fff;
  }

  @media (max-width: 780px) {
    .drone-progress-chip {
      width: min(230px, calc(100vw - 80px));
    }
  }

  .drone-imagery-ai {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 4px;
    padding-top: 4px;
    border-top: 1px dashed var(--bd-e3ebe5, #e3ebe5);
  }

  .drone-imagery-ai-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
  }

  .drone-imagery-ai-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--fg-4c6b5d, #4c6b5d);
    cursor: pointer;
  }

  .drone-imagery-ai-button {
    padding: 4px 7px;
    border: 1px solid var(--bd-cfe0d5, #cfe0d5);
    border-radius: 6px;
    background: var(--bg-f5faf7, #f5faf7);
    color: var(--fg-24563c, #24563c);
    cursor: pointer;
    font: inherit;
    font-size: 10px;
  }

  .drone-imagery-ai-button.primary {
    border-color: #1e9e62;
    background: #1e9e62;
    color: #fff;
  }

  .drone-imagery-ai-button:hover:not(:disabled) {
    filter: brightness(.96);
  }

  .drone-imagery-ai-button:disabled {
    cursor: wait;
    opacity: .55;
  }

  .layer-option {
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 500;
    text-align: left;
    color: var(--fg-4c6b5d, #4c6b5d);
    padding: 8px 10px;
    border-radius: 9px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
  }

  .layer-option:hover {
    background: var(--bg-f4fbf6, #f4fbf6);
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
    color: var(--fg-6a8a6a, #6a8a6a);
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
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 10px;
    background: var(--bg-ffffff, #fff);
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
    color: var(--fg-4c6b5d, #4c6b5d);
    border: none;
    border-radius: 0;
    background: transparent;
    transition: all .15s ease;
    min-width: 0;
    margin: 0;
  }

  .draw-btn:not(:last-child) {
    border-right: 1px solid var(--bd-d4dfd4, #d4dfd4);
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
    color: var(--fg-1a2e1a, #1a2e1a);
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
    color: var(--fg-4c6b5d, #4c6b5d);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 6px;
    background: var(--bg-ffffff, #fff);
    transition: all .15s ease;
  }

  .delineation-toolbar button:hover {
    background: var(--bg-f7faf7, #f7faf7);
    border-color: var(--bd-d0d8d0, #d0d8d0);
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
    background: var(--bg-e0e8e0, #e0e8e0);
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
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 10px;
    background: var(--bg-ffffff, #fff);
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
    color: var(--fg-4c6b5d, #4c6b5d);
    border: none;
    border-radius: 0;
    background: transparent;
    transition: all .15s ease;
    margin: 0;
  }

  .toolbar-btn-group button:not(:last-child) {
    border-right: 1px solid var(--bd-d4dfd4, #d4dfd4);
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
    color: var(--fg-4c6b5d, #4c6b5d);
    border: 1px solid var(--bd-d4dfd4, #d4dfd4);
    border-radius: 10px;
    background: var(--bg-ffffff, #fff);
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
    background: var(--bg-f5f7f5, #f5f7f5);
    color: #a0b0a5;
    border-color: var(--bd-e0e8e0, #e0e8e0);
  }

  .map-legend-float {
    position: absolute;
    bottom: 20px;
    left: 12px;
    z-index: 800;
    background: var(--bg-ffffff, #fff);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
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
    position: static;
    width: 100%;
    min-width: 0;
    max-width: none;
    margin-top: 14px;
    padding: 12px;
    transform: none;
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 8px;
    background: var(--bg-ffffff, #fff);
    box-shadow: none;
    user-select: none;
  }

  .map-timeline-control.collapsed {
    min-width: 0;
    padding: 10px 12px;
    border-radius: 8px;
    box-shadow: none;
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
    color: var(--fg-1e9e62, #1e9e62);
    font-size: 15px;
  }

  .timeline-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--fg-1a2e1a, #1a2e1a);
    letter-spacing: -0.2px;
  }

  .timeline-history-caption {
    margin: 0 0 8px;
    color: var(--fg-6a8a6a, #6a8a6a);
    font-size: 11px;
  }

  .timeline-empty {
    padding: 8px 0;
    color: var(--fg-6a8a6a, #6a8a6a);
    font-size: 12px;
  }

  .timeline-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: var(--bg-edf7f2, #edf7f2);
    border: 1px solid var(--bd-bce6cf, #bce6cf);
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: var(--fg-1e9e62, #1e9e62);
    transition: all 0.2s ease;
  }

  .timeline-badge.badge-negative {
    background: var(--bg-fdf0ee, #fdf0ee);
    border-color: var(--bd-f5c2bc, #f5c2bc);
    color: var(--fg-d04030, #d04030);
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
    color: var(--fg-6a8a6a, #6a8a6a);
    transition: background 0.15s, color 0.15s;
  }

  .timeline-collapse-btn:hover {
    background: var(--bg-eef4ee, #eef4ee);
    color: var(--fg-1a2e1a, #1a2e1a);
  }

  .timeline-collapse-btn i {
    transition: transform 0.2s ease;
    font-size: 13px;
  }

  .timeline-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--bg-f4f8f4, #f4f8f4);
    border-radius: 10px;
    padding: 7px 12px;
    gap: 12px;
    font-size: 11px;
    color: var(--fg-556b56, #556b56);
  }

  .map-timeline-control .timeline-info-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: start;
    gap: 8px;
    padding: 8px 0 0;
    border-top: 1px solid var(--bd-edf1ed, #edf1ed);
    border-radius: 0;
    background: transparent;
  }

  .map-timeline-control .timeline-stat {
    min-width: 0;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    white-space: normal;
  }

  .map-timeline-control .timeline-stat strong {
    max-width: 100%;
    overflow-wrap: anywhere;
  }

  .timeline-stat {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
  }

  .timeline-stat strong {
    color: var(--fg-1a2e1a, #1a2e1a);
    font-weight: 700;
  }

  .timeline-stat strong.text-green {
    color: var(--fg-1e9e62, #1e9e62);
  }

  .map-layer-control>button.active-toggle {
    background: var(--bg-edf7f2, #edf7f2);
    color: var(--fg-1e9e62, #1e9e62);
  }

  @media (max-width: 780px) {

    /* Relocate map legend to top-right on mobile so bottom is reserved for the timeline slider */
    .map-legend-float {
      bottom: auto;
      top: 120px;
      right: 10px;
      left: auto;
      padding: 6px 10px;
      background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.95);
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
    color: var(--fg-1e9e62, #1e9e62);
    background: var(--bg-edf7f2, #edf7f2);
    border: 1px solid var(--bd-cbe9d8, #cbe9d8);
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
    width: 100%;
    padding: 8px 10px;
    border-radius: 8px;
    background: var(--bg-f8faf8, #f8faf8);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    cursor: pointer;
    color: var(--fg-1a2e1a, #1a2e1a);
    font: inherit;
    text-align: left;
    transition: all 0.15s ease;
  }

  .temporal-epoch-row:hover {
    background: var(--bg-edf7f2, #edf7f2);
    border-color: var(--bd-bce6cf, #bce6cf);
  }

  .temporal-epoch-row.active {
    background: var(--bg-edf7f2, #edf7f2);
    border-color: #1e9e62;
    box-shadow: 0 2px 6px rgba(30, 158, 98, 0.15);
  }

  .epoch-year-badge {
    font-weight: 800;
    font-size: 12px;
    padding: 3px 7px;
    border-radius: 6px;
    background: var(--bg-ffffff, #fff);
    color: var(--fg-1e9e62, #1e9e62);
    border: 1px solid var(--bd-bce6cf, #bce6cf);
  }

  .temporal-epoch-row.active .epoch-year-badge {
    background: #1e9e62;
    color: #fff;
  }

  .epoch-content {
    flex: 1;
    min-width: 0;
    display: block;
  }

  .epoch-headline {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--fg-1a2e1a, #1a2e1a);
  }

  .epoch-area {
    flex-shrink: 0;
    color: var(--fg-1e9e62, #1e9e62);
    white-space: nowrap;
  }

  .epoch-source {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .epoch-sub {
    display: block;
    font-size: 11px;
    color: var(--fg-6a8a6a, #6a8a6a);
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
    background: var(--bg-edf7f2, #edf7f2);
    border-color: var(--bd-b0e0c0, #b0e0c0);
    color: var(--fg-1e9e62, #1e9e62);
  }

  .ta {
    background: var(--bg-fdf5e8, #fdf5e8);
    border-color: var(--bd-e8cc98, #e8cc98);
    color: var(--fg-c07818, #c07818);
  }

  .tb {
    background: var(--bg-eef4ff, #eef4ff);
    border-color: var(--bd-b0c8f0, #b0c8f0);
    color: var(--fg-3060b0, #3060b0);
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
    border-bottom: 1px solid var(--bd-e0e8e0, #e0e8e0);
    text-align: left;
  }

  .tbl td {
    padding: 7px 8px;
    border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
    color: var(--fg-3a5a3a, #3a5a3a);
  }

  .mbar {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .mtrack {
    width: 48px;
    height: 3px;
    background: var(--bg-e0e8e0, #e0e8e0);
    border-radius: 2px;
  }

  .mfill {
    height: 100%;
    border-radius: 2px;
  }

  .site-card {
    background: var(--bg-f7faf7, #f7faf7);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 8px;
    cursor: pointer;
  }

  .site-card:hover {
    border-color: #1e9e62;
    background: var(--bg-edf7f2, #edf7f2);
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
    background: var(--bg-edf7f2, #edf7f2);
    color: var(--fg-1e9e62, #1e9e62);
    border: 1px solid var(--bd-b0e0c0, #b0e0c0);
  }

  .ra {
    background: var(--bg-fdf5e8, #fdf5e8);
    color: var(--fg-c07818, #c07818);
    border: 1px solid var(--bd-e8cc98, #e8cc98);
  }

  .rb {
    background: var(--bg-eef4ff, #eef4ff);
    color: var(--fg-3060b0, #3060b0);
    border: 1px solid var(--bd-b0c8f0, #b0c8f0);
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
    background: var(--bg-e0e8e0, #e0e8e0);
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
    background: var(--bg-e0e8e0, #e0e8e0);
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

  .btn.is-saving {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: wait !important;
    opacity: 0.85;
  }

  /* Follows the button's text color, so it works on green and light buttons alike. */
  .save-btn-spinner {
    display: inline-block;
    flex: none;
    width: 14px;
    height: 14px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: save-btn-spin 0.7s linear infinite;
  }

  @keyframes save-btn-spin {
    to {
      transform: rotate(360deg);
    }
  }

  #genusQuickSelector.input-error {
    border-radius: 10px;
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.55);
    padding: 4px;
  }

  /* Custom scrollbar for notes and narrow scrollable containers.
     Standard properties only as the Firefox fallback: Chromium ignores ::-webkit-scrollbar when they are set. */
  @supports not selector(::-webkit-scrollbar) {
    #delineationNotes {
      scrollbar-width: thin;
      scrollbar-color: #9ca3af var(--bg-f0f4f0, #f0f4f0);
    }
  }

  #delineationNotes::-webkit-scrollbar {
    width: 10px;
  }

  #delineationNotes::-webkit-scrollbar-track {
    background: var(--bg-f0f4f0, #f0f4f0);
    border-radius: 999px;
  }

  #delineationNotes::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 999px;
    border: 2px solid var(--bd-f0f4f0, #f0f4f0);
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
    background: var(--bg-ffffff, #ffffff);
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
    border: 3.5px solid var(--bd-edf2f7, #edf2f7);
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
    color: var(--fg-1a2e1a, #1a2e1a);
    margin-bottom: 6px;
  }

  .processing-modal-subtitle {
    font-size: 13px;
    color: var(--fg-64748b, #64748b);
    margin: 0;
    line-height: 1.4;
  }

  .success-icon-wrapper {
    width: 52px;
    height: 52px;
    margin: 0 auto 16px;
    background: var(--bg-edf7f2, #edf7f2);
    border: 2px solid var(--bd-c8e6d4, #c8e6d4);
    color: var(--fg-1e9e62, #1e9e62);
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
  body[data-page='map'] .left-panel {
    display: none !important;
  }

  body[data-page='delineate'] #editModeBtn,
  body[data-page='delineate'] .left-panel {
    display: none !important;
  }

  body[data-page='delineate'] #delineationToolbar {
    display: flex !important;
  }

  .delineation-draw-hint {
    position: absolute;
    top: 58px;
    left: 50%;
    z-index: 900;
    max-width: calc(100% - 24px);
    transform: translateX(-50%);
    padding: 6px 12px;
    border-radius: 8px;
    background: rgba(24, 41, 24, .86);
    color: #fff;
    font-size: 12px;
    pointer-events: none;
    text-align: center;
  }

  .delineation-draw-hint[hidden] {
    display: none;
  }

  /* Saved-delineation boundary editing */
  .boundary-edit-actions { margin-bottom: 12px; }
  .boundary-edit-hint { font-size: 11px; color: var(--fg-3c5a3c, #3c5a3c); background: var(--bg-eef6ee, #eef6ee); border: 1px solid var(--bd-cfe3cf, #cfe3cf); border-radius: 8px; padding: 8px 10px; margin-bottom: 8px; }
  .boundary-edit-hint ul { margin: 4px 0 0; padding-left: 16px; }
  .boundary-edit-hint kbd { font-size: 10px; padding: 0 4px; border: 1px solid var(--bd-b8c8b8, #b8c8b8); border-radius: 3px; background: var(--bg-ffffff, #fff); }
  .delineation-vertex-handle.is-selected div { background: #ff9800 !important; transform: scale(1.25); }
  .delineation-midpoint-handle div { cursor: copy; }

  /* Expert choice: correction vs observed change (feeds "Changes over time") */
  .boundary-record-as { border: 1px solid var(--bd-d4dfd4, #d4dfd4); border-radius: 8px; padding: 8px 10px; margin: 0 0 8px; background: var(--bg-ffffff, #fff); }
  .boundary-record-as legend { font-size: 11px; font-weight: 700; color: var(--fg-556b56, #556b56); padding: 0 4px; }
  .boundary-record-option { display: flex; gap: 8px; align-items: flex-start; padding: 5px 0; cursor: pointer; }
  .boundary-record-option input { margin-top: 2px; accent-color: #1e9e62; flex: none; }
  .boundary-record-option strong { display: block; font-size: 12px; color: var(--fg-182918, #182918); }
  .boundary-record-option small { display: block; font-size: 11px; color: var(--fg-6a8a6a, #6a8a6a); line-height: 1.35; }
  .boundary-observation-fields { display: grid; gap: 4px; margin-top: 6px; padding-top: 8px; border-top: 1px dashed var(--bd-d4dfd4, #d4dfd4); }
  .boundary-observation-fields[hidden] { display: none; }
  .boundary-observation-fields label { font-size: 11px; color: var(--fg-556b56, #556b56); }
  .boundary-observation-fields input,
  .boundary-observation-fields select { width: 100%; padding: 7px 9px; border: 1px solid var(--bd-d4dfd4, #d4dfd4); border-radius: 8px; background: var(--bg-f8faf7, #f8faf7); color: var(--fg-182918, #182918); font: inherit; font-size: 12px; margin-bottom: 4px; }
  .boundary-observation-note { font-size: 10.5px; color: var(--fg-6a8a6a, #6a8a6a); margin: 0; }
</style>