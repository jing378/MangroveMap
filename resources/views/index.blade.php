<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#1e9e62">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/manifest.json">
  <title>MangroveMap — Smart Classifier</title>
  <link rel="icon" type="image/png" href="/icon-192.png" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
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
      font-family: 'Inter', sans-serif;
      background: linear-gradient(180deg, #e9f6ec 0%, #d9eedf 45%, #6ddd87 100%);
      color: #1a2e1a;
      overflow: hidden;
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
    }    body {
      height: 100%;
      font-family: 'Inter', sans-serif;      background: #f0f4f0;
      background: linear-gradient(180deg, #e9f6ec 0%, #d9eedf 45%, #6ddd87 100%);
      color: #1a2e1a;
      overflow: hidden;
    }

    .header {
      height: 54px;
      border-bottom: 1px solid #cfe5d5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 12px;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      gap: 8px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 700;
      font-size: 18px;
      white-space: nowrap;
    }

    .logo-mark {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: #1e9e62;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      color: #fff;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .user-section {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: .3;
      }
    }

    .btn {
      font-family: 'Inter', sans-serif;
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

    .app {
      position: fixed;
      top: 54px;
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

      .live span {
        display: none;
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

    .map-layer-control {
      position: absolute;
      top: 88px;
      left: 10px;
      z-index: 900;
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

    .map-layer-control .bi {
      font-size: 18px;
    }

    .layer-menu {
      position: absolute;
      top: 0;
      left: 100%;
      display: none;
      flex-direction: column;
      min-width: 120px;
      margin-left: 4px;
      background: #fff;
      border: 1px solid #e0e8e0;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
      padding: 4px;
    }

    .layer-menu.show {
      display: flex;
    }

    .layer-option {
      width: 100%;
      min-width: 120px;
      font-family: 'Inter', sans-serif;
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

    .layer-option.active {
      background: #edf7f2;
      color: #1e9e62;
      font-weight: 600;
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

    .mobile-install-btn {
      position: fixed;
      bottom: 145px;
      left: 12px;
      width: 48px;
      height: 48px;
      background: #1e9e62;
      color: white;
      border: none;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
      z-index: 10000;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .mobile-install-btn:active {
      transform: scale(0.9);
    }

    .mobile-install-btn i {
      font-size: 20px;
    }

    @media (min-width: 769px) {
      .mobile-install-btn {
        /* Optional: Hide on desktop if strictly for mobile, 
           but usually good to keep as an option */
      }
    }
  </style>
</head>

<body>
  <header class="header">
    <div class="logo">
      <div class="logo-mark"><i class="bi bi-leaf"></i></div>MangroveMap
    </div>
    <div class="header-right">
      <div class="user-section">
        @auth
        @include('components.notification-bell')
        <div style="display: flex; align-items: center; gap: 8px; padding-left: 8px; border-left: 1px solid #e0e8e0;">
          <span style="font-size: 13px; color: #666;">{{ Auth::user()->name }}</span>
          <div style="width: 28px; height: 28px; border-radius: 50%; background: #1e9e62; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600;">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
          </div>
          <form method="POST" action="{{ route('logout') }}" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-sm" style="background: #e8f1ed; color: #1e9e62; padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; font-weight: 600;">
              <i class="bi bi-box-arrow-right"></i> Logout
            </button>
          </form>
        </div>
        @else
        <button class="btn btn-g" onclick="window.location.href='/login'"><i class="bi bi-box-arrow-in-right"></i> Login</button>
        @endauth
      </div>
    </div>
  </header>

  <div class="app">
    <!-- COVERAGE MAP -->
    <div class="view on" id="v-map">

      <div class="main">
        <div class="map-wrap">
          <div id="mainMap"></div>
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

    <!-- Mobile Install Button -->
    <button id="mobileInstallBtn" class="mobile-install-btn" title="Install App">
      <i class="bi bi-phone"></i>
    </button>

    <!-- PLANTING SUITABILITY -->
    <div class="view" id="v-planting">

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
    const savedDelineations = @json($delineations);
    const zones = [];
    const plantSites = [];

    const zoneContainer = document.getElementById('zoneListContainer');
    if (zoneContainer) {
      zones.forEach((z, i) => {
        const div = document.createElement('div');
        div.className = `zone-row`;
        div.setAttribute('onclick', `flyTo(${i})`);
        div.innerHTML = `<div class="z-pip" style="background:${z.color}"></div><div class="z-name">${z.name}</div><div class="z-ha">${z.area.replace(/[^0-9k]/g,'')}</div><span class="z-chip ${z.status==='Healthy'?'cg':(z.status==='Degraded'?'cr':'ca')}">${z.status}</span>`;
        zoneContainer.appendChild(div);
      });
    }

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
    }).setView([10.25, 125.00], 11);
    let polys = [];
    zones.forEach((z, i) => {
      let p = L.polygon(z.shape, {
        color: z.color,
        fillColor: z.color,
        fillOpacity: .35,
        weight: 2
      }).addTo(mainMap);
      p.bindPopup(`<div><b>${z.name}</b><br>Area: ${z.area}<br>NDVI: ${z.ndvi}<br>Status: ${z.status}</div>`);
      p.on('click', () => selectZone(i));
      polys.push(p);
    });
    let plantMap = L.map('plantMap', {
      zoomControl: true,
      layers: [L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')]
    }).setView([10.25, 125.00], 11);
    zones.forEach(z => L.polygon(z.shape, {
      color: '#1e9e62',
      fillOpacity: .1,
      weight: 1,
      dashArray: '5,4'
    }).addTo(plantMap));
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

    function drawSavedDelineations() {
      if (!Array.isArray(savedDelineations) || savedDelineations.length === 0) {
        return;
      }

      savedDelineations.forEach((record) => {
        if (!Array.isArray(record.features)) {
          return;
        }

        record.features.forEach((feature) => {
          let layer = null;
          const popupContent = `<strong>${record.name || 'Saved Delineation'}</strong><br>${feature.type ? feature.type.toUpperCase() : 'Feature'}`;

          if (feature.type === 'point') {
            layer = L.marker(feature.coords).addTo(mainMap);
          } else if (feature.type === 'line') {
            layer = L.polyline(feature.coords, {
              color: '#4e7ef2'
            }).addTo(mainMap);
          } else if (feature.type === 'area') {
            layer = L.polygon(feature.coords, {
              color: '#4e7ef2',
              fillOpacity: 0.2
            }).addTo(mainMap);
          }

          if (layer) {
            layer.bindPopup(popupContent);
            layer.on('click', () => {
              // Show right panel with delineation details
              document.getElementById('dName').textContent = record.name || 'Saved Delineation';
              document.getElementById('dArea').textContent = record.area || 'N/A';
              document.getElementById('dNDVI').textContent = record.ndvi || 'N/A';
              document.getElementById('dNDVI').className = 'd-val';
              document.getElementById('dStatus').textContent = record.status || 'N/A';
              document.getElementById('dStatus').className = 'd-val';
              document.getElementById('dGenus').textContent = record.genus || 'N/A';
              document.getElementById('dScan').textContent = record.scan || 'N/A';

              const panel = document.getElementById('mapRightPanel');
              if (!panel.classList.contains('open')) {
                panel.classList.add('open');
                setTimeout(() => mainMap.invalidateSize(), 300);
              }

              // Zoom to feature location
              if (layer.getBounds) {
                // For polygons and polylines with getBounds method
                const bounds = layer.getBounds();
                mainMap.fitBounds(bounds, {
                  padding: [50, 50],
                  maxZoom: 13,
                  duration: 1
                });
              } else if (feature.coords && feature.coords.length > 0) {
                // For markers/points
                mainMap.flyTo(feature.coords, 12, {
                  duration: 1
                });
              }
            });
          }
        });
      });
    }

    drawSavedDelineations();

    function selectZone(i) {
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

      mainMap.flyTo([z.lat, z.lng], 10, {
        duration: 1
      });
      polys[i].openPopup();
    }
    window.flyTo = (i) => selectZone(i);
    window.closeRightPanel = () => {
      document.getElementById('mapRightPanel').classList.remove('open');
      setTimeout(() => mainMap.invalidateSize(), 300);
    };
    window.flyPlant = (i) => {
      plantMap.flyTo([plantSites[i].lat, plantSites[i].lng], 11);
      pMarkers[i].openPopup();
    };
    window.toggleLayerMenu = () => {
      const menu = document.getElementById('layerMenu');
      const toggle = document.getElementById('layerToggle');
      if (!menu || !toggle) return;
      const isOpen = menu.classList.toggle('show');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    window.showEditMode = () => {
      document.querySelectorAll('.map-layer-control button[id]').forEach(b => b.classList.remove('active'));
      const btn = document.getElementById('editModeBtn');
      if (btn) btn.classList.add('active');
    };

    window.showSuitability = () => {
      document.querySelectorAll('.map-layer-control button[id]').forEach(b => b.classList.remove('active'));
      const btn = document.getElementById('suitabilityBtn');
      if (btn) btn.classList.add('active');
    };

    window.showClassify = () => {
      document.querySelectorAll('.map-layer-control button[id]').forEach(b => b.classList.remove('active'));
      const btn = document.getElementById('classifyBtn');
      if (btn) btn.classList.add('active');
    };

    window.setBase = (t, btn) => {
      document.querySelectorAll('.layer-btn, .layer-option').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      mainMap.removeLayer(curBase);
      curBase = t === 'osm' ? osmL : t === 'topo' ? topoL : satL;
      mainMap.addLayer(curBase);
    };
    window.show = (id) => {
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      document.getElementById('v-' + id).classList.add('on');
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

        // Adjust zoom for mobile
        const isMobile = window.innerWidth <= 780;
        const currentZoom = mainMap.getZoom();
        const targetZoom = isMobile ? 10 : 11;

        if (currentZoom !== targetZoom) {
          mainMap.setZoom(targetZoom);
          plantMap.setZoom(targetZoom);
        }
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
        labels: ['2021', '2022', '2023', '2024', '2025', '2026'],
        datasets: [{
          data: [46178, 45900, 46360, 46820, 47100, 47382],
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
        labels: ['2021', '2022', '2023', '2024', '2025', '2026'],
        datasets: [{
          data: [46178, 45900, 46360, 46820, 47100, 47382],
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
    const mapObserver = new ResizeObserver(() => {
      mainMap.invalidateSize();
      plantMap.invalidateSize();
    });
    mapObserver.observe(document.getElementById('mainMap'));
    mapObserver.observe(document.getElementById('plantMap'));

    // Notification dropdown toggle
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationToggle && notificationDropdown) {
      notificationToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
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

    // PWA Logic
    let deferredPrompt;
    const mobileInstallBtn = document.getElementById('mobileInstallBtn');

    // Register Service Worker
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(reg => {
          console.log('SW registered:', reg.scope);
        }).catch(err => {
          console.log('SW registration failed:', err);
        });
      });
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
      if (mobileInstallBtn) mobileInstallBtn.style.display = 'flex';
    });

    if (mobileInstallBtn) {
      mobileInstallBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const {
          outcome
        } = await deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        deferredPrompt = null;
        mobileInstallBtn.style.display = 'none';
      });
    }

    window.addEventListener('appinstalled', () => {
      if (mobileInstallBtn) mobileInstallBtn.style.display = 'none';
      deferredPrompt = null;
      console.log('PWA was installed');
    });
  </script>
</body>

</html>