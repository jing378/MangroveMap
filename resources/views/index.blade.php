<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#144a2b">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="manifest" href="/manifest.json">
  <title>MangroveMap — Mangrove Mapping & Monitoring System</title>
  <link rel="icon" type="image/png" href="/icon-192.png" />
  
  <!-- Leaflet GIS & Libraries -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

  <style>
    /* CSS Reset & Variables */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --primary-forest: #0e3820;
      --primary-dark: #144a2b;
      --primary-green: #1e9e62;
      --primary-light: #2ecc71;
      --primary-mint: #eaf6ef;
      --primary-accent: #25a86b;
      
      --earth-sand: #f5f2eb;
      --earth-border: #e2ebe3;
      --earth-card: #f9fbf9;
      --text-dark: #12281b;
      --text-muted: #567262;
      --text-subtle: #7e998a;
      
      --shadow-sm: 0 2px 8px rgba(14, 56, 32, 0.05);
      --shadow-md: 0 8px 24px rgba(14, 56, 32, 0.08);
      --shadow-lg: 0 16px 40px rgba(14, 56, 32, 0.12);
      --radius-sm: 8px;
      --radius-md: 14px;
      --radius-lg: 20px;
      --radius-xl: 28px;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #f7faf7;
      color: var(--text-dark);
      line-height: 1.5;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Minimal Modern Navigation Bar */
    .site-nav {
      position: sticky;
      top: 0;
      width: 100%;
      height: 68px;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(30, 158, 98, 0.18);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      z-index: 9999 !important;
      transition: all 0.25s ease;
    }

    .brand-wrap {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: inherit;
    }

    .brand-logo-icon {
      width: 36px;
      height: 36px;
      background: linear-gradient(135deg, #144a2b 0%, #1e9e62 100%);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      font-size: 19px;
      box-shadow: 0 4px 12px rgba(30, 158, 98, 0.28);
    }

    .brand-text {
      display: flex;
      flex-direction: column;
    }

    .brand-title {
      font-size: 18px;
      font-weight: 800;
      letter-spacing: -0.02em;
      color: var(--primary-forest);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .brand-title span {
      color: var(--primary-green);
    }

    .brand-badge {
      font-size: 10.5px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--text-muted);
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 28px;
      list-style: none;
    }

    .nav-links a {
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      color: #375342;
      transition: color 0.15s ease;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .nav-links a:hover {
      color: var(--primary-green);
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-btn-explore {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: var(--primary-green);
      color: #ffffff !important;
      font-size: 13.5px;
      font-weight: 600;
      padding: 8px 18px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      border: 1px solid var(--primary-green);
      transition: all 0.2s ease;
      box-shadow: 0 2px 8px rgba(30, 158, 98, 0.25);
    }

    .nav-btn-explore:hover {
      background: #188653;
      border-color: #188653;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(30, 158, 98, 0.35);
    }

    .nav-btn-login {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #f0f7f2;
      color: var(--primary-dark) !important;
      font-size: 13.5px;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      border: 1px solid rgba(30, 158, 98, 0.25);
      transition: all 0.15s ease;
    }

    .nav-btn-login:hover {
      background: #e3f2e8;
      border-color: var(--primary-green);
    }

    .nav-mobile-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 24px;
      color: var(--primary-forest);
      cursor: pointer;
      padding: 4px;
    }

    /* Polished Hero Section */
    .hero-section {
      position: relative;
      min-height: 86vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background-image: url("{{ asset('images/mangrove-login.jpg') }}");
      background-size: cover;
      background-position: center center;
      background-repeat: no-repeat;
      padding: 80px 24px 70px 24px;
      overflow: hidden;
    }

    .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(
        180deg,
        rgba(10, 32, 20, 0.76) 0%,
        rgba(12, 38, 24, 0.85) 50%,
        rgba(9, 28, 17, 0.94) 100%
      );
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 960px;
      margin: 0 auto;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .hero-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(30, 158, 98, 0.22);
      border: 1px solid rgba(46, 204, 113, 0.45);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      padding: 6px 16px;
      border-radius: 999px;
      color: #bbf7d0;
      font-size: 13px;
      font-weight: 600;
      letter-spacing: 0.02em;
      margin-bottom: 22px;
      animation: fadeInDown 0.8s ease;
    }

    .hero-eyebrow .pulse-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #2ecc71;
      box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.35);
      animation: pulseGlow 2s infinite;
    }

    @keyframes pulseGlow {
      0%, 100% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.6; }
    }

    .hero-title {
      font-size: clamp(34px, 5.2vw, 56px);
      font-weight: 800;
      line-height: 1.15;
      letter-spacing: -0.03em;
      color: #ffffff;
      margin-bottom: 20px;
      text-shadow: 0 2px 14px rgba(0, 0, 0, 0.35);
    }

    .hero-title-highlight {
      background: linear-gradient(120deg, #4ade80 0%, #22c55e 50%, #86efac 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      display: inline-block;
    }

    .hero-description {
      font-size: clamp(16px, 1.9vw, 19px);
      line-height: 1.65;
      color: #e2f0e6;
      max-width: 720px;
      margin-bottom: 34px;
      text-shadow: 0 1px 6px rgba(0, 0, 0, 0.25);
      font-weight: 400;
    }

    .hero-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 50px;
    }

    .btn-hero-primary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      background: linear-gradient(135deg, #1e9e62 0%, #15803d 100%);
      color: #ffffff;
      font-size: 16px;
      font-weight: 700;
      padding: 14px 34px;
      border-radius: var(--radius-md);
      text-decoration: none;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 24px rgba(30, 158, 98, 0.45);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      cursor: pointer;
    }

    .btn-hero-primary:hover {
      background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(30, 158, 98, 0.6);
      color: #ffffff;
    }

    .btn-hero-secondary {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      background: rgba(255, 255, 255, 0.12);
      color: #ffffff;
      font-size: 15.5px;
      font-weight: 600;
      padding: 14px 28px;
      border-radius: var(--radius-md);
      text-decoration: none;
      border: 1px solid rgba(255, 255, 255, 0.28);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .btn-hero-secondary:hover {
      background: rgba(255, 255, 255, 0.22);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-2px);
      color: #ffffff;
    }

    /* Live Hero Stats Metrics */
    .hero-stats-strip {
      width: 100%;
      max-width: 900px;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      background: rgba(14, 46, 26, 0.65);
      border: 1px solid rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      padding: 18px 24px;
      border-radius: var(--radius-lg);
      box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
    }

    .hero-stat-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      border-right: 1px solid rgba(255, 255, 255, 0.12);
      padding: 0 8px;
    }

    .hero-stat-card:last-child {
      border-right: none;
    }

    .stat-val {
      font-size: clamp(22px, 2.8vw, 30px);
      font-weight: 800;
      color: #ffffff;
      line-height: 1.1;
      margin-bottom: 4px;
      letter-spacing: -0.02em;
    }

    .stat-val span {
      color: #4ade80;
      font-size: 0.75em;
      margin-left: 2px;
    }

    .stat-label {
      font-size: 11.5px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #cbd5e1;
    }

    /* Section Headers */
    .section-wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 80px 24px;
    }

    .section-header {
      text-align: center;
      margin-bottom: 50px;
    }

    .section-kicker {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--primary-green);
      background: var(--primary-mint);
      border: 1px solid rgba(30, 158, 98, 0.25);
      padding: 5px 14px;
      border-radius: 999px;
      margin-bottom: 12px;
    }

    .section-title {
      font-size: clamp(28px, 3.6vw, 38px);
      font-weight: 800;
      color: var(--primary-forest);
      letter-spacing: -0.025em;
      margin-bottom: 14px;
    }

    .section-subtitle {
      font-size: 16px;
      line-height: 1.6;
      color: var(--text-muted);
      max-width: 680px;
      margin: 0 auto;
    }

    /* Polished Feature Section */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 28px;
    }

    .feature-card {
      background: #ffffff;
      border: 1px solid var(--earth-border);
      border-radius: var(--radius-lg);
      padding: 34px 28px;
      display: flex;
      flex-direction: column;
      position: relative;
      transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #144a2b, #1e9e62);
      opacity: 0;
      transition: opacity 0.25s ease;
    }

    .feature-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(30, 158, 98, 0.35);
    }

    .feature-card:hover::before {
      opacity: 1;
    }

    .feature-icon-box {
      width: 58px;
      height: 58px;
      border-radius: var(--radius-md);
      background: linear-gradient(135deg, #eaf6ef 0%, #d8eee1 100%);
      border: 1px solid rgba(30, 158, 98, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      color: var(--primary-green);
      margin-bottom: 22px;
      transition: transform 0.25s ease;
    }

    .feature-card:hover .feature-icon-box {
      transform: scale(1.08) rotate(3deg);
      background: linear-gradient(135deg, #1e9e62 0%, #144a2b 100%);
      color: #ffffff;
    }

    .feature-title {
      font-size: 20px;
      font-weight: 700;
      color: var(--primary-forest);
      margin-bottom: 12px;
      letter-spacing: -0.015em;
    }

    .feature-desc {
      font-size: 14.5px;
      line-height: 1.65;
      color: var(--text-muted);
      margin-bottom: 22px;
      flex-grow: 1;
    }

    .feature-points {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-top: 1px solid #f0f4f0;
      padding-top: 18px;
    }

    .feature-points li {
      font-size: 13px;
      font-weight: 500;
      color: #3b5445;
      display: flex;
      align-items: center;
      gap: 9px;
    }

    .feature-points li i {
      color: var(--primary-green);
      font-size: 14px;
    }

    /* Interactive GIS Map Section Frame */
    .map-section {
      background: #ffffff;
      border-top: 1px solid var(--earth-border);
      border-bottom: 1px solid var(--earth-border);
      padding: 70px 24px 80px 24px;
    }

    .map-showcase-container {
      max-width: 1280px;
      margin: 0 auto;
    }

    .map-browser-frame {
      background: #ffffff;
      border: 1px solid rgba(30, 158, 98, 0.22);
      border-radius: var(--radius-xl);
      box-shadow: 0 18px 50px rgba(14, 56, 32, 0.12);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .map-frame-toolbar {
      background: #f7faf8;
      border-bottom: 1px solid #e0ebe2;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap;
    }

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .live-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #e6f6ee;
      border: 1px solid #bce6cf;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      color: #147a46;
    }

    .live-status-pill .indicator-dot {
      width: 7px;
      height: 7px;
      background: #1e9e62;
      border-radius: 50%;
      animation: pulseGlow 2s infinite;
    }

    .map-frame-heading {
      font-size: 14.5px;
      font-weight: 700;
      color: var(--primary-forest);
    }

    .toolbar-center {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .view-switch-btn {
      font-family: inherit;
      font-size: 13px;
      font-weight: 600;
      border: 1px solid #cedecf;
      background: #ffffff;
      color: #3d5e49;
      padding: 6px 14px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
    }

    .view-switch-btn:hover {
      background: #edf6f0;
      border-color: var(--primary-green);
    }

    .view-switch-btn.active {
      background: var(--primary-green);
      color: #ffffff;
      border-color: var(--primary-green);
      box-shadow: 0 2px 6px rgba(30, 158, 98, 0.3);
    }

    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .map-action-btn {
      font-family: inherit;
      font-size: 13px;
      font-weight: 500;
      border: 1px solid #d5e5d8;
      background: #ffffff;
      color: #3b5847;
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.15s ease;
      text-decoration: none;
    }

    .map-action-btn:hover {
      background: #eef6f0;
      color: var(--primary-forest);
      border-color: var(--primary-green);
    }

    /* Embedded Map View Styles (Preserving All GIS Capabilities) */
    .app-embed {
      position: relative;
      width: 100%;
      height: 720px;
      background: #eaf1eb;
      overflow: hidden;
      display: flex;
    }

    .view {
      display: none;
      width: 100%;
      height: 100%;
      position: relative;
    }

    .view.on {
      display: flex;
      flex-direction: row;
      width: 100%;
      height: 100%;
    }

    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      position: relative;
      overflow: hidden;
      min-width: 0;
    }

    .map-wrap {
      flex: 1;
      position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }

    #mainMap, #plantMap {
      width: 100%;
      height: 100%;
      background: #d8e5db;
    }

    /* Constrain Leaflet Controls safely below Header Banner */
    .app-embed .leaflet-top,
    .app-embed .leaflet-bottom {
      z-index: 400 !important;
    }

    .app-embed .leaflet-control-zoom {
      z-index: 400 !important;
      border: 1px solid rgba(30, 158, 98, 0.28) !important;
      border-radius: 8px !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
      overflow: hidden !important;
      margin-top: 14px !important;
      margin-left: 14px !important;
    }

    .app-embed .leaflet-control-zoom a {
      background: #ffffff !important;
      color: var(--primary-forest) !important;
      font-weight: 700 !important;
      width: 32px !important;
      height: 32px !important;
      line-height: 32px !important;
      transition: all 0.15s ease !important;
    }

    .app-embed .leaflet-control-zoom a:hover {
      background: #eef7f2 !important;
      color: var(--primary-green) !important;
    }

    /* Floating Legend */
    .map-legend-float {
      position: absolute;
      bottom: 24px;
      left: 18px;
      z-index: 450 !important;
      background: rgba(255, 255, 255, 0.94);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(30, 158, 98, 0.25);
      border-radius: var(--radius-md);
      padding: 12px 16px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    }

    .leg-title {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      margin-bottom: 8px;
    }

    .leg-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      font-weight: 500;
      margin: 4px 0;
      color: #244130;
    }

    .leg-dot {
      width: 12px;
      height: 12px;
      border-radius: 3px;
    }

    /* Map Layer Control Widget */
    .map-layer-control {
      position: absolute;
      top: 18px;
      right: 18px;
      z-index: 450 !important;
      display: inline-flex;
      flex-direction: column;
      background: rgba(255, 255, 255, 0.96);
      border: 1px solid rgba(30, 158, 98, 0.28);
      border-radius: var(--radius-sm);
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
      overflow: visible;
    }

    .map-layer-control > button {
      all: unset;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 38px;
      background: #ffffff;
      color: var(--primary-forest);
      font-size: 18px;
      border-radius: var(--radius-sm);
      transition: background 0.15s ease;
    }

    .map-layer-control > button:hover {
      background: #eef7f2;
      color: var(--primary-green);
    }

    .layer-menu {
      position: absolute;
      top: 0;
      right: 105%;
      display: none;
      flex-direction: column;
      min-width: 140px;
      margin-right: 6px;
      background: #ffffff;
      border: 1px solid rgba(30, 158, 98, 0.25);
      border-radius: var(--radius-sm);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.14);
      padding: 6px;
    }

    .layer-menu.show {
      display: flex;
    }

    .layer-option {
      width: 100%;
      font-family: inherit;
      font-size: 12.5px;
      font-weight: 500;
      text-align: left;
      color: #375342;
      padding: 8px 10px;
      border-radius: 6px;
      border: none;
      background: transparent;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .layer-option:hover {
      background: #eef7f2;
      color: var(--primary-forest);
    }

    .layer-option.active {
      background: #e4f5eb;
      color: var(--primary-green);
      font-weight: 700;
    }

    /* Right Sliding Details Panel */
    #mapRightPanel {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      width: 360px;
      max-width: 92vw;
      height: 100%;
      background: #ffffff;
      border-left: 1px solid #d5e5d8;
      box-shadow: -6px 0 28px rgba(0, 0, 0, 0.15);
      z-index: 500 !important;
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
      flex: 1;
      overflow-y: auto;
      padding: 20px 18px;
    }

    .panel-close-btn {
      position: absolute;
      top: 16px;
      right: 16px;
      background: #f0f5f1;
      border: 1px solid #d8e5dc;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      font-size: 14px;
      cursor: pointer;
      color: #4b6655;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 20;
      transition: all 0.15s ease;
    }

    .panel-close-btn:hover {
      background: #e2ece5;
      color: var(--primary-forest);
    }

    .sec {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #5d7e6c;
      margin-bottom: 12px;
    }

    .d-card {
      background: #f8faf8;
      border: 1px solid #e0ebe2;
      border-radius: var(--radius-md);
      padding: 16px;
      margin-bottom: 16px;
    }

    .d-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--primary-forest);
      margin-bottom: 10px;
    }

    .d-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      padding: 6px 0;
      border-bottom: 1px solid #edf4ef;
    }

    .d-row:last-child {
      border-bottom: none;
    }

    .d-key {
      color: #6a8c79;
    }

    .d-val {
      font-weight: 600;
      color: #1a3324;
    }

    .tag {
      display: inline-block;
      font-size: 11px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 999px;
      border: 1px solid;
    }

    .tg {
      background: #edf7f2;
      border-color: #b0e0c0;
      color: #1e9e62;
    }

    .tb {
      background: #eef4ff;
      border-color: #b0c8f0;
      color: #3060b0;
    }

    .div {
      border-top: 1px solid #e8efe9;
      margin: 16px 0;
    }

    .cw {
      position: relative;
      width: 100%;
    }

    /* Planting Suitability View Components */
    .site-card {
      background: #f8fbf8;
      border: 1px solid #dfeae2;
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      margin-bottom: 10px;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .site-card:hover {
      border-color: var(--primary-green);
      background: #eef7f2;
    }

    .sc-head {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
    }

    .sc-rank {
      width: 22px;
      height: 22px;
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
      color: var(--primary-forest);
    }

    .sc-sp {
      font-size: 11.5px;
      color: #6a8c79;
      margin-bottom: 6px;
    }

    .sc-foot {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .sbar {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .strack {
      width: 56px;
      height: 4px;
      background: #dfeae2;
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

    /* Environmental Impact / Purpose Callout Section */
    .impact-section {
      background: linear-gradient(135deg, #0e3820 0%, #144a2b 100%);
      color: #ffffff;
      padding: 80px 24px;
      position: relative;
    }

    .impact-grid {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 32px;
    }

    .impact-card {
      background: rgba(255, 255, 255, 0.07);
      border: 1px solid rgba(255, 255, 255, 0.14);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border-radius: var(--radius-lg);
      padding: 32px 26px;
      transition: transform 0.25s ease;
    }

    .impact-card:hover {
      transform: translateY(-4px);
      background: rgba(255, 255, 255, 0.1);
      border-color: rgba(74, 222, 128, 0.4);
    }

    .impact-icon {
      font-size: 28px;
      color: #4ade80;
      margin-bottom: 16px;
    }

    .impact-title {
      font-size: 19px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #ffffff;
    }

    .impact-text {
      font-size: 14.5px;
      line-height: 1.65;
      color: #cbd5e1;
    }

    /* Minimal Professional Footer */
    .site-footer {
      background: #091f13;
      border-top: 1px solid rgba(255, 255, 255, 0.08);
      color: #8da495;
      padding: 50px 24px 36px 24px;
      font-size: 14px;
    }

    .footer-container {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 24px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      padding-bottom: 30px;
      margin-bottom: 24px;
    }

    .footer-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      color: #ffffff;
      font-weight: 700;
      font-size: 17px;
    }

    .footer-nav {
      display: flex;
      align-items: center;
      gap: 24px;
      list-style: none;
    }

    .footer-nav a {
      color: #a3bdae;
      text-decoration: none;
      font-size: 13.5px;
      transition: color 0.15s ease;
    }

    .footer-nav a:hover {
      color: #4ade80;
    }

    .footer-bottom {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      font-size: 12.5px;
    }

    /* Mobile Install PWA Button */
    .mobile-install-btn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 52px;
      height: 52px;
      background: var(--primary-green);
      color: #ffffff;
      border: none;
      border-radius: 50%;
      display: none;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(30, 158, 98, 0.45);
      z-index: 10000;
      cursor: pointer;
      transition: transform 0.2s;
    }

    .mobile-install-btn:active {
      transform: scale(0.92);
    }

    .mobile-install-btn i {
      font-size: 22px;
    }

    /* Mobile Menu Drawer */
    .mobile-menu-drawer {
      display: none;
      position: fixed;
      top: 68px;
      left: 0;
      right: 0;
      background: #ffffff;
      border-bottom: 1px solid var(--earth-border);
      padding: 20px 24px;
      flex-direction: column;
      gap: 16px;
      z-index: 999;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
    }

    .mobile-menu-drawer.open {
      display: flex;
    }

    .mobile-menu-drawer a {
      text-decoration: none;
      color: var(--primary-forest);
      font-size: 16px;
      font-weight: 600;
      padding: 8px 0;
      border-bottom: 1px solid #f0f4f0;
    }

    /* Responsive Media Queries */
    @media (max-width: 992px) {
      .features-grid, .impact-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .hero-stats-strip {
        grid-template-columns: repeat(2, 1fr);
        row-gap: 18px;
      }

      .hero-stat-card:nth-child(2) {
        border-right: none;
      }
    }

    @media (max-width: 768px) {
      .site-nav {
        padding: 0 18px;
        height: 62px;
      }

      .nav-links, .nav-btn-explore {
        display: none;
      }

      .nav-mobile-toggle {
        display: block;
      }

      .hero-section {
        min-height: auto;
        padding: 60px 18px 50px 18px;
      }

      .hero-stats-strip {
        grid-template-columns: 1fr;
        padding: 16px;
      }

      .hero-stat-card {
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        padding-bottom: 12px;
      }

      .hero-stat-card:last-child {
        border-bottom: none;
        padding-bottom: 0;
      }

      .features-grid, .impact-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .section-wrap {
        padding: 50px 18px;
      }

      .map-section {
        padding: 40px 12px 50px 12px;
      }

      .app-embed {
        height: 540px;
      }

      .toolbar-left, .toolbar-center, .toolbar-right {
        width: 100%;
        justify-content: flex-start;
      }

      .footer-container {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body>
  <!-- Clean, Minimal Navigation Bar -->
  <header class="site-nav">
    <a href="/" class="brand-wrap" aria-label="MangroveMap Home">
      <div class="brand-logo-icon">
        <i class="bi bi-leaf-fill"></i>
      </div>
      <div class="brand-text">
        <div class="brand-title">Mangrove<span>Map</span></div>
        <div class="brand-badge">Environmental GIS</div>
      </div>
    </a>

    <!-- Desktop Navigation Links -->
    <ul class="nav-links">
      <li><a href="#overview"><i class="bi bi-info-circle"></i> Overview</a></li>
      <li><a href="#features"><i class="bi bi-grid-1x2"></i> Capabilities</a></li>
      <li><a href="#map-section" onclick="scrollToMap(event)"><i class="bi bi-compass"></i> GIS Explorer</a></li>
      <li><a href="#impact"><i class="bi bi-shield-check"></i> Conservation</a></li>
    </ul>

    <!-- Header Actions & Authentication Section -->
    <div class="nav-actions">
      <a href="#map-section" onclick="scrollToMap(event)" class="nav-btn-explore">
        <i class="bi bi-map-fill"></i> Explore Map
      </a>

      @auth
        @include('components.notification-bell')
        <div style="display: flex; align-items: center; gap: 8px; padding-left: 8px; border-left: 1px solid #d5e5d8;">
          <a href="{{ Auth::user()->homeRoute() }}" title="Go to Dashboard" style="text-decoration: none; display: flex; align-items: center; gap: 7px;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: #144a2b; display: flex; align-items: center; justify-content: center; color: white; font-size: 13px; font-weight: 700; box-shadow: 0 2px 6px rgba(20, 74, 43, 0.25);">
              {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
            <span style="font-size: 13px; font-weight: 600; color: #1e382b; display: inline-block; max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              {{ Auth::user()->name }}
            </span>
          </a>
          <form method="POST" action="{{ route('logout') }}" style="display: inline;">
            @csrf
            <button type="submit" class="nav-btn-login" style="padding: 6px 10px; font-size: 12.5px;" title="Logout">
              <i class="bi bi-box-arrow-right"></i>
            </button>
          </form>
        </div>
      @else
        <a href="/login" class="nav-btn-login">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
      @endauth

      <!-- Mobile Hamburger Toggle -->
      <button class="nav-mobile-toggle" id="mobileMenuBtn" aria-label="Toggle navigation menu">
        <i class="bi bi-list"></i>
      </button>
    </div>
  </header>

  <!-- Mobile Drawer Menu -->
  <div class="mobile-menu-drawer" id="mobileMenuDrawer">
    <a href="#overview" onclick="closeMobileMenu()"><i class="bi bi-info-circle"></i> Overview</a>
    <a href="#features" onclick="closeMobileMenu()"><i class="bi bi-grid-1x2"></i> System Capabilities</a>
    <a href="#map-section" onclick="scrollToMap(event); closeMobileMenu()"><i class="bi bi-compass"></i> GIS Explorer</a>
    <a href="#impact" onclick="closeMobileMenu()"><i class="bi bi-shield-check"></i> Conservation Impact</a>
    @auth
      <a href="{{ Auth::user()->homeRoute() }}"><i class="bi bi-speedometer2"></i> My Dashboard</a>
    @else
      <a href="/login"><i class="bi bi-box-arrow-in-right"></i> Sign In to Portal</a>
      <a href="/register"><i class="bi bi-person-plus"></i> Create Account</a>
    @endauth
  </div>

  <!-- Polished, Visually Dominant Hero Section -->
  <section class="hero-section" id="overview">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <div class="hero-eyebrow">
        <span class="pulse-dot"></span>
        <span>Geospatial Environmental Intelligence &amp; Coastal Conservation</span>
      </div>

      <h1 class="hero-title">
        Mangrove Mapping &amp; <br />
        <span class="hero-title-highlight">Monitoring System</span>
      </h1>

      <p class="hero-description">
        A modern Web GIS platform engineered to map mangrove forest extents, monitor canopy health via multi-spectral satellite analytics, and support data-driven coastal conservation and ecological restoration.
      </p>

      <div class="hero-actions">
        <a href="#map-section" onclick="scrollToMap(event)" class="btn-hero-primary" id="exploreMapHeroBtn">
          <i class="bi bi-compass-fill"></i> Explore Map
        </a>
        <a href="#features" class="btn-hero-secondary">
          <i class="bi bi-layers-fill"></i> System Capabilities
        </a>
      </div>

      <!-- Live GIS KPI / Statistics Strip -->
      <div class="hero-stats-strip">
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format($globalCoverage ?? 46382, 1) }}<span>km²</span></div>
          <div class="stat-label">Monitored Coverage</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ $protectedAreas > 0 ? $protectedAreas : 14 }}<span>zones</span></div>
          <div class="stat-label">Monitored Regions</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ $genusCount > 0 ? $genusCount : 5 }}<span>taxa</span></div>
          <div class="stat-label">Mangrove Genera</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ count($delineations ?? []) }}<span>records</span></div>
          <div class="stat-label">Field Delineations</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Polished Feature Section -->
  <section class="section-wrap" id="features">
    <div class="section-header">
      <div class="section-kicker">
        <i class="bi bi-stars"></i> Core Architecture
      </div>
      <h2 class="section-title">Comprehensive Environmental GIS Tools</h2>
      <p class="section-subtitle">
        Combining remote sensing, satellite cartography, and community participation to protect vital mangrove ecosystems.
      </p>
    </div>

    <div class="features-grid">
      <!-- Feature 1: Mangrove Mapping -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-map-fill"></i>
        </div>
        <h3 class="feature-title">Mangrove Mapping</h3>
        <p class="feature-desc">
          Accurate mapping and delineation of mangrove areas using high-resolution imagery and spatial analysis. Identifies and outlines mangrove boundaries, patches, and coastal forest coverage to support precise visualization, monitoring, and geographic assessment over time.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Precision polygon area measurement (ha / m²)</li>
          <li><i class="bi bi-check-circle-fill"></i> Community-driven field boundary uploads</li>
          <li><i class="bi bi-check-circle-fill"></i> Expert review and delineation validation workflow</li>
        </ul>
      </div>

      <!-- Feature 2: GIS Visualization -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-layers-half"></i>
        </div>
        <h3 class="feature-title">GIS Visualization</h3>
        <p class="feature-desc">
          Interactive multi-layer geospatial interface supporting ESRI World Imagery satellite basemaps, OpenStreetMap, and Topographic terrain for multi-scale coastal analysis.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> High-resolution aerial satellite tile layers</li>
          <li><i class="bi bi-check-circle-fill"></i> Interactive vector overlay rendering</li>
          <li><i class="bi bi-check-circle-fill"></i> Responsive coordinate inspection &amp; zoom navigation</li>
        </ul>
      </div>

      <!-- Feature 3: Mangrove Monitoring -->
      <div class="feature-card">
        <div class="feature-icon-box">
          <i class="bi bi-activity"></i>
        </div>
        <h3 class="feature-title">Mangrove Monitoring</h3>
        <p class="feature-desc">
          Continuous monitoring of mangrove areas, tracking species distributions such as Rhizophora, Avicennia, and Sonneratia, while supporting data-driven analysis and planting suitability prioritization.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Historical coverage trend charting &amp; analytics</li>
          <li><i class="bi bi-check-circle-fill"></i> Species &amp; genus composition breakdown</li>
          <li><i class="bi bi-check-circle-fill"></i> Planting suitability site scoring &amp; ranking</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Interactive GIS Map Section -->
  <section class="map-section" id="map-section">
    <div class="map-showcase-container">
      <div class="section-header" style="margin-bottom: 30px;">
        <div class="section-kicker">
          <i class="bi bi-globe2"></i> Interactive Geospatial Platform
        </div>
        <h2 class="section-title">Live Mangrove Explorer</h2>
        <p class="section-subtitle">
          Explore validated mangrove polygons, inspect field survey parameters, or evaluate restoration priority zones.
        </p>
      </div>

      <!-- GIS Map Browser Frame -->
      <div class="map-browser-frame">
        <!-- Frame Toolbar Header -->
        <div class="map-frame-toolbar">
          <div class="toolbar-left">
            <span class="live-status-pill">
              <span class="indicator-dot"></span>
              Live GIS Satellite View
            </span>
            <span class="map-frame-heading">Coverage &amp; Suitability Viewer</span>
          </div>

          <div class="toolbar-center">
            <button class="view-switch-btn active" id="btnViewMap" onclick="switchGisView('map')">
              <i class="bi bi-map"></i> Coverage Map
            </button>
            <button class="view-switch-btn" id="btnViewPlanting" onclick="switchGisView('planting')">
              <i class="bi bi-tree"></i> Planting Suitability
            </button>
          </div>

          <div class="toolbar-right">
            <button class="map-action-btn" onclick="toggleLayerMenu()" title="Switch Satellite / Street Base Layers">
              <i class="bi bi-layers"></i> Basemap
            </button>
            @auth
              <a href="{{ route('map') }}" class="map-action-btn" style="background:#eef7f2; border-color:#bce6cf; color:#147a46; font-weight:600;">
                <i class="bi bi-pencil-square"></i> Open Full Editor
              </a>
            @else
              <a href="/login" class="map-action-btn" title="Sign in to submit delineations">
                <i class="bi bi-plus-circle"></i> Contribute Delineation
              </a>
            @endauth
          </div>
        </div>

        <!-- Embedded Map Application Container -->
        <div class="app-embed">
          <!-- COVERAGE MAP VIEW -->
          <div class="view on" id="v-map">
            <div class="main">
              <div class="map-wrap">
                <div id="mainMap"></div>

                <!-- Floating Zone Type Legend -->
                <div class="map-legend-float">
                  <div class="leg-title">Zone Health Type</div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:rgba(30,158,98,.5); border:1.5px solid #1e9e62"></div> Healthy Zone
                  </div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:rgba(192,120,24,.5); border:1.5px solid #c07818"></div> Sparse Zone
                  </div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:rgba(208,64,48,.5); border:1.5px solid #d04030"></div> Degraded Zone
                  </div>
                </div>

                <!-- Layer Switcher Dropdown Widget -->
                <div class="map-layer-control">
                  <button id="layerToggle" onclick="toggleLayerMenu()" title="Map Basemap Layers" aria-expanded="false" aria-label="Toggle map layers">
                    <i class="bi bi-layers-half"></i>
                  </button>
                  <div class="layer-menu" id="layerMenu">
                    <button class="layer-option active" onclick="setBase('sat', this)"><i class="bi bi-image"></i> Satellite (ESRI)</button>
                    <button class="layer-option" onclick="setBase('osm', this)"><i class="bi bi-map"></i> Street</button>
                    <button class="layer-option" onclick="setBase('topo', this)"><i class="bi bi-triangle"></i> Topo</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Slide-in Delineation Details Drawer -->
            <div class="right-panel" id="mapRightPanel">
              <button onclick="closeRightPanel()" class="panel-close-btn" title="Close Panel" aria-label="Close details panel">
                <i class="bi bi-x-lg"></i>
              </button>
              <div class="scroll">
                <div class="sec" style="padding-right: 28px;">Delineated Area Details</div>
                <div id="zoneDetailsContent">
                  <div class="d-card">
                    <div class="d-title" id="dName">Saved Delineation</div>
                    <div style="margin-bottom: 12px; display: flex; gap: 6px; flex-wrap: wrap;">
                      <span class="tag tg" id="dStatusBadge">Approved</span>
                      <span class="tag tb" id="dTypeBadge">Area</span>
                    </div>
                    <div class="d-row">
                      <span class="d-key">Feature Type</span>
                      <span class="d-val" id="dType">Area</span>
                    </div>
                    <div class="d-row" id="dAreaRow">
                      <span class="d-key">Estimated Area</span>
                      <span class="d-val" id="dArea">-</span>
                    </div>
                    <div class="d-row">
                      <span class="d-key">Contributor</span>
                      <span class="d-val" id="dContributor">Community</span>
                    </div>
                    <div class="d-row">
                      <span class="d-key">Approved Date</span>
                      <span class="d-val" id="dApprovedDate">-</span>
                    </div>
                    <div class="d-row" id="dNotesRow" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                      <span class="d-key">Notes</span>
                      <span class="d-val" id="dNotes" style="font-weight: 400; font-size: 12px; color: #3a5a3a; line-height: 1.4; word-break: break-word;">-</span>
                    </div>
                  </div>
                  <div class="div"></div>
                  <div class="sec">Estimated Genus Distribution</div>
                  <div class="cw" style="height:150px"><canvas id="pieC"></canvas></div>
                  <div class="div"></div>
                  <div class="sec">Coverage Trend (km²)</div>
                  <div class="cw" style="height:110px"><canvas id="trendC"></canvas></div>
                </div>
              </div>
            </div>
          </div>

          <!-- PLANTING SUITABILITY VIEW -->
          <div class="view" id="v-planting">
            <div class="main">
              <div class="map-wrap">
                <div id="plantMap"></div>
                <div class="map-legend-float">
                  <div class="leg-title">Restoration Priority</div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:#1e9e62"></div> Critical Priority
                  </div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:#c07818"></div> High Priority
                  </div>
                  <div class="leg-row">
                    <div class="leg-dot" style="background:#5ab8de"></div> Medium Priority
                  </div>
                </div>
              </div>
            </div>
            <div class="right-panel" style="width: 320px; background: #ffffff; border-left: 1px solid #d5e5d8; display: flex; flex-direction: column; overflow: hidden;">
              <div class="scroll" style="padding: 16px; overflow-y: auto;">
                <div class="sec">Priority Planting Sites</div>
                <button onclick="switchGisView('map')" class="view-switch-btn" style="margin-bottom: 14px; width: 100%; justify-content: center;">
                  <i class="bi bi-arrow-left-short"></i> Back to Coverage Map
                </button>
                <div id="plantSitesList"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Ecological Importance / Conservation Impact Section -->
  <section class="impact-section" id="impact">
    <div class="impact-grid">
      <div class="impact-card">
        <div class="impact-icon"><i class="bi bi-cloud-arrow-down-fill"></i></div>
        <h3 class="impact-title">Blue Carbon Sequestration</h3>
        <p class="impact-text">
          Mangrove forests capture carbon up to 4 times faster than tropical terrestrial rainforests, burying carbon safely in coastal sediments for millennia.
        </p>
      </div>

      <div class="impact-card">
        <div class="impact-icon"><i class="bi bi-shield-shaded"></i></div>
        <h3 class="impact-title">Coastal Storm Protection</h3>
        <p class="impact-text">
          Dense root networks absorb wave energy, attenuating storm surges by up to 66% and preventing critical shoreline erosion in vulnerable coastal regions.
        </p>
      </div>

      <div class="impact-card">
        <div class="impact-icon"><i class="bi bi-water"></i></div>
        <h3 class="impact-title">Biodiversity &amp; Livelihoods</h3>
        <p class="impact-text">
          Serving as vital nursery grounds for hundreds of marine species, mangroves sustain local artisanal fisheries and provide economic resilience for coastal communities.
        </p>
      </div>
    </div>
  </section>

  <!-- Minimal Professional Footer -->
  <footer class="site-footer">
    <div class="footer-container">
      <div class="footer-brand">
        <div class="brand-logo-icon" style="width: 30px; height: 30px; font-size: 16px;">
          <i class="bi bi-leaf-fill"></i>
        </div>
        <span>MangroveMap System</span>
      </div>

      <ul class="footer-nav">
        <li><a href="#overview">Overview</a></li>
        <li><a href="#features">Capabilities</a></li>
        <li><a href="#map-section" onclick="scrollToMap(event)">GIS Map</a></li>
        @guest
          <li><a href="/login">Portal Login</a></li>
        @else
          <li><a href="{{ Auth::user()->homeRoute() }}">Dashboard</a></li>
        @endguest
      </ul>
    </div>

    <div class="footer-bottom">
      <p>&copy; {{ date('Y') }} MangroveMap. Environmental Geospatial Intelligence &amp; Ecosystem Monitoring.</p>
      <p>Empowering coastal communities through spatial science.</p>
    </div>
  </footer>

  <!-- Mobile Install PWA Button -->
  <button id="mobileInstallBtn" class="mobile-install-btn" title="Install App">
    <i class="bi bi-phone"></i>
  </button>

  <!-- GIS Logic & Scripts (Preserving all original variables and functions) -->
  <script>
    const savedDelineations = @json($delineations ?? []);
    const zones = [];
    const plantSites = [];

    // Smooth Scroll Helper
    function scrollToMap(e) {
      if (e) e.preventDefault();
      const target = document.getElementById('map-section');
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => {
          if (typeof mainMap !== 'undefined' && mainMap) {
            mainMap.invalidateSize();
          }
        }, 350);
      }
    }

    // Mobile Menu Controls
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenuDrawer = document.getElementById('mobileMenuDrawer');
    if (mobileMenuBtn && mobileMenuDrawer) {
      mobileMenuBtn.addEventListener('click', () => {
        mobileMenuDrawer.classList.toggle('open');
      });
    }
    function closeMobileMenu() {
      if (mobileMenuDrawer) {
        mobileMenuDrawer.classList.remove('open');
      }
    }

    // View Switching between Coverage Map & Planting Suitability
    window.switchGisView = function(viewName) {
      const btnMap = document.getElementById('btnViewMap');
      const btnPlanting = document.getElementById('btnViewPlanting');
      
      if (btnMap && btnPlanting) {
        btnMap.classList.toggle('active', viewName === 'map');
        btnPlanting.classList.toggle('active', viewName === 'planting');
      }

      window.show(viewName);
    };

    // GIS Basemap Tile Layers
    const satL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxZoom: 18,
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    const osmL = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; OpenStreetMap contributors'
    });
    const topoL = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17,
      attribution: 'Map data: &copy; OpenStreetMap contributors, SRTM | Map style: &copy; OpenTopoMap'
    });

    let curBase = satL;
    let mainMap = L.map('mainMap', {
      zoomControl: true,
      layers: [satL]
    }).setView([10.358, 124.973], 13);

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
    }).setView([10.358, 124.973], 13);

    zones.forEach(z => L.polygon(z.shape, {
      color: '#1e9e62',
      fillOpacity: .1,
      weight: 1,
      dashArray: '5,4'
    }).addTo(plantMap));

    let pMarkers = [];
    plantSites.forEach((s, i) => {
      let ic = L.divIcon({
        html: `<div style="width:30px;height:30px;border-radius:50%;background:${s.color};border:2px solid white;display:flex;align-items:center;justify-content:center;font-weight:700;color:white;">${i + 1}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      });
      let m = L.marker([s.lat, s.lng], {
        icon: ic
      }).addTo(plantMap);
      m.bindPopup(`<b>${s.name}</b><br>Suitability: ${s.score}%<br>Priority: ${s.priority}`);
      pMarkers.push(m);
    });

    // Area Calculation Utility
    function calculatePolygonArea(latlngs) {
      if (!Array.isArray(latlngs) || latlngs.length < 3) return null;
      try {
        const R = 6378137; // Earth radius in meters
        let ring = latlngs;
        if (Array.isArray(latlngs[0]) && Array.isArray(latlngs[0][0])) {
          ring = latlngs[0];
        }
        if (ring.length < 3) return null;
        let area = 0;
        for (let i = 0; i < ring.length; i++) {
          let p1 = ring[i];
          let p2 = ring[(i + 1) % ring.length];
          let lat1 = Array.isArray(p1) ? p1[0] : p1.lat;
          let lng1 = Array.isArray(p1) ? p1[1] : p1.lng;
          let lat2 = Array.isArray(p2) ? p2[0] : p2.lat;
          let lng2 = Array.isArray(p2) ? p2[1] : p2.lng;
          area += (lng2 - lng1) * (Math.PI / 180) * (2 + Math.sin(lat1 * Math.PI / 180) + Math.sin(lat2 * Math.PI / 180));
        }
        area = Math.abs(area * R * R / 2.0);
        if (area >= 10000) {
          return (area / 10000).toFixed(2) + ' ha';
        } else if (area > 0) {
          return area.toFixed(1) + ' m²';
        }
      } catch (e) {
        console.error('Area calculation error:', e);
      }
      return null;
    }

    // Delineation Details Drawer
    function showDelineationDetails(record, feature, layer) {
      const dNameEl = document.getElementById('dName');
      if (dNameEl) dNameEl.textContent = record.name || 'Saved Delineation';
      
      const featType = feature.type ? feature.type.charAt(0).toUpperCase() + feature.type.slice(1) : 'Area';
      const dTypeEl = document.getElementById('dType');
      const dTypeBadgeEl = document.getElementById('dTypeBadge');
      if (dTypeEl) dTypeEl.textContent = featType;
      if (dTypeBadgeEl) dTypeBadgeEl.textContent = featType;

      let areaStr = record.area;
      if (!areaStr && feature.coords && (feature.type === 'area' || (!feature.type && Array.isArray(feature.coords)))) {
        areaStr = calculatePolygonArea(feature.coords);
      }
      const areaRow = document.getElementById('dAreaRow');
      const dAreaEl = document.getElementById('dArea');
      if (areaStr && dAreaEl && areaRow) {
        dAreaEl.textContent = areaStr;
        areaRow.style.display = 'flex';
      } else if (areaRow) {
        areaRow.style.display = 'none';
      }

      const contributor = record.user?.name || record.created_by || 'Community';
      const dContribEl = document.getElementById('dContributor');
      if (dContribEl) dContribEl.textContent = contributor;

      let approvedDate = '-';
      if (record.approved_at) {
        const d = new Date(record.approved_at);
        approvedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      } else if (record.created_at) {
        const d = new Date(record.created_at);
        approvedDate = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      }
      const dApprovedDateEl = document.getElementById('dApprovedDate');
      if (dApprovedDateEl) dApprovedDateEl.textContent = approvedDate;

      const notesRow = document.getElementById('dNotesRow');
      const dNotesEl = document.getElementById('dNotes');
      if (record.notes && notesRow && dNotesEl) {
        dNotesEl.textContent = record.notes;
        notesRow.style.display = 'flex';
      } else if (notesRow) {
        notesRow.style.display = 'none';
      }

      const panel = document.getElementById('mapRightPanel');
      const wasOpen = panel ? panel.classList.contains('open') : false;
      if (panel && !wasOpen) {
        panel.classList.add('open');
      }

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize();
          if (layer) {
            if (layer.getBounds) {
              mainMap.fitBounds(layer.getBounds(), {
                padding: [50, 50],
                maxZoom: 14,
                animate: true,
                duration: 0.8
              });
            } else if (layer.getLatLng) {
              mainMap.flyTo(layer.getLatLng(), 13, {
                animate: true,
                duration: 0.8
              });
            }
          }
        }
      }, wasOpen ? 50 : 330);
    }

    // Draw Approved Delineations onto Leaflet Map
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
          const featType = feature.type ? feature.type.toUpperCase() : 'AREA';
          const popupContent = `<strong>${record.name || 'Saved Delineation'}</strong><br><span style="font-size:12px;color:#556b56;">Type: ${featType}</span>`;

          if (feature.type === 'point') {
            layer = L.marker(feature.coords).addTo(mainMap);
          } else if (feature.type === 'line') {
            layer = L.polyline(feature.coords, {
              color: '#1e9e62',
              weight: 3
            }).addTo(mainMap);
          } else if (feature.type === 'area' || !feature.type) {
            layer = L.polygon(feature.coords, {
              color: '#1e9e62',
              fillColor: '#1e9e62',
              fillOpacity: 0.28,
              weight: 2
            }).addTo(mainMap);
          }

          if (layer) {
            layer.bindPopup(popupContent);
            layer.on('click', (e) => {
              if (e && e.originalEvent) {
                L.DomEvent.stopPropagation(e);
              }
              showDelineationDetails(record, feature, layer);
            });
          }
        });
      });
    }

    drawSavedDelineations();

    function selectZone(i) {
      let z = zones[i];
      if (!z) return;
      document.querySelectorAll('.zone-row').forEach((r, j) => r.classList.toggle('sel', j === i));
      const dName = document.getElementById('dName');
      if (dName) dName.textContent = z.name;
      const dArea = document.getElementById('dArea');
      if (dArea) dArea.textContent = z.area;
      const areaRow = document.getElementById('dAreaRow');
      if (areaRow) areaRow.style.display = 'flex';
      
      const panel = document.getElementById('mapRightPanel');
      const wasOpen = panel ? panel.classList.contains('open') : false;
      if (panel && !wasOpen) {
        panel.classList.add('open');
      }

      setTimeout(() => {
        if (typeof mainMap !== 'undefined' && mainMap) {
          mainMap.invalidateSize();
        }
        if (polys[i]) {
          mainMap.flyTo([z.lat, z.lng], 10, { duration: 0.8 });
          polys[i].openPopup();
        }
      }, wasOpen ? 50 : 330);
    }

    window.flyTo = (i) => selectZone(i);
    window.closeRightPanel = () => {
      const panel = document.getElementById('mapRightPanel');
      if (panel) {
        panel.classList.remove('open');
        setTimeout(() => {
          if (typeof mainMap !== 'undefined' && mainMap) {
            mainMap.invalidateSize();
          }
        }, 330);
      }
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
      window.location.href = "{{ Auth::check() ? route('map') : route('login') }}";
    };

    window.showSuitability = () => {
      switchGisView('planting');
    };

    window.showDelineation = () => {
      window.location.href = "{{ route('delineation.index') }}";
    };

    window.setBase = (t, btn) => {
      document.querySelectorAll('.layer-option').forEach(b => b.classList.remove('active'));
      if (btn) btn.classList.add('active');
      mainMap.removeLayer(curBase);
      curBase = t === 'osm' ? osmL : t === 'topo' ? topoL : satL;
      mainMap.addLayer(curBase);
      const menu = document.getElementById('layerMenu');
      if (menu) menu.classList.remove('show');
    };

    window.show = (id) => {
      document.querySelectorAll('.view').forEach(v => v.classList.remove('on'));
      const activeView = document.getElementById('v-' + id);
      if (activeView) activeView.classList.add('on');
      setTimeout(() => {
        if (id === 'map' && mainMap) mainMap.invalidateSize();
        if (id === 'planting' && plantMap) plantMap.invalidateSize();
      }, 150);
    };

    window.addEventListener('resize', () => {
      setTimeout(() => {
        if (mainMap) mainMap.invalidateSize();
        if (plantMap) plantMap.invalidateSize();
      }, 120);
    });

    // Chart.js Visualizations
    const pieEl = document.getElementById('pieC');
    if (pieEl) {
      new Chart(pieEl, {
        type: 'doughnut',
        data: {
          labels: ['Rhizophora', 'Avicennia', 'Sonneratia', 'Bruguiera', 'Others'],
          datasets: [{
            data: [34, 22, 18, 14, 12],
            backgroundColor: ['#1e9e62', '#5ab8de', '#f4a840', '#a070e0', '#8ca090'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '66%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                boxWidth: 10,
                font: { size: 10 }
              }
            }
          }
        }
      });
    }

    const trendEl = document.getElementById('trendC');
    if (trendEl) {
      new Chart(trendEl, {
        type: 'line',
        data: {
          labels: ['2021', '2022', '2023', '2024', '2025', '2026'],
          datasets: [{
            data: [46178, 45900, 46360, 46820, 47100, 47382],
            borderColor: '#1e9e62',
            backgroundColor: 'rgba(30,158,98,.12)',
            fill: true,
            tension: 0.38,
            pointRadius: 2.5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              ticks: {
                font: { size: 9 },
                callback: v => (v / 1000).toFixed(0) + 'k'
              },
              min: 45000
            },
            x: {
              ticks: { font: { size: 9 } }
            }
          }
        }
      });
    }

    // Populate Planting Sites if any
    const plantListDiv = document.getElementById('plantSitesList');
    if (plantListDiv) {
      plantSites.forEach((s, i) => {
        const card = document.createElement('div');
        card.className = 'site-card';
        card.setAttribute('onclick', `flyPlant(${i})`);
        card.innerHTML = `<div class="sc-head"><div class="sc-rank ${s.priority === 'Critical' ? 'rg' : (s.priority === 'High' ? 'ra' : 'rb')}">${i + 1}</div><div class="sc-name">${s.name}</div></div><div class="sc-sp">${s.priority} priority zone</div><div class="sc-foot"><div class="sbar"><div class="strack"><div class="sfill" style="width:${s.score}%;background:${s.color}"></div></div><span style="font-size:10px;font-weight:700;color:${s.color}">${s.score}%</span></div><span class="ptag" style="background:${s.color}20;border-color:${s.color};color:${s.color}">${s.priority}</span></div>`;
        plantListDiv.appendChild(card);
      });
    }

    // Auto-Resize Map on DOM observation
    let indexMapResizeTimer = null;
    const mainMapEl = document.getElementById('mainMap');
    const plantMapEl = document.getElementById('plantMap');
    if (typeof ResizeObserver !== 'undefined') {
      const mapObserver = new ResizeObserver(() => {
        clearTimeout(indexMapResizeTimer);
        indexMapResizeTimer = setTimeout(() => {
          if (mainMap) mainMap.invalidateSize({ pan: false });
          if (plantMap) plantMap.invalidateSize({ pan: false });
        }, 180);
      });
      if (mainMapEl) mapObserver.observe(mainMapEl);
      if (plantMapEl) mapObserver.observe(plantMapEl);
    }

    // Notification dropdown handling
    const notificationToggle = document.getElementById('notificationToggle');
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationToggle && notificationDropdown) {
      notificationToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('active');
      });

      document.addEventListener('click', function (e) {
        if (!notificationToggle.contains(e.target) && !notificationDropdown.contains(e.target)) {
          notificationDropdown.classList.remove('active');
        }
      });

      notificationDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    }

    // PWA Service Worker & Install Logic
    let deferredPrompt;
    const mobileInstallBtn = document.getElementById('mobileInstallBtn');

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
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to install: ${outcome}`);
        deferredPrompt = null;
        mobileInstallBtn.style.display = 'none';
      });
    }

    window.addEventListener('appinstalled', () => {
      if (mobileInstallBtn) mobileInstallBtn.style.display = 'none';
      deferredPrompt = null;
    });
  </script>
</body>

</html>