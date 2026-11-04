<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  @include('partials.theme')
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

    /* Dark theme (partials.theme). These variables are only used for text, borders and one tint. */
    html[data-theme="dark"] {
      --primary-forest: #d8efe2;
      --primary-dark: #9fe0bd;
      --primary-mint: #173326;
      --earth-sand: #161f1a;
      --earth-border: #2a3a31;
      --earth-card: #16211b;
      --text-dark: #e3ece6;
      --text-muted: #a3b9ab;
      --text-subtle: #84998c;
      --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
      --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.35);
      --shadow-lg: 0 16px 40px rgba(0, 0, 0, 0.45);
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: var(--bg-f7faf7, #f7faf7);
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
      background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.96);
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

    .brand-logo-icon img {
      width: 100%;
      height: 100%;
      display: block;
      border-radius: inherit;
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
      color: var(--fg-375342, #375342);
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
      background: var(--bg-f0f7f2, #f0f7f2);
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
      background: var(--bg-e3f2e8, #e3f2e8);
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
      background: linear-gradient(120deg, #4ade80 0%, #22c55e 50%, var(--bg-86efac, #86efac) 100%);
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
      border-color: rgba(var(--bd-ffffff-rgb, 255, 255, 255), 0.5);
      transform: translateY(-2px);
      color: #ffffff;
    }

    /* Live Hero Stats Metrics */
    .hero-stats-strip {
      width: 100%;
      max-width: 1040px;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
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
      color: var(--fg-4ade80, #4ade80);
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
      background: var(--bg-ffffff, #ffffff);
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
      background: linear-gradient(135deg, var(--bg-eaf6ef, #eaf6ef) 0%, var(--bg-d8eee1, #d8eee1) 100%);
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
      border-top: 1px solid var(--bd-f0f4f0, #f0f4f0);
      padding-top: 18px;
    }

    .feature-points li {
      font-size: 13px;
      font-weight: 500;
      color: var(--fg-3b5445, #3b5445);
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
      background: var(--bg-ffffff, #ffffff);
      border-top: 1px solid var(--earth-border);
      border-bottom: 1px solid var(--earth-border);
      padding: 70px 24px 80px 24px;
    }

    .map-showcase-container {
      max-width: 1280px;
      margin: 0 auto;
    }

    .map-browser-frame {
      background: var(--bg-ffffff, #ffffff);
      border: 1px solid rgba(30, 158, 98, 0.22);
      border-radius: var(--radius-xl);
      box-shadow: 0 18px 50px rgba(14, 56, 32, 0.12);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .map-frame-toolbar {
      background: var(--bg-f7faf8, #f7faf8);
      border-bottom: 1px solid var(--bd-e0ebe2, #e0ebe2);
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
      background: var(--bg-e6f6ee, #e6f6ee);
      border: 1px solid var(--bd-bce6cf, #bce6cf);
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      color: var(--fg-147a46, #147a46);
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
      border: 1px solid var(--bd-cedecf, #cedecf);
      background: var(--bg-ffffff, #ffffff);
      color: var(--fg-3d5e49, #3d5e49);
      padding: 6px 14px;
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s ease;
    }

    .view-switch-btn:hover {
      background: var(--bg-edf6f0, #edf6f0);
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
      border: 1px solid var(--bd-d5e5d8, #d5e5d8);
      background: var(--bg-ffffff, #ffffff);
      color: var(--fg-3b5847, #3b5847);
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
      background: var(--bg-eef6f0, #eef6f0);
      color: var(--primary-forest);
      border-color: var(--primary-green);
    }

    /* Embedded Map View Styles (Preserving All GIS Capabilities) */
    .app-embed {
      position: relative;
      width: 100%;
      height: 720px;
      background: var(--bg-eaf1eb, #eaf1eb);
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

    #mainMap {
      width: 100%;
      height: 100%;
      background: var(--bg-d8e5db, #d8e5db);
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
      background: var(--bg-ffffff, #ffffff) !important;
      color: var(--primary-forest) !important;
      font-weight: 700 !important;
      width: 32px !important;
      height: 32px !important;
      line-height: 32px !important;
      transition: all 0.15s ease !important;
    }

    .app-embed .leaflet-control-zoom a:hover {
      background: var(--bg-eef7f2, #eef7f2) !important;
      color: var(--primary-green) !important;
    }

    /* Floating Legend */
    .map-legend-float {
      position: absolute;
      bottom: 24px;
      left: 18px;
      z-index: 450 !important;
      background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.94);
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
      color: var(--fg-244130, #244130);
    }

    .leg-dot {
      width: 12px;
      height: 12px;
      border-radius: 3px;
      flex: none;
    }

    .leg-collapse {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      width: 100%;
      border: 0;
      background: none;
      padding: 0;
      cursor: pointer;
      color: var(--text-muted);
    }

    .leg-collapse .leg-title {
      margin-bottom: 0;
    }

    .leg-collapse i {
      font-size: 11px;
      transition: transform 0.2s ease;
    }

    .map-legend-float.collapsed .leg-collapse i {
      transform: rotate(180deg);
    }

    .map-legend-float.collapsed #legendBody {
      display: none;
    }

    #legendBody {
      margin-top: 8px;
    }

    .leg-toggle {
      cursor: pointer;
    }

    .leg-toggle input {
      accent-color: #1e9e62;
      margin: 0;
    }

    .leg-toggle small {
      color: var(--fg-6a8a6a, #6a8a6a);
      font-size: 11px;
    }

    .leg-pin {
      color: var(--fg-1e9e62, #1e9e62);
      font-size: 14px;
      width: 12px;
      text-align: center;
    }

    .leg-rec-icon {
      display: inline-grid;
      place-items: center;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: var(--bg-ffffff, #fff);
      border: 2px solid #7cb342;
      color: var(--fg-7cb342, #7cb342);
      font-size: 9px;
    }

    .leg-suitability {
      display: grid;
      gap: 3px;
      margin: 4px 0 0 22px;
      font-size: 11px;
      color: var(--fg-4a6a4a, #4a6a4a);
    }

    .leg-suitability span {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .leg-swatch {
      width: 14px;
      height: 0;
      border-top: 2px dashed currentColor;
    }

    /* Location pin on every approved delineation */
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
      color: var(--fg-1e9e62, #1e9e62);
      -webkit-text-stroke: 1px var(--bg-ffffff, #fff);
    }

    /* Planting recommendation marker, colored by suitability */
    .rec-marker {
      display: grid;
      place-items: center;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: var(--bg-ffffff, #fff);
      border: 2.5px solid var(--rec-color, #7a9a7a);
      color: var(--rec-color, #7a9a7a);
      font-size: 13px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .rec-popup {
      font-size: 12.5px;
      color: var(--fg-2f4a3a, #2f4a3a);
      line-height: 1.45;
      min-width: 200px;
    }

    .rec-popup .rec-suit {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 700;
      margin: 2px 0 6px;
    }

    .rec-popup .rec-suit i {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: currentColor;
    }

    .rec-popup .rec-meta {
      margin-top: 6px;
      color: #7a9a7a;
      font-size: 11px;
    }

    .rec-popup .rec-text {
      margin-top: 6px;
      white-space: pre-line;
    }

    .d-rec {
      border: 1px solid var(--bd-e0ece4, #e0ece4);
      border-left: 4px solid var(--rec-color, #7a9a7a);
      border-radius: 8px;
      padding: 8px 10px;
      margin-bottom: 8px;
      font-size: 12px;
      color: var(--fg-2f4a3a, #2f4a3a);
    }

    .d-rec strong {
      display: block;
      color: var(--rec-color, var(--fg-1a3324, #1a3324));
      margin-bottom: 4px;
    }

    .d-rec p {
      margin-top: 4px;
      white-space: pre-line;
    }

    /* Map Layer Control Widget */
    .map-layer-control {
      position: absolute;
      top: 18px;
      right: 18px;
      z-index: 450 !important;
      display: inline-flex;
      flex-direction: column;
      background: rgba(var(--bg-ffffff-rgb, 255, 255, 255), 0.96);
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
      background: var(--bg-ffffff, #ffffff);
      color: var(--primary-forest);
      font-size: 18px;
      border-radius: var(--radius-sm);
      transition: background 0.15s ease;
    }

    .map-layer-control > button:hover {
      background: var(--bg-eef7f2, #eef7f2);
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
      background: var(--bg-ffffff, #ffffff);
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
      color: var(--fg-375342, #375342);
      padding: 8px 10px;
      border-radius: 6px;
      border: none;
      background: transparent;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .layer-option:hover {
      background: var(--bg-eef7f2, #eef7f2);
      color: var(--primary-forest);
    }

    .layer-option.active {
      background: var(--bg-e4f5eb, #e4f5eb);
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
      background: var(--bg-ffffff, #ffffff);
      border-left: 1px solid var(--bd-d5e5d8, #d5e5d8);
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
      background: var(--bg-f0f5f1, #f0f5f1);
      border: 1px solid var(--bd-d8e5dc, #d8e5dc);
      border-radius: 50%;
      width: 32px;
      height: 32px;
      font-size: 14px;
      cursor: pointer;
      color: var(--fg-4b6655, #4b6655);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 20;
      transition: all 0.15s ease;
    }

    .panel-close-btn:hover {
      background: var(--bg-e2ece5, #e2ece5);
      color: var(--primary-forest);
    }

    .sec {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--fg-5d7e6c, #5d7e6c);
      margin-bottom: 12px;
    }

    .d-card {
      background: var(--bg-f8faf8, #f8faf8);
      border: 1px solid var(--bd-e0ebe2, #e0ebe2);
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
      border-bottom: 1px solid var(--bd-edf4ef, #edf4ef);
    }

    .d-row:last-child {
      border-bottom: none;
    }

    .d-key {
      color: var(--fg-6a8c79, #6a8c79);
    }

    .d-val {
      font-weight: 600;
      color: var(--fg-1a3324, #1a3324);
      text-align: right;
    }

    .d-genera {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 4px;
    }

    .d-genera span {
      padding: 4px 10px;
      border-radius: 999px;
      background: var(--bg-edf7f2, #edf7f2);
      border: 1px solid var(--bd-bce6cf, #bce6cf);
      color: var(--fg-147a46, #147a46);
      font-size: 12px;
      font-weight: 700;
    }

    .d-empty {
      font-size: 12px;
      color: var(--fg-6a8a6a, #6a8a6a);
      margin-bottom: 6px;
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
      background: var(--bg-edf7f2, #edf7f2);
      border-color: var(--bd-b0e0c0, #b0e0c0);
      color: var(--fg-1e9e62, #1e9e62);
    }

    .tb {
      background: var(--bg-eef4ff, #eef4ff);
      border-color: var(--bd-b0c8f0, #b0c8f0);
      color: var(--fg-3060b0, #3060b0);
    }

    .div {
      border-top: 1px solid var(--bd-e8efe9, #e8efe9);
      margin: 16px 0;
    }

    .cw {
      position: relative;
      width: 100%;
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
      color: var(--fg-4ade80, #4ade80);
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
      color: var(--fg-4ade80, #4ade80);
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
      background: var(--bg-ffffff, #ffffff);
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
      border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
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

      .hero-stat-card:nth-child(2n) {
        border-right: none;
      }

      .hero-stat-card:last-child:nth-child(odd) {
        grid-column: span 2;
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

      .nav-btn-login {
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
        grid-column: auto;
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
  @include('partials.scrollbar-styles')
</head>

<body>
  <!-- Clean, Minimal Navigation Bar -->
  <header class="site-nav">
    <a href="/" class="brand-wrap" aria-label="MangroveMap Home">
      <div class="brand-logo-icon">
        <img src="/icon-192.png" alt="">
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

      @guest
        <a href="/login" class="nav-btn-login">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
      @endguest

      @include('partials.theme-toggle')

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
        <span class="hero-title-highlight">Classification System</span>
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
        {{-- All figures are from expert-approved delineations. --}}
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format($mappedAreaHa ?? 0, 2) }}<span>ha</span></div>
          <div class="stat-label">Mapped Mangrove Area</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format($barangayCount ?? 0) }}<span>barangays</span></div>
          <div class="stat-label">Areas Mapped</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format($generaCount ?? 0) }}<span>genera</span></div>
          <div class="stat-label">Mangrove Genera Recorded</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format(count($delineations ?? [])) }}<span>records</span></div>
          <div class="stat-label">Approved Delineations</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val">{{ number_format($contributorCount ?? 0) }}<span>people</span></div>
          <div class="stat-label">Contributors</div>
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
          <li><i class="bi bi-check-circle-fill"></i> Precision polygon area measurement in hectares</li>
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
          Continuous monitoring of mangrove areas, tracking species distributions such as Rhizophora, Avicennia, and Sonneratia, while supporting data-driven analysis and restoration planning.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Historical coverage trend charting &amp; analytics</li>
          <li><i class="bi bi-check-circle-fill"></i> Species &amp; genus composition breakdown</li>
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


          <div class="toolbar-right">
            <button class="map-action-btn" onclick="toggleLayerMenu()" title="Switch Satellite / Street Base Layers">
              <i class="bi bi-layers"></i> Basemap
            </button>
            @auth
              <a href="{{ route('map') }}" class="map-action-btn" style="background:var(--bg-eef7f2, #eef7f2); border-color:var(--bd-bce6cf, #bce6cf); color:var(--fg-147a46, #147a46); font-weight:600;">
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
                {{-- Legend doubles as layer toggles. --}}
                <div class="map-legend-float" id="mapLegend">
                  <button type="button" class="leg-collapse" id="legendCollapse" aria-expanded="true" aria-controls="legendBody" title="Hide legend">
                    <span class="leg-title">Legend</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                  </button>
                  <div id="legendBody">
                    <label class="leg-row leg-toggle">
                      <input type="checkbox" id="toggleDelineations" checked>
                      <span class="leg-dot" style="background:rgba(30,158,98,.5); border:1.5px solid #1e9e62"></span>
                      Mangrove areas <small>({{ count($delineations ?? []) }} · {{ number_format($mappedAreaHa ?? 0, 2) }} ha)</small>
                    </label>
                    <label class="leg-row leg-toggle">
                      <input type="checkbox" id="togglePins" checked>
                      <i class="bi bi-geo-alt-fill leg-pin" aria-hidden="true"></i>
                      Location pins
                    </label>
                    <label class="leg-row leg-toggle">
                      <input type="checkbox" id="toggleRecommendations" checked>
                      <span class="leg-rec-icon"><i class="bi bi-flower1" aria-hidden="true"></i></span>
                      Planting recommendations <small>({{ count($plantingRecommendations ?? []) }})</small>
                    </label>
                    <div class="leg-suitability" aria-label="Planting suitability colors">
                      @foreach ($suitabilityLevels ?? [] as $key => $label)
                        <span><i class="leg-swatch" data-suitability-swatch="{{ $key }}"></i>{{ $label }}</span>
                      @endforeach
                    </div>
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
                      <span class="d-key">Area</span>
                      <span class="d-val" id="dArea">-</span>
                    </div>
                    <div class="d-row" id="dLocationRow" style="display:none;">
                      <span class="d-key">Location</span>
                      <span class="d-val" id="dLocation">-</span>
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
                      <span class="d-val" id="dNotes" style="font-weight: 400; font-size: 12px; color: var(--fg-3a5a3a, #3a5a3a); line-height: 1.4; word-break: break-word;">-</span>
                    </div>
                  </div>
                  <div class="div"></div>
                  <div class="sec">Mangrove Genera Present</div>
                  <div id="dGenera" class="d-genera"></div>
                  <div class="div"></div>
                  <div class="sec">Planting Recommendation</div>
                  <div id="dRecommendations"></div>
                  <div class="div"></div>
                  <div class="sec">Coverage Trend (ha)</div>
                  <div id="trendEmpty" class="d-empty">No yearly observations recorded for this area yet.</div>
                  <div class="cw" id="trendWrap" style="height:110px; display:none;"><canvas id="trendC"></canvas></div>
                </div>
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
          <img src="/icon-192.png" alt="">
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
        <li><a href="{{ route('terms') }}">Terms</a></li>
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

    // Areas are measured on the server (same formula as the map editor) and always shown in hectares.
    function formatHectares(value) {
      const hectares = Number(value);
      if (!Number.isFinite(hectares) || hectares <= 0) return null;
      if (hectares < 0.01) return '< 0.01 ha';
      return `${hectares.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ha`;
    }

    let coverageTrendChart = null;
    function renderCoverageTrend(trend) {
      const points = Array.isArray(trend) ? trend : [];
      const wrap = document.getElementById('trendWrap');
      const empty = document.getElementById('trendEmpty');
      if (wrap) wrap.style.display = points.length ? 'block' : 'none';
      if (empty) empty.style.display = points.length ? 'none' : 'block';
      if (!points.length || typeof Chart === 'undefined') return;

      const labels = points.map(point => String(point.year));
      const values = points.map(point => Number(point.area_ha));
      if (coverageTrendChart) {
        coverageTrendChart.data.labels = labels;
        coverageTrendChart.data.datasets[0].data = values;
        coverageTrendChart.update();
        return;
      }
      coverageTrendChart = new Chart(document.getElementById('trendC'), {
        type: 'line',
        data: {
          labels,
          datasets: [{
            data: values,
            borderColor: '#1e9e62',
            backgroundColor: 'rgba(30,158,98,.12)',
            fill: true,
            tension: 0.38,
            pointRadius: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: context => formatHectares(context.parsed.y) || '0 ha' } }
          },
          scales: {
            y: { ticks: { font: { size: 9 }, callback: v => `${v} ha` } },
            x: { ticks: { font: { size: 9 } } }
          }
        }
      });
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

      // The clicked shape's area; when a record has several shapes, also show the record total.
      const featureArea = formatHectares(feature.area_ha);
      const recordArea = formatHectares(record.area_ha);
      const areaStr = featureArea && recordArea && Number(record.area_ha) > Number(feature.area_ha)
        ? `${featureArea} (of ${recordArea})`
        : featureArea;
      const areaRow = document.getElementById('dAreaRow');
      const dAreaEl = document.getElementById('dArea');
      if (areaStr && dAreaEl && areaRow) {
        dAreaEl.textContent = areaStr;
        areaRow.style.display = 'flex';
      } else if (areaRow) {
        areaRow.style.display = 'none';
      }

      const locationRow = document.getElementById('dLocationRow');
      const dLocationEl = document.getElementById('dLocation');
      if (locationRow && dLocationEl) {
        dLocationEl.textContent = feature.location || '';
        locationRow.style.display = feature.location ? 'flex' : 'none';
      }

      const generaEl = document.getElementById('dGenera');
      if (generaEl) {
        const genera = Array.isArray(feature.genera) && feature.genera.length ? feature.genera : (record.genera || []);
        generaEl.replaceChildren(...(genera.length
          ? genera.map(name => Object.assign(document.createElement('span'), { textContent: name }))
          : [Object.assign(document.createElement('div'), { className: 'd-empty', textContent: 'No genera recorded.' })]));
      }

      renderDelineationRecommendations(record);
      renderCoverageTrend(record.coverage_trend);

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

    // ---- Map layers: approved areas, a location pin on each, and planting recommendations ----
    const SUITABILITY_COLORS = {
      highly_suitable: '#1e9e62',
      moderately_suitable: '#7cb342',
      marginally_suitable: '#c07818',
      not_suitable: '#d04030',
    }; // same palette as the expert Recommender tab
    const plantingRecommendations = @json($plantingRecommendations ?? []);

    // Recommendations sit in a lower pane so clicking a delineation still opens its details.
    mainMap.createPane('recommendationPane');
    mainMap.getPane('recommendationPane').style.zIndex = 390;
    const delineationLayer = L.featureGroup().addTo(mainMap);
    const pinLayer = L.featureGroup().addTo(mainMap);
    const recommendationLayer = L.featureGroup().addTo(mainMap);

    const pinIcon = L.divIcon({
      className: '',
      html: '<span class="map-pin"><i class="bi bi-geo-alt-fill"></i></span>',
      iconSize: [30, 30],
      iconAnchor: [15, 29],
      popupAnchor: [0, -26],
      tooltipAnchor: [0, -24],
    });

    // Elements built with textContent: names, notes and advice are user input.
    function el(tag, props = {}, children = []) {
      const node = Object.assign(document.createElement(tag), props);
      node.append(...children.filter(Boolean));
      return node;
    }

    function delineationPopup(record, feature) {
      const details = [formatHectares(feature.area_ha), feature.location].filter(Boolean).join(' · ');
      return el('div', {}, [
        el('strong', { textContent: record.name || 'Approved delineation' }),
        details && el('div', { textContent: details, style: 'font-size:12px;color:#556b56;margin-top:2px;' }),
      ]);
    }

    function recommendationPopup(rec) {
      const color = SUITABILITY_COLORS[rec.suitability] || '#7a9a7a';
      const genera = Array.isArray(rec.recommended_genera) ? rec.recommended_genera : [];
      const meta = [
        formatHectares(rec.area_ha),
        rec.features?.[0]?.location,
        rec.expert_name ? `by ${rec.expert_name}` : null,
      ].filter(Boolean).join(' · ');
      return el('div', { className: 'rec-popup' }, [
        el('strong', { textContent: rec.name || 'Planting recommendation' }),
        el('div', { className: 'rec-suit', style: `color:${color}` }, [el('i'), el('span', { textContent: rec.suitability_label || rec.suitability })]),
        el('div', { textContent: genera.length ? `Recommended genus: ${genera.join(', ')}` : 'Not suitable for planting' }),
        rec.recommendation?.trim() && el('div', { className: 'rec-text', textContent: rec.recommendation.trim() }),
        meta && el('div', { className: 'rec-meta', textContent: meta }),
      ]);
    }

    function pinPosition(feature, layer) {
      if (feature.type === 'point') return feature.coords;
      if (layer instanceof L.Polygon) return layer.getCenter();
      if (layer instanceof L.Polyline) {
        const points = layer.getLatLngs();
        return points[Math.floor(points.length / 2)];
      }
      return null;
    }

    function drawSavedDelineations() {
      (Array.isArray(savedDelineations) ? savedDelineations : []).forEach((record) => {
        (Array.isArray(record.features) ? record.features : []).forEach((feature) => {
          let shape = null;
          if (feature.type === 'line') {
            shape = L.polyline(feature.coords, { color: '#1e9e62', weight: 3 }).addTo(delineationLayer);
          } else if (feature.type === 'area' || !feature.type) {
            shape = L.polygon(feature.coords, { color: '#1e9e62', fillColor: '#1e9e62', fillOpacity: 0.28, weight: 2 }).addTo(delineationLayer);
          }

          const position = pinPosition(feature, shape);
          const pin = position
            ? L.marker(position, { icon: pinIcon, title: record.name || 'Approved delineation', riseOnHover: true }).addTo(pinLayer)
            : null;
          if (pin) pin.bindTooltip(record.name || 'Approved delineation', { direction: 'top' });

          [shape, pin].filter(Boolean).forEach(layer => {
            layer.bindPopup(delineationPopup(record, feature));
            layer.on('click', (e) => {
              if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
              showDelineationDetails(record, feature, shape || pin);
            });
          });
        });
      });
    }

    function drawPlantingRecommendations() {
      plantingRecommendations.forEach(rec => {
        const color = SUITABILITY_COLORS[rec.suitability] || '#7a9a7a';
        const tooltip = `${rec.name} · ${rec.suitability_label || rec.suitability}`;
        (rec.features || []).forEach(feature => {
          const polygon = L.polygon(feature.coords, {
            pane: 'recommendationPane',
            color,
            weight: 2,
            dashArray: '6 4',
            fillColor: color,
            fillOpacity: 0.16,
          }).addTo(recommendationLayer);
          polygon.bindTooltip(tooltip, { sticky: true });
          polygon.bindPopup(recommendationPopup(rec), { maxWidth: 280 });
          L.marker(polygon.getCenter(), {
            icon: L.divIcon({
              className: '',
              html: `<span class="rec-marker" style="--rec-color:${color}"><i class="bi bi-flower1"></i></span>`,
              iconSize: [26, 26],
              // Offset to the lower right so it isn't hidden under the area's location pin.
              iconAnchor: [-4, -2],
              popupAnchor: [17, 0],
            }),
            title: tooltip,
            riseOnHover: true,
          }).addTo(recommendationLayer).bindPopup(recommendationPopup(rec), { maxWidth: 280 });
        });
      });
    }

    drawSavedDelineations();
    drawPlantingRecommendations();

    // Open on the mapped areas rather than a fixed view.
    (function fitToData() {
      const bounds = L.latLngBounds([]);
      [delineationLayer, pinLayer, recommendationLayer].forEach(group => {
        if (group.getLayers().length) bounds.extend(group.getBounds());
      });
      if (bounds.isValid()) mainMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    })();

    // Legend checkboxes show or hide each layer.
    [
      ['toggleDelineations', delineationLayer],
      ['togglePins', pinLayer],
      ['toggleRecommendations', recommendationLayer],
    ].forEach(([id, layer]) => {
      document.getElementById(id)?.addEventListener('change', (event) => {
        event.target.checked ? layer.addTo(mainMap) : layer.remove();
      });
    });
    document.querySelectorAll('[data-suitability-swatch]').forEach(swatch => {
      swatch.style.color = SUITABILITY_COLORS[swatch.dataset.suitabilitySwatch] || '#7a9a7a';
    });
    document.getElementById('legendCollapse')?.addEventListener('click', (event) => {
      const legend = document.getElementById('mapLegend');
      const collapsed = legend.classList.toggle('collapsed');
      event.currentTarget.setAttribute('aria-expanded', String(!collapsed));
      event.currentTarget.title = collapsed ? 'Show legend' : 'Hide legend';
    });
    // Keep the legend compact on phones.
    if (window.matchMedia('(max-width: 640px)').matches) {
      document.getElementById('mapLegend')?.classList.add('collapsed');
      document.getElementById('legendCollapse')?.setAttribute('aria-expanded', 'false');
    }

    function renderDelineationRecommendations(record) {
      const container = document.getElementById('dRecommendations');
      if (!container) return;
      const linked = plantingRecommendations.filter(rec => Number(rec.delineation_id) === Number(record.id));
      container.replaceChildren(...(linked.length
        ? linked.map(rec => {
          const genera = Array.isArray(rec.recommended_genera) ? rec.recommended_genera : [];
          return el('div', { className: 'd-rec', style: `--rec-color:${SUITABILITY_COLORS[rec.suitability] || '#7a9a7a'}` }, [
            el('strong', { textContent: rec.suitability_label || rec.suitability }),
            el('div', { textContent: genera.length ? `Plant: ${genera.join(', ')}` : 'Not suitable for planting' }),
            rec.recommendation?.trim() && el('p', { textContent: rec.recommendation.trim() }),
            rec.expert_name && el('div', { className: 'd-empty', style: 'margin:4px 0 0;', textContent: `By ${rec.expert_name}` }),
          ]);
        })
        : [el('div', { className: 'd-empty', textContent: 'No planting recommendation for this area yet.' })]));
    }

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

    window.showDelineation = () => {
      window.location.href = "{{ Auth::check() ? route('drone-imagery.page') : route('login') }}";
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
      }, 150);
    };

    window.addEventListener('resize', () => {
      setTimeout(() => {
        if (mainMap) mainMap.invalidateSize();
      }, 120);
    });

    // Auto-Resize Map on DOM observation
    let indexMapResizeTimer = null;
    const mainMapEl = document.getElementById('mainMap');
    if (typeof ResizeObserver !== 'undefined') {
      const mapObserver = new ResizeObserver(() => {
        clearTimeout(indexMapResizeTimer);
        indexMapResizeTimer = setTimeout(() => {
          if (mainMap) mainMap.invalidateSize({ pan: false });
        }, 180);
      });
      if (mainMapEl) mapObserver.observe(mainMapEl);
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