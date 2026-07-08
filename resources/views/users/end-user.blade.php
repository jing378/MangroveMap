<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>MangroveMap — Smart Classifier</title>
  <link rel="icon" type="image/png" href="/icon-192.png" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
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
      z-index: 1000;
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
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
      display: none;
      z-index: 999;
      overflow: hidden;
    }

    .profile-dropdown.active {
      display: block;
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
      position: fixed;
      top: 60px;
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
      width: 0;
      min-width: 0;
      max-width: 0;
      flex: 0 0 0px;
      border-left: none;
      position: relative;
      transition: width 0.3s ease-in-out, max-width 0.3s ease-in-out, flex-basis 0.3s ease-in-out;
    }

    #mapRightPanel.open {
      width: 320px;
      max-width: 320px;
      flex: 0 0 320px;
      border-left: 1px solid #e0e8e0;
    }

    #mapRightPanel .scroll {
      min-width: 320px;
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
        position: absolute !important;
        right: 0;
        top: 0;
        bottom: 0;
        height: 100% !important;
        max-height: 100% !important;
        width: 85% !important;
        max-width: 340px;
        z-index: 1000;
        border: none !important;
        border-left: 1px solid #e0e8e0 !important;
        box-shadow: -2px 0 15px rgba(0, 0, 0, 0.15);
        transform: translateX(105%);
        transition: transform 0.3s ease-in-out;
      }

      #mapRightPanel.open {
        transform: translateX(0);
        width: 85% !important;
      }

      #mapRightPanel .scroll {
        min-width: 100%;
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
        top: 56px;
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
        grid-template-columns: repeat(4, 1fr);
        gap: 6px;
      }

      .stat-box {
        padding: 6px;
      }

      .stat-num {
        font-size: 20px;
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

    .classify-main {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f7faf7;
      overflow: hidden;
      position: relative;
    }

    .classify-bottom {
      background: #fff;
      border-top: 1px solid #e0e8e0;
      padding: 13px 18px;
      overflow-y: auto;
      max-height: 200px;
      flex: 0 0 auto;
    }

    .upload-zone {
      border: 1.5px dashed #c4d8c4;
      border-radius: 14px;
      padding: 36px 28px;
      text-align: center;
      cursor: pointer;
      background: #fff;
      transition: all .15s;
    }

    .upload-zone:hover {
      border-color: #1e9e62;
      background: #f0faf5;
    }

    .upload-zone .ico {
      font-size: 34px;
      margin-bottom: 10px;
    }

    .upload-zone h3 {
      font-size: 16px;
      font-weight: 600;
      color: #2a4a2a;
    }

    .upload-zone p {
      font-size: 14px;
      color: #9ab0a0;
    }

    #fileInput {
      display: none;
    }

    .ubtn {
      display: inline-block;
      margin-top: 12px;
      padding: 7px 18px;
      background: #1e9e62;
      color: #fff;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      border: none;
    }

    #imgView {
      display: none;
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      padding: 16px;
    }

    .conf-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 7px;
    }

    .conf-lbl {
      font-size: 13px;
      color: #5a7a5a;
      width: 145px;
      flex-shrink: 0;
      font-style: italic;
    }

    .conf-track {
      flex: 1;
      height: 5px;
      background: #e0e8e0;
      border-radius: 3px;
      overflow: hidden;
    }

    .conf-fill {
      height: 100%;
      border-radius: 3px;
    }

    .conf-pct {
      font-size: 12px;
      font-weight: 600;
      width: 28px;
      text-align: right;
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
    }

    @media (max-width: 780px) {
      .view.on {
        flex-direction: column;
      }

      #v-classify.on {
        flex-direction: column !important;
      }
    }

    .cw {
      position: relative;
      width: 100%;
    }

    /* Image preview container */
    .image-preview-container {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f7faf7;
    }

    .no-image-message {
      text-align: center;
      padding: 24px;
      color: #9ab0a0;
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
  </style>
</head>

<body>
  <header class="header">
    <div class="logo">
      <div class="logo-mark"><i class="bi bi-leaf"></i></div><span>MangroveMap</span>
    </div>
    <div class="header-right">
      @auth
      @include('components.notification-bell')
      <div class="profile-dropdown-wrapper">
        <button class="profile-toggle" id="profileToggle" type="button">
          @if(Auth::user()->profile_image)
          <img src="{{ asset('storage/' . Auth::user()->profile_image) }}" alt="Profile" class="profile-image">
          @else
          <div class="profile-image">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
          @endif
          <div class="profile-info">
            <div class="profile-name">{{ Auth::user()->name }}</div>
            <div class="profile-role">{{ Auth::user()->isExpert() ? 'Expert' : 'Resident' }}</div>
          </div>
        </button>

        <div class="profile-dropdown" id="profileDropdown">
          <div class="dropdown-header">
            @if(Auth::user()->profile_image)
            <img src="{{ asset('storage/' . Auth::user()->profile_image) }}" alt="Profile" class="dropdown-header-image">
            @else
            <div class="dropdown-header-image">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</div>
            @endif
            <div class="dropdown-header-text">
              <div class="dropdown-header-name">{{ Auth::user()->name }}</div>
              <div class="dropdown-header-email">{{ Auth::user()->email }}</div>
            </div>
          </div>
          <div class="dropdown-menu">
            <a href="{{ route('profile.show') }}" class="dropdown-item">
              <i class="bi bi-person-circle"></i>
              <span>View Profile</span>
            </a>
            <a href="#" class="dropdown-item">
              <i class="bi bi-gear"></i>
              <span>Settings</span>
            </a>
            <a href="#" class="dropdown-item">
              <i class="bi bi-question-circle"></i>
              <span>Help & Support</span>
            </a>
            <div class="dropdown-divider"></div>
            <form method="POST" action="{{ route('logout') }}" style="width: 100%; padding: 0; margin: 0;">
              @csrf
              <button type="submit" class="dropdown-item danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
              </button>
            </form>
          </div>
        </div>
        @else
        <button class="btn btn-g" onclick="window.location.href='/login'"><i class="bi bi-box-arrow-in-right"></i> Login</button>
        @endauth
      </div>
  </header>

  <div class="app">
    <!-- COVERAGE MAP -->
    <div class="view on" id="v-map">
      <div class="left-panel">
        <div class="scroll">
          <div class="sec">Summary</div>
          <div class="stat-grid">
            <div class="stat-box">
              <div class="stat-num g">0</div>
              <div class="stat-lbl">Total area (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num r">0</div>
              <div class="stat-lbl">Degraded (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num a">0</div>
              <div class="stat-lbl">Net gain (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num">0</div>
              <div class="stat-lbl">Genus found</div>
            </div>
          </div>
          <div class="div"></div>
          <div class="sec">Mangrove Zones</div>
          <div id="zoneListContainer" class="zone-list"></div>
        </div>
      </div>
      <div class="main">
        <div class="map-wrap">
          <div id="mainMap"></div>
          <div class="delineation-toolbar" id="delineationToolbar">
            <div class="draw-select-wrapper">
              <div class="draw-mode-group" role="group" aria-label="Drawing mode">
                <button type="button" class="draw-btn" data-mode="point"><i class="bi bi-geo-alt-fill"></i><span>Point</span></button>
                <button type="button" class="draw-btn" data-mode="line"><i class="bi bi-slash-circle"></i><span>Line</span></button>
                <button type="button" class="draw-btn" data-mode="area"><i class="bi bi-grid-3x3-gap"></i><span>Area</span></button>
              </div>
              <select id="drawTypeSelect" class="draw-select" aria-label="Select drawing mode" disabled>
                <option value="" selected disabled>Select mode</option>
                <option value="point">Point</option>
                <option value="line">Line</option>
                <option value="area">Area</option>
              </select>
            </div>
            <div class="delineation-separator"></div>
            <div class="toolbar-right">
              <div class="toolbar-btn-group">
                <button id="undoBtn" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                <button id="redoBtn" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
              </div>
              <button id="saveBtn" class="toolbar-save-btn" title="Save draft"><i class="bi bi-download"></i></button>
            </div>
          </div>
          <div class="map-layer-control">
            <button id="layerToggle" type="button" onclick="toggleLayerMenu()" aria-expanded="false" title="Choose map layer" aria-label="Choose map layer">
              <i class="bi bi-layers-fill"></i>
            </button>
            <button id="editModeBtn" type="button" onclick="showEditMode()" title="Edit mode" aria-label="Edit mode">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button id="suitabilityBtn" type="button" onclick="showSuitability()" title="Show suitability layer" aria-label="Show suitability layer">
              <i class="bi bi-check2-square"></i>
            </button>
            <button id="classifyBtn" type="button" onclick="showClassify()" title="Show classification" aria-label="Show classification layer">
              <i class="bi bi-image"></i>
            </button>
            <div id="layerMenu" class="layer-menu" aria-label="Base layer options">
              <button class="layer-option active" onclick="setBase('sat', this)">Satellite</button>
              <button class="layer-option" onclick="setBase('osm', this)">Street</button>
              <button class="layer-option" onclick="setBase('topo', this)">Topo</button>
            </div>
          </div>
          <div class="map-legend-float">
            <div class="leg-title">Zone type</div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(30,158,98,.4);border:1.5px solid #1e9e62"></div>Healthy
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(192,120,24,.4);border:1.5px solid #c07818"></div>Sparse
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:rgba(208,64,48,.4);border:1.5px solid #d04030"></div>Degraded
            </div>
          </div>
        </div>
      </div>
      <div class="right-panel" id="mapRightPanel">
        <button onclick="closeRightPanel()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 16px; cursor: pointer; color: #9ab0a0; z-index: 10;">
          <i class="bi bi-x-lg"></i>
        </button>
        <div class="scroll">
          <div class="sec" style="padding-right: 20px;">Selected Zone</div>
          <div id="delineationInfoCard" class="d-card" style="display:none;">
            <div class="d-title">Delineated Area Info</div>
            <div class="d-row"><span class="d-key">Type</span><span class="d-val" id="delineationFeatureType">-</span></div>
            <div class="d-row"><span class="d-key">Coords</span><span class="d-val" id="delineationFeatureCoords">-</span></div>
            <div class="d-row"><span class="d-key">Label</span><span class="d-val" id="delineationFeatureLabel">-</span></div>
            <div class="d-row"><span class="d-key">Review status</span><span class="d-val" id="delineationReviewStatus">-</span></div>
            <div id="delineationRejectionBox" class="delineation-rejection-box" style="display:none;">
              <strong>Expert feedback</strong>
              <p id="delineationRejectionNotes" style="margin:0;"></p>
            </div>
            <div class="div"></div>
            <div style="margin-bottom:12px;">
              <label for="delineationLabel" style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Name / Label</label>
              <input id="delineationLabel" type="text" style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;" placeholder="Enter zone name or note" />
            </div>
            <div style="margin-bottom:12px;">
              <label for="delineationNotes" style="display:block;font-size:12px;color:#556b56;margin-bottom:6px;">Notes</label>
              <textarea id="delineationNotes" rows="4" style="width:100%;padding:10px;border:1px solid #d4dfd4;border-radius:10px;background:#f8faf7;color:#182918;resize:none;overflow-y:auto;" placeholder="Fill in information about this delineated feature"></textarea>
            </div>
          </div>
          <button id="removeDelineationBtn" class="btn" style="width:100%;margin-top:8px;margin-bottom:14px;background:#fdf0ee;color:#d04030;border-color:#e8b8b0;" onclick="window.removeCurrentDelineation()">Remove delineation</button>
          <div id="zoneDetailsContent">
            <div class="d-card">
              <div class="d-title" id="dName"></div>
              <div class="d-row"><span class="d-key">Area</span><span class="d-val" id="dArea"></span></div>
              <div class="d-row"><span class="d-key">Health (NDVI)</span><span class="d-val" id="dNDVI"></span></div>
              <div class="d-row"><span class="d-key">Status</span><span class="d-val" id="dStatus"></span></div>
              <div class="d-row"><span class="d-key">Dominant genus</span><span class="d-val" id="dGenus"></span></div>
              <div class="d-row"><span class="d-key">Last scan</span><span class="d-val" id="dScan"></span></div>
            </div>
            <div class="div"></div>
            <div class="sec">Genus Distribution</div>
            <div class="cw" style="height:148px"><canvas id="pieC"></canvas></div>
            <div class="div"></div>
            <div class="div"></div>
            <div class="sec">Coverage Trend</div>
            <div class="cw" style="height:108px"><canvas id="trendC"></canvas></div>
          </div>
        </div>
      </div>
    </div>

    <!-- CLASSIFIER VIEW - UPLOAD & AUTO CLASSIFICATION -->
    <div class="view" id="v-classify">
      <div class="left-panel">
        <div class="scroll">
          <div class="sec">Upload Image</div>
          <a href="{{ Auth::user()->isExpert() ? route('expert.dashboard') : route('dashboard') }}" class="btn" style="margin-bottom:14px; display:inline-flex; align-items:center;"><i class="bi bi-arrow-left-short" style="margin-right:6px;"></i> Back to Dashboard</a>
          <div class="upload-zone" id="uploadBox" onclick="document.getElementById('fileInput').click()">
            <div class="ico">📷</div>
            <h3>Click to upload mangrove image</h3>
            <p>JPG, PNG - field, drone, or satellite photo</p>
            <input type="file" id="fileInput" accept="image/*" onchange="handleImageUpload(event)" />
            <label class="ubtn">Choose Image</label>
          </div>
          <div id="classResult" style="display:none;margin-top:14px">
            <div class="d-card">
              <div class="d-title" style="color:#1e9e62">Rhizophora mucronata</div>
              <div style="font-size:11px;font-style:italic;color:#7a9a7a;margin-bottom:9px">Genus: Rhizophora - Rhizophoraceae</div>
              <div style="margin-bottom:9px"><span class="tag tg">Confidence 91%</span><span class="tag ta">Salinity: High</span><span class="tag tb">Prop-root</span></div>
              <div class="d-row"><span class="d-key">Zone</span><span class="d-val">Mid-intertidal</span></div>
              <div class="d-row"><span class="d-key">Salinity</span><span class="d-val">10-35 ppt</span></div>
              <div class="d-row"><span class="d-key">Substrate</span><span class="d-val">Fine mud</span></div>
              <div class="d-row"><span class="d-key">Carbon seq.</span><span class="d-val g">6.4 t C/ha/yr</span></div>
            </div>
            <div class="sec">Top Matches</div>
            <div class="conf-row"><span class="conf-lbl">R. mucronata</span>
              <div class="conf-track">
                <div class="conf-fill" style="width:91%;background:#1e9e62"></div>
              </div><span class="conf-pct">91%</span>
            </div>
            <div class="conf-row"><span class="conf-lbl">R. apiculata</span>
              <div class="conf-track">
                <div class="conf-fill" style="width:6%;background:#5ab8de"></div>
              </div><span class="conf-pct">6%</span>
            </div>
            <div class="conf-row"><span class="conf-lbl">B. gymnorrhiza</span>
              <div class="conf-track">
                <div class="conf-fill" style="width:3%;background:#c0c8b8"></div>
              </div><span class="conf-pct">3%</span>
            </div>
            <div class="div"></div>
            <div class="sec">Features Detected</div>
            <div style="font-size:11px;color:#5a7a5a;line-height:2.1"><i class="bi bi-check-circle-fill" style="color:#1e9e62;margin-right:5px"></i> Prop / stilt root system<br><i class="bi bi-check-circle-fill" style="color:#1e9e62;margin-right:5px"></i> Viviparous propagules<br><i class="bi bi-check-circle-fill" style="color:#1e9e62;margin-right:5px"></i> Elliptic leaf shape<br><i class="bi bi-check-circle-fill" style="color:#1e9e62;margin-right:5px"></i> Dense canopy structure<br><i class="bi bi-check-circle-fill" style="color:#1e9e62;margin-right:5px"></i> Mid-intertidal position</div>
          </div>
        </div>
      </div>
      <div class="main">
        <div class="classify-main">
          <div id="imagePreviewArea" class="image-preview-container">
            <div class="no-image-message">
              <div style="font-size:48px;margin-bottom:12px;color:#c0d8c0"><i class="bi bi-tree"></i></div>
              <p style="font-weight:500">No image loaded</p>
              <p style="font-size:12px">Upload a mangrove photo to see AI classification</p>
            </div>
            <img id="imgView" src="" alt="Mangrove Preview" style="display:none; max-width:100%; max-height:100%; object-fit:contain;" />
          </div>
        </div>
        <div class="classify-bottom">
          <div class="sec">Genus Reference Library</div>
          <table class="tbl">
            <thead>
              <tr>
                <th>Scientific Name</th>
                <th>Root Type</th>
                <th>Salinity</th>
                <th>% Cover</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="font-style:italic">Rhizophora mucronata</td>
                <td>Prop</td>
                <td>High</td>
                <td>
                  <div class="mbar">
                    <div class="mtrack">
                      <div class="mfill" style="width:80%;background:#1e9e62"></div>
                    </div>34%
                  </div>
                </td>
              </tr>
              <tr>
                <td style="font-style:italic">Avicennia marina</td>
                <td>Pneumatophore</td>
                <td>Very High</td>
                <td>
                  <div class="mbar">
                    <div class="mtrack">
                      <div class="mfill" style="width:65%;background:#5ab8de"></div>
                    </div>22%
                  </div>
                </td>
              </tr>
              <tr>
                <td style="font-style:italic">Sonneratia alba</td>
                <td>Knee/peg</td>
                <td>Medium</td>
                <td>
                  <div class="mbar">
                    <div class="mtrack">
                      <div class="mfill" style="width:53%;background:#f4a840"></div>
                    </div>18%
                  </div>
                </td>
              </tr>
              <tr>
                <td style="font-style:italic">Bruguiera gymnorrhiza</td>
                <td>Knee</td>
                <td>Medium</td>
                <td>
                  <div class="mbar">
                    <div class="mtrack">
                      <div class="mfill" style="width:41%;background:#a070e0"></div>
                    </div>14%
                  </div>
                </td>
              </tr>
              <tr>
                <td style="font-style:italic">Ceriops tagal</td>
                <td>Buttress</td>
                <td>High</td>
                <td>
                  <div class="mbar">
                    <div class="mtrack">
                      <div class="mfill" style="width:24%;background:#c0c8b8"></div>
                    </div>8%
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="right-panel">
        <div class="scroll">
          <div class="sec">About the Model</div>
          <div style="font-size:12px;color:#5a7a5a;line-height:1.8;margin-bottom:14px">ResNet-50 classifier trained on <strong>14,000</strong> labeled images across <strong>12 genera</strong>.<br>Top-1 accuracy: <strong class="g">88.6%</strong><br>Top-3 accuracy: <strong class="g">96.2%</strong></div>
          <div class="div"></div>
          <div class="sec">Supported Genera</div>
          <div style="font-size:12px">
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #edf2ed"><span>Rhizophora</span><span>Detected</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #edf2ed"><span>Avicennia</span><span>Detected</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #edf2ed"><span>Sonneratia</span><span>Detected</span></div>
            <div style="display:flex;justify-content:space-between;padding:6px 0"><span>Bruguiera</span><span>Detected</span></div>
          </div>
          <div class="div"></div>
          <div class="sec">Tips for Best Results</div>
          <div style="font-size:11px;color:#7a9a7a;line-height:2">• Shoot at low tide<br>• Include root base in frame<br>• Avoid heavy cloud shadow<br>• Resolution 1m or better</div>
        </div>
      </div>
    </div>

    <!-- PLANTING SUITABILITY -->
    <div class="view" id="v-planting">
      <div class="left-panel">
        <div class="scroll">
          <div class="sec">Summary</div>
          <div class="stat-grid">
            <div class="stat-box">
              <div class="stat-num g">14</div>
              <div class="stat-lbl">High suitability</div>
            </div>
            <div class="stat-box">
              <div class="stat-num a">22</div>
              <div class="stat-lbl">Medium sites</div>
            </div>
            <div class="stat-box">
              <div class="stat-num">5,840</div>
              <div class="stat-lbl">Plantable (ha)</div>
            </div>
            <div class="stat-box">
              <div class="stat-num g">37K</div>
              <div class="stat-lbl">t CO2e / yr</div>
            </div>
          </div>
          <div class="div"></div>
          <div class="sec">Suitability Factors</div>
          <div class="factor-row">
            <div class="factor-head"><span class="factor-key">Tidal regime</span><span class="g">91%</span></div>
            <div class="factor-bar">
              <div class="factor-fill" style="width:91%;background:#1e9e62"></div>
            </div>
          </div>
          <div class="factor-row">
            <div class="factor-head"><span class="factor-key">Sediment stability</span><span class="g">84%</span></div>
            <div class="factor-bar">
              <div class="factor-fill" style="width:84%;background:#1e9e62"></div>
            </div>
          </div>
          <div class="factor-row">
            <div class="factor-head"><span class="factor-key">Salinity match</span><span>78%</span></div>
            <div class="factor-bar">
              <div class="factor-fill" style="width:78%;background:#5ab8de"></div>
            </div>
          </div>
          <div class="factor-row">
            <div class="factor-head"><span class="factor-key">Freshwater input</span><span class="a">62%</span></div>
            <div class="factor-bar">
              <div class="factor-fill" style="width:62%;background:#c07818"></div>
            </div>
          </div>
          <div class="div"></div>
          <div class="sec">Coverage Trend</div>
          <div class="cw" style="height:108px"><canvas id="plantTrendC"></canvas></div>
        </div>
      </div>
      <div class="main">
        <div class="map-wrap">
          <div id="plantMap"></div>
          <div class="map-legend-float">
            <div class="leg-title">Priority</div>
            <div class="leg-row">
              <div class="leg-dot" style="background:#1e9e62"></div>Critical
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:#c07818"></div>High
            </div>
            <div class="leg-row">
              <div class="leg-dot" style="background:#5ab8de"></div>Medium
            </div>
          </div>
        </div>
      </div>
      <div class="right-panel">
        <div class="scroll">
          <div class="sec">Priority Planting Sites</div>
          <div id="plantSitesList"></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // DATA & MAP INITIALIZATION (same as before)
    const saveDelineationUrl = "{{ Auth::user()->isExpert() ? route('expert.delineations.store') : route('delineations.store') }}";
    const deleteDelineationBaseUrl = "{{ url('/delineations') }}";
    const savedDelineations = @json($delineations);
    const approvedDelineations = @json($approvedDelineationsForMap);
    // Combine user delineations with approved delineations from all users
    const allDelineations = [...savedDelineations, ...approvedDelineations];
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function extractPoints(coords) {
      if (!coords) return [];
      if (typeof coords[0] === 'number') return [coords];
      if (Array.isArray(coords[0]) && typeof coords[0][0] === 'number') return coords;
      if (Array.isArray(coords[0]) && Array.isArray(coords[0][0])) return coords.flat();
      return [];
    }

    function getFeatureCenter(feature) {
      const points = extractPoints(feature?.coords);
      if (!points.length) return [10.25, 125.00];
      const total = points.reduce((acc, pt) => {
        acc[0] += Number(pt[0]) || 0;
        acc[1] += Number(pt[1]) || 0;
        return acc;
      }, [0, 0]);
      return [total[0] / points.length, total[1] / points.length];
    }

    function formatFeatureArea(feature) {
      const points = extractPoints(feature?.coords);
      if (!points.length) return 'N/A';
      if (feature?.type === 'point') return '1 pt';
      return `${points.length} pts`;
    }

    function formatDelineationScan(record) {
      if (!record?.created_at) return 'Draft';
      const d = new Date(record.created_at);
      return isNaN(d.getTime()) ? 'Draft' : d.toLocaleDateString();
    }

    function getDelineationStatus(record) {
      if (record.is_rejected) return {
        label: 'Rejected',
        chip: 'cr',
        color: '#d04030',
        sc: 'r'
      };
      if (record.is_approved) return {
        label: 'Approved',
        chip: 'cg',
        color: '#1e9e62',
        sc: 'g'
      };
      return {
        label: 'Pending',
        chip: 'cp',
        color: '#c07818',
        sc: 'p'
      };
    }

    // "Mangrove Zones" should show approved/public delineations (not the user's drafts).
    // Drafts/pending delineations are handled separately in the edit/drawing layer.
    const zoneRecords = Array.isArray(allDelineations) ? allDelineations.filter(r => !!r?.is_approved && !r?.is_rejected) : [];

    const zones = zoneRecords.map(record => {
      const feature = Array.isArray(record.features) ? record.features[0] : null;
      const [lat, lng] = getFeatureCenter(feature);
      const review = getDelineationStatus(record);
      return {
        id: record.id,
        name: record.name || `Delineation ${record.id}`,
        lat,
        lng,
        area: formatFeatureArea(feature),
        ndvi: review.label,
        status: review.label,
        genus: feature?.label || 'User delineation',
        scan: formatDelineationScan(record),
        sc: review.sc,
        color: review.color,
        chip: review.chip
      };
    });

    const plantSites = [];

    const zoneContainer = document.getElementById('zoneListContainer');
    zones.forEach((z, i) => {
      const div = document.createElement('div');
      div.className = `zone-row`;
      div.setAttribute('onclick', `flyTo(${i})`);
      div.innerHTML = `<div class="z-pip" style="background:${z.color}"></div><div class="z-name">${z.name}</div><div class="z-ha">${z.area.replace(/[^0-9k]/g,'')}</div><span class="z-chip ${z.chip || 'ca'}">${z.status}</span>`;
      zoneContainer.appendChild(div);
    });

    const satL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 18
    });
    const osmL = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18
    });
    const topoL = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17
    });
    let curBase = satL;
    let mainMap = L.map('mainMap', {
      zoomControl: true,
      layers: [satL]
    }).setView([10.25, 125.00], 10);

    const snapPixelThreshold = 15; // pixels
    let snapMarker = L.circleMarker([0, 0], {
      radius: 8,
      color: '#1e9e62',
      weight: 2,
      fillColor: '#1e9e62',
      fillOpacity: 0.35,
      opacity: 0
    }).addTo(mainMap);

    let locationMarker = null;
    let locationCircle = null;

    function showMyLocation() {
      mainMap.locate({
        setView: true,
        maxZoom: 15,
        watch: false
      });
    }

    mainMap.on('locationfound', (e) => {
      if (locationMarker) mainMap.removeLayer(locationMarker);
      if (locationCircle) mainMap.removeLayer(locationCircle);
      locationMarker = L.marker(e.latlng).addTo(mainMap).bindPopup('You are here').openPopup();
      locationCircle = L.circle(e.latlng, {
        radius: e.accuracy || 50,
        color: '#1e9e62',
        fillColor: '#1e9e62',
        fillOpacity: 0.15
      }).addTo(mainMap);
    });

    mainMap.on('locationerror', () => {
      alert('Unable to determine your location.');
    });

    const zoomControlContainer = mainMap.zoomControl.getContainer();
    if (zoomControlContainer) {
      const locateBtn = L.DomUtil.create('a', 'leaflet-control-locate-button', zoomControlContainer);
      locateBtn.href = '#';
      locateBtn.title = 'Show my location';
      locateBtn.innerHTML = '<i class="bi bi-geo-alt-fill"></i>';
      L.DomEvent.on(locateBtn, 'click', L.DomEvent.stopPropagation)
        .on(locateBtn, 'click', L.DomEvent.preventDefault)
        .on(locateBtn, 'click', showMyLocation);
    }

    // Keep overlay layers in dedicated groups so redraws are cheap.
    // This avoids expensive mainMap.eachLayer() scans/removals which can get laggy fast.
    const zoneMarkerLayer = L.layerGroup().addTo(mainMap);
    const delineationLayer = L.layerGroup().addTo(mainMap);
    const vertexLayer = L.layerGroup().addTo(mainMap);

    let polys = [];
    zones.forEach((z, i) => {
      const p = L.circleMarker([z.lat, z.lng], {
        radius: 8,
        fillColor: z.color,
        color: "#fff",
        weight: 2,
        opacity: 1,
        fillOpacity: 0.8
      }).addTo(zoneMarkerLayer);

      p.bindPopup(`<div><b>${z.name}</b><br>Area: ${z.area}<br>NDVI: ${z.ndvi}<br>Status: ${z.status}</div>`);
      p.on('click', () => selectZone(i));
      polys.push(p);
    });

    let plantMap = L.map('plantMap', {
      zoomControl: true,
      layers: [L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')]
    }).setView([10.25, 125.00], 10);

    // Removed the background boxes from the plantMap as well
    zones.forEach(z => {
      L.circleMarker([z.lat, z.lng], {
        radius: 4,
        color: '#1e9e62',
        fillOpacity: 0.3,
        interactive: false
      }).addTo(plantMap);
    });
    let pMarkers = [];
    plantSites.forEach((s, i) => {
      let ic = L.divIcon({
        html: `<div style="width:30px;height:30px;border-radius:50%;background:${s.color};border:2px solid white;display:flex;align-items:center;justify-content:center;font-weight:700;color:white;">${i+1}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      });
      let m = L.marker([s.lat, s.lng], {
        icon: ic
      }).addTo(plantMap);
      m.bindPopup(`<b>${s.name}</b><br>Suitability: ${s.score}%<br>Priority: ${s.priority}`);
      pMarkers.push(m);
    });

    window.flyTo = (i) => selectZone(i);
    window.closeRightPanel = () => {
      document.getElementById('mapRightPanel').classList.remove('open');
      document.getElementById('delineationInfoCard').style.display = 'none';
      selectedDrawnIndex = -1;
      setTimeout(() => mainMap.invalidateSize(), 300);
    };
    window.flyPlant = (i) => {
      plantMap.flyTo([plantSites[i].lat, plantSites[i].lng], 11);
      pMarkers[i].openPopup();
    };
    window.setBase = (t, btn) => {
      document.querySelectorAll('.layer-btn, .layer-option').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      document.getElementById('layerMenu')?.classList.remove('show');
      mainMap.removeLayer(curBase);
      curBase = t === 'osm' ? osmL : t === 'topo' ? topoL : satL;
      mainMap.addLayer(curBase);
    };

    function collectVertexCoords() {
      const coords = [];
      const add = (item) => {
        if (!item) return;
        if (Array.isArray(item[0])) {
          item.forEach(add);
        } else {
          coords.push(item);
        }
      };
      drawnFeatures.forEach(f => add(f.coords));
      add(currentFeature?.coords);
      return coords;
    }

    function getSnappedLatLng(latlng) {
      const vertices = collectVertexCoords();
      let best = null;
      let bestDist = snapPixelThreshold;

      const mousePixel = mainMap.latLngToContainerPoint(latlng);

      vertices.forEach(pt => {
        const candidate = L.latLng(pt[0], pt[1]);
        const vertexPixel = mainMap.latLngToContainerPoint(candidate);
        const pixelDist = mousePixel.distanceTo(vertexPixel);

        if (pixelDist < bestDist) {
          bestDist = pixelDist;
          best = candidate;
        }
      });
      return best;
    }

    let drawingMode = null;
    let isDrawing = false;
    let currentFeature = null;
    let drawnFeatures = [];
    let featureHistory = [];
    let historyIndex = -1;
    let currentSelectedZoneIndex = -1;
    let selectedDrawnIndex = -1;

    function loadSavedDelineations() {
      if (!Array.isArray(savedDelineations) || savedDelineations.length === 0) {
        return;
      }

      savedDelineations.forEach(record => {
        if (Array.isArray(record.features)) {
          record.features.forEach(feature => {
            drawnFeatures.push({
              ...feature,
              label: record.name,
              notes: record.notes,
              delineation_id: record.id,
              is_approved: !!record.is_approved,
              is_rejected: !!record.is_rejected,
              is_own: true,
            });
          });
        }
      });

      redrawFeatures();
    }

    function pushDelineationFeatures(record, options = {}) {
      if (!Array.isArray(record.features)) return;
      record.features.forEach(feature => {
        drawnFeatures.push({
          ...feature,
          label: record.name,
          notes: record.notes,
          delineation_id: record.id,
          is_approved: !!record.is_approved,
          is_rejected: !!record.is_rejected,
          rejection_notes: record.rejection_notes || null,
          is_own: !!options.is_own,
          created_by: options.created_by || null,
        });
      });
    }

    function loadDelineationsOnMap() {
      drawnFeatures = [];
      if (Array.isArray(savedDelineations)) {
        savedDelineations.forEach(record => pushDelineationFeatures(record, {
          is_own: true
        }));
      }
      if (Array.isArray(approvedDelineations)) {
        approvedDelineations.forEach(record => {
          pushDelineationFeatures(record, {
            is_own: false,
            created_by: record.user ? record.user.name : 'Community',
          });
        });
      }
      redrawFeatures();
    }

    loadDelineationsOnMap();

    function selectZone(i) {
      currentSelectedZoneIndex = i;
      let z = zones[i];
      document.querySelectorAll('.zone-row').forEach((r, j) => r.classList.toggle('sel', j === i));
      document.getElementById('dName').textContent = z.name;
      document.getElementById('dArea').textContent = z.area;
      document.getElementById('dNDVI').textContent = z.ndvi;
      document.getElementById('dNDVI').className = `d-val ${z.sc}`;
      document.getElementById('dStatus').textContent = z.status;
      document.getElementById('dStatus').className = `d-val ${z.sc}`;
      document.getElementById('dGenus').textContent = z.genus;
      document.getElementById('dScan').textContent = z.scan;

      const panel = document.getElementById('mapRightPanel');
      if (!panel.classList.contains('open')) {
        panel.classList.add('open');
        setTimeout(() => mainMap.invalidateSize(), 300);
      }
      selectedDrawnIndex = -1;
      document.getElementById('delineationInfoCard').style.display = 'none';
      document.getElementById('zoneDetailsContent').style.display = 'block';
      mainMap.flyTo([z.lat, z.lng], 10, {
        duration: 1
      });
      polys[i].openPopup();
    }

    function selectDrawnFeature(i) {
      const feature = drawnFeatures[i];
      if (!feature) return;
      selectedDrawnIndex = i;

      document.getElementById('mapRightPanel').classList.add('open');
      document.getElementById('delineationInfoCard').style.display = 'block';
      document.getElementById('zoneDetailsContent').style.display = 'none';
      document.getElementById('delineationFeatureType').textContent = feature.type;
      document.getElementById('delineationFeatureCoords').textContent = feature.type === 'point' ?
        feature.coords.join(', ') :
        feature.coords.slice(0, 3).map(c => c.join(', ')).join(' | ') + (feature.coords.length > 3 ? ' ...' : '');
      document.getElementById('delineationFeatureLabel').textContent = feature.label || '-';
      document.getElementById('delineationLabel').value = feature.label || '';
      document.getElementById('delineationNotes').value = feature.notes || '';

      const statusEl = document.getElementById('delineationReviewStatus');
      const rejectionBox = document.getElementById('delineationRejectionBox');
      const rejectionNotes = document.getElementById('delineationRejectionNotes');
      let statusLabel = 'Pending review';
      let statusClass = 'ca';
      if (feature.is_rejected) {
        statusLabel = 'Rejected';
        statusClass = 'cr';
      } else if (feature.is_approved) {
        statusLabel = feature.is_own ? 'Approved' : 'Approved (community)';
        statusClass = 'cg';
      }
      statusEl.textContent = statusLabel;
      statusEl.className = `d-val ${statusClass}`;
      if (feature.is_rejected && feature.rejection_notes) {
        rejectionBox.style.display = 'block';
        rejectionNotes.textContent = feature.rejection_notes;
      } else {
        rejectionBox.style.display = 'none';
        rejectionNotes.textContent = '';
      }
    }

    function validateDelineationMeta() {
      const labelEl = document.getElementById('delineationLabel');
      const notesEl = document.getElementById('delineationNotes');
      const label = labelEl?.value.trim();
      const notes = notesEl?.value.trim();

      const labelValid = Boolean(label);
      const notesValid = Boolean(notes);

      labelEl?.classList.toggle('input-error', !labelValid);
      notesEl?.classList.toggle('input-error', !notesValid);

      if (!labelValid || !notesValid) {
        alert('Please fill in both Name / Label and Notes before saving.');
        if (!labelValid) {
          labelEl?.focus();
        } else {
          notesEl?.focus();
        }
        return null;
      }

      return {
        name: label,
        notes
      };
    }

    function saveDelineationMeta() {
      if (selectedDrawnIndex < 0) return;
      const meta = validateDelineationMeta();
      if (!meta) return;

      const feature = drawnFeatures[selectedDrawnIndex];
      feature.label = meta.name;
      feature.notes = meta.notes;
      alert('Delineation details saved.');
    }

    window.removeCurrentDelineation = async function() {
      if (selectedDrawnIndex < 0) return;

      const feature = drawnFeatures[selectedDrawnIndex];
      const delineationId = feature?.delineation_id;

      // If this feature came from a saved draft delineation record, deleting it must happen server-side.
      // Otherwise it will reappear on refresh (because it is loaded again from the DB).
      if (delineationId && feature?.is_own && !feature?.is_approved) {
        const ok = confirm('Remove this draft delineation? This will permanently delete it.');
        if (!ok) return;

        try {
          const response = await fetch(`${deleteDelineationBaseUrl}/${delineationId}`, {
            method: 'DELETE',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
          });

          const payload = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(payload.message || 'Unable to delete delineation.');
          }

          alert(payload.message || 'Delineation deleted.');
          window.location.reload();
          return;
        } catch (error) {
          console.error('Delete delineation failed:', error);
          alert('Unable to delete delineation. Please try again.');
          return;
        }
      }

      // Unsaved / in-memory only: remove locally.
      drawnFeatures.splice(selectedDrawnIndex, 1);
      selectedDrawnIndex = -1;
      currentFeature = null;
      saveDrawingStateToHistory();
      redrawFeatures();
      document.getElementById('delineationInfoCard').style.display = 'none';
      document.getElementById('mapRightPanel').classList.remove('open');
    };

    function saveDrawingStateToHistory() {
      // Save both completed features and current feature being drawn
      const state = {
        drawnFeatures: JSON.parse(JSON.stringify(drawnFeatures)),
        currentFeature: currentFeature ? JSON.parse(JSON.stringify(currentFeature)) : null
      };
      // Clear redo history and add new state
      featureHistory = featureHistory.slice(0, historyIndex + 1);
      featureHistory.push(state);
      historyIndex = featureHistory.length - 1;
      updateHistoryButtons();
    }

    function updateHistoryButtons() {
      const undoBtn = document.getElementById('undoBtn');
      const redoBtn = document.getElementById('redoBtn');

      undoBtn.disabled = historyIndex <= 0;
      redoBtn.disabled = historyIndex >= featureHistory.length - 1;

      undoBtn.style.opacity = undoBtn.disabled ? '0.5' : '1';
      redoBtn.style.opacity = redoBtn.disabled ? '0.5' : '1';
      undoBtn.style.cursor = undoBtn.disabled ? 'not-allowed' : 'pointer';
      redoBtn.style.cursor = redoBtn.disabled ? 'not-allowed' : 'pointer';
    }
//script for delenation
    window.showEditMode = () => {
      const toolbar = document.getElementById('delineationToolbar');
      const drawTypeSelect = document.getElementById('drawTypeSelect');
      const drawModeButtons = document.querySelectorAll('.draw-btn');
      const isOpening = !toolbar.classList.contains('active');

      toolbar.classList.toggle('active');

      if (isOpening) {
        const leftPanel = document.querySelector('.left-panel');
        if (leftPanel) leftPanel.classList.add('hide');
        if (drawTypeSelect) {
          drawTypeSelect.disabled = false;
          drawTypeSelect.value = '';
          drawingMode = null;
          drawModeButtons.forEach(btn => btn.classList.remove('active'));
        }
        // Initialize history with empty state including the current drawing context
        if (featureHistory.length === 0) {
          featureHistory = [{
            drawnFeatures: JSON.parse(JSON.stringify(drawnFeatures)),
            currentFeature: null
          }];
          historyIndex = 0;
        }

        // Ensure satellite layer is active
        if (curBase !== satL) {
          mainMap.removeLayer(curBase);
          curBase = satL;
          mainMap.addLayer(curBase);
          document.querySelectorAll('.layer-btn, .layer-option').forEach(b => b.classList.remove('active'));
        }

        // Zoom to Southern Leyte region with satellite view
        mainMap.flyTo([10.35, 124.75], 12, {
          duration: 0.8
        });
        updateHistoryButtons();
      } else {
        drawingMode = null;
        const drawTypeSelect = document.getElementById('drawTypeSelect');
        if (drawTypeSelect) drawTypeSelect.disabled = true;
        const leftPanel = document.querySelector('.left-panel');
        if (leftPanel) leftPanel.classList.remove('hide');
        mainMap.dragging.enable();
      }
    };

    const drawTypeSelect = document.getElementById('drawTypeSelect');
    const drawModeButtons = document.querySelectorAll('.draw-btn');

    drawModeButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        const mode = this.dataset.mode;
        if (!mode) return;
        const isAlreadyActive = this.classList.contains('active');

        if (isAlreadyActive) {
          drawingMode = null;
          if (drawTypeSelect) drawTypeSelect.value = '';
          drawModeButtons.forEach(b => b.classList.remove('active'));
          return;
        }

        drawingMode = mode;
        if (drawTypeSelect) drawTypeSelect.value = mode;
        drawModeButtons.forEach(b => b.classList.toggle('active', b === this));
      });
    });

    drawTypeSelect?.addEventListener('change', function() {
      drawingMode = this.value;
      drawModeButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.mode === drawingMode));
    });

    document.getElementById('undoBtn').addEventListener('click', () => {
      if (historyIndex > 0) {
        historyIndex--;
        const state = featureHistory[historyIndex];
        drawnFeatures = JSON.parse(JSON.stringify(state.drawnFeatures));
        currentFeature = state.currentFeature ? JSON.parse(JSON.stringify(state.currentFeature)) : null;
        redrawFeatures();
        updateHistoryButtons();
      }
    });

    document.getElementById('redoBtn').addEventListener('click', () => {
      if (historyIndex < featureHistory.length - 1) {
        historyIndex++;
        const state = featureHistory[historyIndex];
        drawnFeatures = JSON.parse(JSON.stringify(state.drawnFeatures));
        currentFeature = state.currentFeature ? JSON.parse(JSON.stringify(state.currentFeature)) : null;
        redrawFeatures();
        updateHistoryButtons();
      }
    });

    async function persistDelineation() {
      if (!drawnFeatures.length) {
        alert('No delineation features to save.');
        return;
      }

      const meta = validateDelineationMeta();
      if (!meta) return;

      try {
        const response = await fetch(saveDelineationUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: JSON.stringify({
            features: drawnFeatures,
            name: meta.name,
            notes: meta.notes,
          })
        });

        const payload = await response.json();
        if (!response.ok) {
          throw new Error(payload.message || 'Unable to save delineation.');
        }

        alert(payload.message || 'Delineation draft saved successfully.');
      } catch (error) {
        console.error('Delineation save failed:', error);
        alert('Unable to save delineation. Please try again.');
      }
    }

    document.getElementById('saveBtn').addEventListener('click', () => {
      persistDelineation();
    });

    const saveDelineationMetaBtn = document.getElementById('saveDelineationMetaBtn');
    if (saveDelineationMetaBtn) {
      saveDelineationMetaBtn.addEventListener('click', () => {
        saveDelineationMeta();
      });
    }

    function redrawFeatures() {
      // Clear only our overlay layers; keep the base tiles + controls intact.
      delineationLayer.clearLayers();
      vertexLayer.clearLayers();

      function getFeatureColor(f) {
        if (f.is_rejected) return '#d04030';
        if (f.is_approved) return '#1e9e62';
        if (f.is_own) return '#c07818';
        return '#4ecdc4';
      }

      // Draw completed features
      drawnFeatures.forEach((f, i) => {
        const color = getFeatureColor(f);
        if (f.type === 'point') {
          const marker = L.circleMarker(f.coords, {
            radius: 7,
            color,
            fillColor: color,
            fillOpacity: 0.85,
          }).addTo(delineationLayer);
          marker.on('click', () => selectDrawnFeature(i));
        } else if (f.type === 'line') {
          const line = L.polyline(f.coords, {
            color,
            weight: 3,
          }).addTo(delineationLayer);
          line.on('click', () => selectDrawnFeature(i));
          // Vertex markers are visually nice but expensive; only render them in edit mode.
          if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
            f.coords.forEach(coord => L.circleMarker(coord, {
              radius: 3,
              color,
              weight: 1,
              fillColor: color,
              fillOpacity: 0.9,
              interactive: false,
            }).addTo(vertexLayer));
          }
        } else if (f.type === 'area') {
          const polygon = L.polygon(f.coords, {
            color,
            fillColor: color,
            fillOpacity: 0.45,
            weight: 2,
          }).addTo(delineationLayer);
          polygon.on('click', () => selectDrawnFeature(i));
          if (document.getElementById('delineationToolbar')?.classList.contains('active')) {
            f.coords.forEach(coord => L.circleMarker(coord, {
              radius: 3,
              color,
              weight: 1,
              fillColor: color,
              fillOpacity: 0.9,
              interactive: false,
            }).addTo(vertexLayer));
          }
        }
      });

      // Draw current feature being drawn
      if (currentFeature) {
        if (currentFeature.type === 'point') {
          L.circleMarker(currentFeature.coords, {
            radius: 7,
            color: '#ff6b6b',
            fillColor: '#ff6b6b',
            fillOpacity: 0.6,
            interactive: false,
          }).addTo(delineationLayer);
        } else if (currentFeature.type === 'line') {
          L.polyline(currentFeature.coords, {
            color: '#ff6b6b',
            opacity: 0.7
          }).addTo(delineationLayer);
          currentFeature.coords.forEach(coord => L.circleMarker(coord, {
            radius: 3,
            color: '#ff6b6b',
            weight: 1,
            fillColor: '#ff6b6b',
            fillOpacity: 0.9,
            interactive: false,
          }).addTo(vertexLayer));
        } else if (currentFeature.type === 'area') {
          if (currentFeature.coords.length >= 3) {
            L.polygon(currentFeature.coords, {
              color: '#4ecdc4',
              fillOpacity: 0.3
            }).addTo(delineationLayer);
          } else {
            L.polyline(currentFeature.coords, {
              color: '#4ecdc4',
              opacity: 0.7
            }).addTo(delineationLayer);
          }
          currentFeature.coords.forEach(coord => L.circleMarker(coord, {
            radius: 3,
            color: '#4ecdc4',
            weight: 1,
            fillColor: '#4ecdc4',
            fillOpacity: 0.9,
            interactive: false,
          }).addTo(vertexLayer));
        }
      }
    }

    mainMap.on('click', (e) => {
      if (!drawingMode) return;
      const snappedLatLng = getSnappedLatLng(e.latlng) || e.latlng;
      if (!currentFeature) {
        currentFeature = {
          type: drawingMode,
          coords: []
        };
      }
      if (drawingMode === 'point') {
        currentFeature.coords = [snappedLatLng.lat, snappedLatLng.lng];
        drawnFeatures.push(currentFeature);
        currentFeature = null;
        saveDrawingStateToHistory();
        redrawFeatures();
        selectDrawnFeature(drawnFeatures.length - 1);
      } else if (drawingMode === 'line') {
        currentFeature.coords.push([snappedLatLng.lat, snappedLatLng.lng]);
        // Save state after each point added
        saveDrawingStateToHistory();

        // Check if close to first point to close the delineation
        if (currentFeature.coords.length > 2) {
          let first = currentFeature.coords[0];
          const firstPixel = mainMap.latLngToContainerPoint(L.latLng(first[0], first[1]));
          const currentPixel = mainMap.latLngToContainerPoint(snappedLatLng);
          const pixelDist = firstPixel.distanceTo(currentPixel);

          if (pixelDist < snapPixelThreshold * 2) { // use 2x threshold for closing
            currentFeature.coords[currentFeature.coords.length - 1] = [first[0], first[1]];
            currentFeature.type = 'area';
            drawnFeatures.push(currentFeature);
            currentFeature = null;
            saveDrawingStateToHistory();
            drawingMode = null; // Exit drawing mode
            mainMap.dragging.enable();
            document.querySelectorAll('.draw-btn').forEach(b => b.classList.remove('active'));
            redrawFeatures();
            selectDrawnFeature(drawnFeatures.length - 1);
            return;
          }
        }
      } else if (drawingMode === 'area') {
        currentFeature.coords.push([snappedLatLng.lat, snappedLatLng.lng]);
        // Save state after each point added
        saveDrawingStateToHistory();

        // Check if close to first point to close early
        if (currentFeature.coords.length > 2) {
          let first = currentFeature.coords[0];
          const firstPixel = mainMap.latLngToContainerPoint(L.latLng(first[0], first[1]));
          const currentPixel = mainMap.latLngToContainerPoint(snappedLatLng);
          const pixelDist = firstPixel.distanceTo(currentPixel);

          if (pixelDist < snapPixelThreshold * 2) {
            currentFeature.coords[currentFeature.coords.length - 1] = [first[0], first[1]];
            drawnFeatures.push(currentFeature);
            currentFeature = null;
            saveDrawingStateToHistory();
            drawingMode = null;
            mainMap.dragging.enable();
            document.querySelectorAll('.draw-btn').forEach(b => b.classList.remove('active'));
            redrawFeatures();
            selectDrawnFeature(drawnFeatures.length - 1);
            return;
          }
        }
      }
      redrawFeatures();
    });

    // Throttle snapping updates to one per animation frame (mousemove can fire *a lot*).
    let snapRaf = 0;
    let lastMouseLatLng = null;
    mainMap.on('mousemove', (e) => {
      lastMouseLatLng = e.latlng;
      if (snapRaf) return;
      snapRaf = requestAnimationFrame(() => {
        snapRaf = 0;
        if (!drawingMode || !lastMouseLatLng) {
          snapMarker.setStyle({
            opacity: 0,
            fillOpacity: 0
          });
          return;
        }
        const snapped = getSnappedLatLng(lastMouseLatLng);
        if (snapped) {
          snapMarker.setLatLng(snapped);
          snapMarker.setStyle({
            opacity: 1,
            fillOpacity: 0.35
          });
        } else {
          snapMarker.setStyle({
            opacity: 0,
            fillOpacity: 0
          });
        }
      });
    });

    window.toggleLayerMenu = () => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      const isOpen = menu.classList.toggle('show');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    document.addEventListener('click', (event) => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      if (!menu.contains(event.target) && !toggle.contains(event.target)) {
        menu.classList.remove('show');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    window.show = (id, btn) => {
      document.querySelectorAll('.h-tab').forEach(b => b.classList.remove('active'));
      if (btn && btn.classList) btn.classList.add('active');
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      document.getElementById('v-' + id).classList.add('on');
      const navDropdown = document.getElementById('navDropdown');
      if (navDropdown) navDropdown.value = id;
      setTimeout(() => {
        if (id === 'map') mainMap.invalidateSize();
        if (id === 'planting') plantMap.invalidateSize();
      }, 150);
    };

    window.showClassify = () => {
      const classifyTab = Array.from(document.querySelectorAll('.h-tab')).find(b => {
        const onclick = b.getAttribute('onclick') || '';
        return onclick.includes("show('classify'");
      });
      if (classifyTab) {
        show('classify', classifyTab);
      } else {
        show('classify');
      }
      const fileInput = document.getElementById('fileInput');
      if (fileInput) {
        fileInput.click();
      }
    };

    window.showFromDropdown = (select) => {
      const id = select.value;
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      document.getElementById('v-' + id).classList.add('on');
      let btns = document.querySelectorAll('.h-tab');
      btns.forEach((b, i) => {
        b.classList.remove('active');
        if ((id === 'map' && i === 0) || (id === 'classify' && i === 2) || (id === 'planting' && i === 1)) b.classList.add('active');
      });
      setTimeout(() => {
        if (id === 'map') mainMap.invalidateSize();
        if (id === 'planting') plantMap.invalidateSize();
      }, 150);
    };

    // NEW: Handle image upload and auto-show classification
    window.handleImageUpload = (e) => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => {
        const img = document.getElementById('imgView');
        img.src = ev.target.result;
        img.style.display = 'block';
        // Hide placeholder message
        const placeholderDiv = document.querySelector('#imagePreviewArea .no-image-message');
        if (placeholderDiv) placeholderDiv.style.display = 'none';
        // Show classification results instantly
        document.getElementById('classResult').style.display = 'block';
        // Optional: collapse upload zone slightly but keep visible
        const uploadBox = document.getElementById('uploadBox');
        uploadBox.style.marginBottom = '8px';
      };
      reader.readAsDataURL(file);
    };

    window.addEventListener('resize', () => {
      setTimeout(() => {
        mainMap.invalidateSize();
        plantMap.invalidateSize();
      }, 100);
    });
    new Chart(document.getElementById('pieC'), {
      type: 'doughnut',
      data: {
        labels: ['Rhizophora', 'Avicennia', 'Sonneratia', 'Bruguiera', 'Others'],
        datasets: [{
          data: [34, 22, 18, 14, 12],
          backgroundColor: ['#1e9e62', '#5ab8de', '#f4a840', '#a070e0', '#c0c8b8'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '64%'
      }
    });
    new Chart(document.getElementById('trendC'), {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          data: [],
          borderColor: '#1e9e62',
          backgroundColor: 'rgba(30,158,98,.1)',
          fill: true,
          tension: .4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            ticks: {
              callback: v => (v / 1000).toFixed(0) + 'k'
            },
            min: 45000
          }
        }
      }
    });
    new Chart(document.getElementById('plantTrendC'), {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          data: [],
          borderColor: '#1e9e62',
          backgroundColor: 'rgba(30,158,98,.1)',
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            ticks: {
              callback: v => (v / 1000).toFixed(0) + 'k'
            }
          }
        }
      }
    });
    const plantListDiv = document.getElementById('plantSitesList');
    plantSites.forEach((s, i) => {
      const card = document.createElement('div');
      card.className = 'site-card';
      card.setAttribute('onclick', `flyPlant(${i})`);
      card.innerHTML = `<div class="sc-head"><div class="sc-rank ${s.priority==='Critical'?'rg':(s.priority==='High'?'ra':'rb')}">${i+1}</div><div class="sc-name">${s.name}</div></div><div class="sc-sp">${s.priority} priority zone</div><div class="sc-foot"><div class="sbar"><div class="strack"><div class="sfill" style="width:${s.score}%;background:${s.color}"></div></div><span style="font-size:10px;font-weight:700;color:${s.color}">${s.score}%</span></div><span class="ptag" style="background:${s.color}20;border-color:${s.color};color:${s.color}">${s.priority}</span></div>`;
      plantListDiv.appendChild(card);
    });
    // Avoid ResizeObserver -> invalidateSize() feedback loops (can cause constant reflow).
    // We already invalidate on tab switches / panel open-close / window resize.

    // Notification dropdown toggle
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationToggle && notificationDropdown) {
      notificationToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
        if (typeof profileDropdown !== 'undefined' && profileDropdown) {
          profileDropdown.classList.remove('active');
        }
      });

      document.addEventListener('click', function(e) {
        if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
          notificationDropdown.classList.remove('active');
        }
      });

      notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }

    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileToggle && profileDropdown) {
      profileToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('active');
        notificationDropdown?.classList.remove('active');
      });

      document.addEventListener('click', function(e) {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
          profileDropdown.classList.remove('active');
        }
      });

      profileDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
      });
    }
  </script>
</body>

</html>