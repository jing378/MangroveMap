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
  <link rel="icon" type="image/png" href="/favicon-round.png" />
  
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
      position: relative;
      width: 40px;
      height: 40px;
      background: none;
      border: none;
      border-radius: 10px;
      color: var(--primary-forest);
      cursor: pointer;
      padding: 0;
      transition: background-color 0.2s ease;
    }

    .nav-mobile-toggle:hover,
    .nav-mobile-toggle[aria-expanded="true"] {
      background-color: rgba(30, 158, 98, 0.1);
    }

    .nav-mobile-toggle:focus-visible {
      outline: 3px solid rgba(30, 158, 98, 0.35);
      outline-offset: 2px;
    }

    .nav-mobile-toggle:active .hamburger-bars {
      transform: scale(0.9);
    }

    /* Hamburger bars: the middle one fades out, the outer two meet in the middle and rotate into an X. */
    .hamburger-bars {
      position: absolute;
      top: 50%;
      left: 50%;
      width: 22px;
      height: 16px;
      margin: -8px 0 0 -11px;
      transition: transform 0.15s ease;
    }

    .hamburger-bars span {
      position: absolute;
      left: 0;
      width: 100%;
      height: 2.5px;
      border-radius: 2px;
      background: currentColor;
      transition: transform 0.35s cubic-bezier(0.68, -0.4, 0.27, 1.4), opacity 0.2s ease, top 0.25s ease;
    }

    .hamburger-bars span:nth-child(1) { top: 0; }
    .hamburger-bars span:nth-child(2) { top: 6.75px; }
    .hamburger-bars span:nth-child(3) { top: 13.5px; }

    .nav-mobile-toggle[aria-expanded="true"] .hamburger-bars span:nth-child(1) {
      top: 6.75px;
      transform: rotate(45deg);
    }

    .nav-mobile-toggle[aria-expanded="true"] .hamburger-bars span:nth-child(2) {
      opacity: 0;
      transform: scaleX(0.2);
    }

    .nav-mobile-toggle[aria-expanded="true"] .hamburger-bars span:nth-child(3) {
      top: 6.75px;
      transform: rotate(-45deg);
    }

    /* Polished Hero Section */
    .hero-section {
      position: relative;
      min-height: 86vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0b2416;
      padding: 80px 24px 110px 24px;
      overflow: hidden;
      isolation: isolate;
    }

    /* Hero photo: slow Ken Burns zoom, nudged by the mouse (--px/--py set in JS). */
    .hero-bg {
      position: absolute;
      inset: -40px;
      background-image: url("{{ asset('images/mangrove-login.jpg') }}");
      background-size: cover;
      background-position: center center;
      transform: translate3d(calc(var(--px, 0) * -18px), calc(var(--py, 0) * -14px), 0);
      transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
      z-index: 0;
    }

    .hero-bg::after {
      content: '';
      position: absolute;
      inset: 0;
      background: inherit;
      background-size: cover;
      animation: kenBurns 28s ease-in-out infinite alternate;
    }

    @keyframes kenBurns {
      from { transform: scale(1.02); }
      to { transform: scale(1.14) translate(-1.5%, -1%); }
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
      grid-template-columns: repeat(2, 1fr);
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

    .panel-grip {
      display: none;
    }

    /* Phones: details panel becomes a bottom sheet so the map stays visible above it. */
    @media (max-width: 768px) {
      #mapRightPanel {
        top: auto;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: none;
        height: 58%;
        border-left: none;
        border-top: 1px solid var(--bd-d5e5d8, #d5e5d8);
        border-radius: 16px 16px 0 0;
        box-shadow: 0 -6px 24px rgba(0, 0, 0, 0.18);
        transform: translateY(105%);
      }

      #mapRightPanel.open {
        transform: translateY(0);
      }

      #mapRightPanel .scroll {
        padding: 6px 16px 20px;
        overscroll-behavior: contain;
      }

      .panel-grip {
        display: block;
        flex-shrink: 0;
        width: 40px;
        height: 4px;
        margin: 8px auto 4px;
        border-radius: 999px;
        background: var(--bg-cfdcd3, #cfdcd3);
      }

      .panel-close-btn {
        top: 10px;
        right: 12px;
      }
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
      display: flex;
      visibility: hidden;
      opacity: 0;
      transform: translateY(-12px);
      pointer-events: none;
      transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0s linear 0.3s;
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
      visibility: visible;
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
      transition: opacity 0.25s ease, transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), visibility 0s;
    }

    .mobile-menu-drawer a {
      text-decoration: none;
      color: var(--primary-forest);
      font-size: 16px;
      font-weight: 600;
      padding: 8px 0;
      border-bottom: 1px solid var(--bd-f0f4f0, #f0f4f0);
      opacity: 0;
      transform: translateY(-6px);
      transition: opacity 0.2s ease, transform 0.25s ease;
    }

    /* Links follow one after another as the menu opens. */
    .mobile-menu-drawer.open a {
      opacity: 1;
      transform: translateY(0);
      transition-delay: calc(var(--i, 0) * 45ms + 60ms);
    }

    @media (prefers-reduced-motion: reduce) {
      .mobile-menu-drawer,
      .mobile-menu-drawer a,
      .hamburger-bars,
      .hamburger-bars span {
        transition-duration: 0.01ms !important;
        transition-delay: 0s !important;
      }
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

    /* ===================== Motion & interactivity ===================== */

    /* Reading progress bar along the top of the nav. */
    .scroll-progress {
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      width: 100%;
      transform-origin: 0 50%;
      transform: scaleX(var(--progress, 0));
      background: linear-gradient(90deg, #144a2b, #1e9e62, #4ade80);
      z-index: 10001;
      pointer-events: none;
    }

    /* Nav tightens and gains a shadow once the page scrolls. */
    .site-nav.scrolled {
      height: 58px;
      box-shadow: 0 6px 24px rgba(14, 56, 32, 0.1);
    }

    .nav-links a {
      position: relative;
    }

    .nav-links a::after {
      content: '';
      position: absolute;
      left: 0;
      right: 0;
      bottom: -8px;
      height: 2px;
      border-radius: 2px;
      background: var(--primary-green);
      transform: scaleX(0);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .nav-links a:hover::after,
    .nav-links a.active::after {
      transform: scaleX(1);
    }

    .nav-links a.active {
      color: var(--primary-green);
    }

    /* Hero layers: particles canvas, cursor glow, wave. */
    .hero-overlay {
      background:
        radial-gradient(ellipse at 50% 120%, rgba(30, 158, 98, 0.35), transparent 60%),
        linear-gradient(180deg, rgba(10, 32, 20, 0.72) 0%, rgba(12, 38, 24, 0.82) 50%, rgba(9, 28, 17, 0.94) 100%);
    }

    .hero-particles {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      z-index: 1;
      pointer-events: none;
    }

    .hero-glow {
      position: absolute;
      width: 520px;
      height: 520px;
      left: 0;
      top: 0;
      margin: -260px 0 0 -260px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(74, 222, 128, 0.16) 0%, transparent 65%);
      transform: translate3d(var(--gx, 50vw), var(--gy, 40vh), 0);
      transition: transform 0.25s ease-out, opacity 0.4s ease;
      z-index: 1;
      pointer-events: none;
      opacity: 0;
    }

    .hero-section:hover .hero-glow {
      opacity: 1;
    }

    .hero-waves {
      position: absolute;
      left: 0;
      right: 0;
      bottom: -1px;
      height: 90px;
      z-index: 2;
      pointer-events: none;
      overflow: hidden;
    }

    .hero-waves svg {
      position: absolute;
      bottom: 0;
      width: 200%;
      height: 100%;
    }

    .hero-waves .wave-back {
      fill: rgba(30, 158, 98, 0.22);
      animation: waveDrift 16s linear infinite;
    }

    .hero-waves .wave-front {
      fill: var(--bg-f7faf7, #f7faf7);
      animation: waveDrift 10s linear infinite reverse;
    }

    @keyframes waveDrift {
      from { transform: translateX(0); }
      to { transform: translateX(-50%); }
    }

    /* Staggered entrance for the hero copy. */
    .hero-enter {
      opacity: 0;
      transform: translateY(26px);
      filter: blur(6px);
      animation: heroEnter 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      animation-delay: calc(var(--d, 0) * 120ms + 150ms);
    }

    @keyframes heroEnter {
      to { opacity: 1; transform: none; filter: blur(0); }
    }

    /* Rotating word in the headline. */
    .hero-rotator {
      display: inline-grid;
      vertical-align: bottom;
      grid-template-columns: minmax(0, 1fr);
      justify-items: center;
      overflow: hidden;
      padding-bottom: 0.14em;
      margin-bottom: -0.14em;
      transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .hero-rotator > span {
      grid-area: 1 / 1;
      justify-self: center;
      opacity: 0;
      transform: translateY(105%);
      transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
    }

    .hero-rotator > span.on {
      opacity: 1;
      transform: none;
    }

    .hero-rotator > span.out {
      opacity: 0;
      transform: translateY(-105%);
    }

    .hero-title-highlight {
      background-size: 200% auto;
      animation: shimmerText 6s linear infinite;
    }

    @keyframes shimmerText {
      to { background-position: 200% center; }
    }

    .btn-hero-primary {
      position: relative;
      overflow: hidden;
    }

    .btn-hero-primary::after {
      content: '';
      position: absolute;
      top: 0;
      left: -60%;
      width: 40%;
      height: 100%;
      background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.35), transparent);
      transform: skewX(-20deg);
      animation: btnShine 4.5s ease-in-out infinite 2s;
    }

    @keyframes btnShine {
      0%, 70% { left: -60%; }
      100% { left: 130%; }
    }

    .hero-stat-card {
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .hero-stat-card:hover {
      transform: translateY(-4px);
    }

    .hero-stat-card:hover .stat-val {
      color: #bbf7d0;
    }

    .stat-val {
      font-variant-numeric: tabular-nums;
      transition: color 0.2s ease;
    }

    /* Scroll cue under the hero. */
    .scroll-cue {
      position: relative;
      margin-top: 36px;
      width: 26px;
      height: 42px;
      --cue-ink: rgba(255, 255, 255, 0.55);
      border: 2px solid var(--cue-ink);
      border-radius: 14px;
      display: block;
      opacity: 0;
      animation: heroEnter 0.8s ease forwards 1.4s;
    }

    .scroll-cue::before {
      content: '';
      position: absolute;
      left: 50%;
      top: 7px;
      width: 4px;
      height: 8px;
      margin-left: -2px;
      border-radius: 2px;
      --cue-dot: #ffffff;
      background: var(--cue-dot);
      animation: scrollDot 1.8s ease-in-out infinite;
    }

    @keyframes scrollDot {
      0% { transform: translateY(0); opacity: 1; }
      70% { transform: translateY(14px); opacity: 0; }
      100% { transform: translateY(0); opacity: 0; }
    }

    /* Reveal on scroll (only once JS has marked the page, so nothing hides without it). */
    .js-motion .reveal {
      opacity: 0;
      transform: translateY(34px) scale(0.98);
      transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
      transition-delay: calc(var(--i, 0) * 110ms);
    }

    .js-motion .reveal.visible {
      opacity: 1;
      transform: none;
    }

    /* Feature cards: 3D tilt (--rx/--ry) and a spotlight that follows the cursor (--mx/--my). */
    .features-grid {
      perspective: 1200px;
    }

    .feature-card.tilting {
      transition: transform 0.08s linear, box-shadow 0.28s ease, border-color 0.28s ease;
    }

    .feature-card:hover {
      transform: translateY(-5px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg));
    }

    .feature-card::after {
      content: '';
      position: absolute;
      inset: 0;
      border-radius: inherit;
      background: radial-gradient(380px circle at var(--mx, 50%) var(--my, 50%), rgba(30, 158, 98, 0.1), transparent 45%);
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
    }

    .feature-card:hover::after {
      opacity: 1;
    }

    .feature-card > * {
      position: relative;
      z-index: 1;
    }

    .feature-points li {
      transition: transform 0.25s ease;
    }

    .feature-card:hover .feature-points li {
      transform: translateX(4px);
    }

    .feature-card:hover .feature-points li:nth-child(2) { transition-delay: 40ms; }
    .feature-card:hover .feature-points li:nth-child(3) { transition-delay: 80ms; }

    /* How it works: interactive step-through. */
    .how-section {
      max-width: 1200px;
      margin: 0 auto;
      padding: 10px 24px 90px 24px;
    }

    .how-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr);
      gap: 36px;
      align-items: stretch;
    }

    .how-steps {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .how-step {
      width: 100%;
      display: grid;
      grid-template-columns: 46px 1fr;
      gap: 14px;
      align-items: center;
      text-align: left;
      font-family: inherit;
      padding: 16px 18px;
      background: var(--bg-ffffff, #ffffff);
      border: 1px solid var(--earth-border);
      border-radius: var(--radius-md);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
    }

    .how-step:hover {
      transform: translateX(4px);
      border-color: rgba(30, 158, 98, 0.35);
    }

    .how-step:focus-visible {
      outline: 3px solid rgba(30, 158, 98, 0.35);
      outline-offset: 2px;
    }

    .how-step.active {
      border-color: var(--primary-green);
      box-shadow: var(--shadow-md);
    }

    .how-step-num {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: var(--primary-green);
      background: var(--primary-mint);
      transition: background 0.3s ease, color 0.3s ease, transform 0.3s ease;
    }

    .how-step.active .how-step-num {
      background: linear-gradient(135deg, #1e9e62, #144a2b);
      color: #ffffff;
      transform: rotate(-6deg) scale(1.05);
    }

    .how-step-title {
      font-size: 15.5px;
      font-weight: 700;
      color: var(--primary-forest);
    }

    .how-step-sub {
      font-size: 13px;
      color: var(--text-muted);
    }

    /* Auto-advance timer bar inside the active step. */
    .how-step-bar {
      position: absolute;
      left: 0;
      bottom: 0;
      height: 3px;
      width: 100%;
      background: var(--primary-green);
      transform-origin: 0 50%;
      transform: scaleX(0);
    }

    .how-step.active .how-step-bar {
      animation: stepTimer var(--how-ms, 6000ms) linear forwards;
    }

    .how-steps.paused .how-step-bar {
      animation-play-state: paused;
    }

    @keyframes stepTimer {
      to { transform: scaleX(1); }
    }

    .how-stage {
      position: relative;
      min-height: 380px;
      border-radius: var(--radius-xl);
      overflow: hidden;
      background: linear-gradient(160deg, #0e3820 0%, #144a2b 55%, #1b6b41 100%);
      box-shadow: var(--shadow-lg);
      color: #ffffff;
    }

    .how-stage::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
      background-size: 32px 32px;
      animation: gridPan 20s linear infinite;
    }

    @keyframes gridPan {
      to { background-position: 32px 32px; }
    }

    .how-panel {
      position: absolute;
      inset: 0;
      padding: 30px;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      opacity: 0;
      visibility: hidden;
      transform: translateY(16px);
      transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), visibility 0s linear 0.5s;
    }

    .how-panel.active {
      opacity: 1;
      visibility: visible;
      transform: none;
      transition: opacity 0.5s ease, transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), visibility 0s;
    }

    .how-art {
      position: absolute;
      top: 24px;
      left: 30px;
      right: 30px;
      height: 200px;
    }

    .how-art svg {
      width: 100%;
      height: 100%;
      overflow: visible;
    }

    .how-panel h3 {
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 8px;
      letter-spacing: -0.01em;
    }

    .how-panel p {
      font-size: 14.5px;
      line-height: 1.65;
      color: #d1e7d9;
      max-width: 520px;
    }

    /* Step artwork animations (they replay whenever a panel becomes active). */
    .how-panel.active .draw-path {
      stroke-dasharray: 900;
      stroke-dashoffset: 900;
      animation: drawLine 2.2s cubic-bezier(0.65, 0, 0.35, 1) forwards;
    }

    .how-panel.active .fill-in {
      opacity: 0;
      animation: fadeFill 0.8s ease forwards 1.9s;
    }

    @keyframes drawLine {
      to { stroke-dashoffset: 0; }
    }

    @keyframes fadeFill {
      to { opacity: 1; }
    }

    .how-panel.active .pop {
      transform-box: fill-box;
      transform-origin: center;
      transform: scale(0);
      animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
      animation-delay: calc(var(--d, 0) * 1ms);
    }

    @keyframes popIn {
      to { transform: scale(1); }
    }

    .how-panel.active .scan-line {
      animation: scan 2.4s ease-in-out infinite;
    }

    @keyframes scan {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(180px); }
    }

    .how-panel.active .drone {
      animation: droneFly 4s ease-in-out infinite;
    }

    @keyframes droneFly {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(260px, 10px); }
    }

    .how-panel.active .grow-bar {
      transform-box: fill-box;
      transform-origin: bottom;
      transform: scaleY(0);
      animation: growBar 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
      animation-delay: calc(var(--d, 0) * 1ms);
    }

    @keyframes growBar {
      to { transform: scaleY(1); }
    }

    /* Impact cards: big animated figures and a slow aurora behind them. */
    .impact-section {
      overflow: hidden;
    }

    .impact-section::before {
      content: '';
      position: absolute;
      width: 900px;
      height: 900px;
      top: -450px;
      left: -200px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(74, 222, 128, 0.14), transparent 60%);
      animation: aurora 18s ease-in-out infinite alternate;
      pointer-events: none;
    }

    @keyframes aurora {
      to { transform: translate(60vw, 300px); }
    }

    .impact-grid {
      position: relative;
    }

    .impact-figure {
      font-size: 40px;
      font-weight: 800;
      line-height: 1;
      letter-spacing: -0.03em;
      margin-bottom: 6px;
      --figure-from: #bbf7d0;
      background: linear-gradient(120deg, var(--figure-from), #4ade80);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      font-variant-numeric: tabular-nums;
    }

    .impact-icon {
      display: inline-flex;
      transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .impact-card:hover .impact-icon {
      transform: translateY(-4px) rotate(-8deg) scale(1.12);
    }

    /* Map frame lifts a little as it enters. */
    .map-browser-frame {
      transition: box-shadow 0.4s ease;
    }

    .map-browser-frame:hover {
      box-shadow: 0 26px 60px rgba(14, 56, 32, 0.18);
    }

    @media (max-width: 900px) {
      .how-layout {
        grid-template-columns: 1fr;
      }

      .how-stage {
        min-height: 360px;
      }
    }

    @media (max-width: 768px) {
      .hero-section {
        padding-bottom: 100px;
      }

      .scroll-cue {
        display: none;
      }

      .hero-waves {
        height: 60px;
      }

      .how-section {
        padding: 0 18px 60px 18px;
      }

      .how-panel {
        padding: 22px;
      }

      .how-art {
        left: 22px;
        right: 22px;
        height: 170px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        animation-delay: 0s !important;
        transition-duration: 0.01ms !important;
        transition-delay: 0s !important;
      }

      .hero-enter, .scroll-cue {
        opacity: 1;
        transform: none;
        filter: none;
      }

      .hero-particles, .hero-glow {
        display: none;
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
      <button type="button" class="nav-mobile-toggle" id="mobileMenuBtn" aria-label="Open navigation menu" title="Menu"
        aria-controls="mobileMenuDrawer" aria-expanded="false">
        {{-- Three bars that turn into an X while the menu is open. --}}
        <span class="hamburger-bars" aria-hidden="true">
          <span></span><span></span><span></span>
        </span>
      </button>
    </div>
  </header>

  <!-- Mobile Drawer Menu -->
  <div class="mobile-menu-drawer" id="mobileMenuDrawer">
    <a href="#overview" onclick="closeMobileMenu()"><i class="bi bi-info-circle"></i> Overview</a>
    <a href="#features" onclick="closeMobileMenu()"><i class="bi bi-grid-1x2"></i> System Capabilities</a>
    <a href="#how-it-works" onclick="closeMobileMenu()"><i class="bi bi-diagram-3"></i> How It Works</a>
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
    <div class="hero-bg" aria-hidden="true"></div>
    <div class="hero-overlay"></div>
    <canvas class="hero-particles" id="heroParticles" aria-hidden="true"></canvas>
    <div class="hero-glow" aria-hidden="true"></div>
    <div class="hero-content">
      <div class="hero-eyebrow hero-enter" style="--d:0">
        <span class="pulse-dot"></span>
        <span>Geospatial Environmental Intelligence &amp; Coastal Conservation</span>
      </div>

      <h1 class="hero-title hero-enter" style="--d:1">
        Mangrove <span style="white-space: nowrap"><span class="hero-rotator" id="heroRotator" aria-live="off"><span class="on">Mapping</span><span>Monitoring</span><span>Restoration</span></span> &amp;</span><br />
        <span class="hero-title-highlight">Classification System</span>
      </h1>

      <p class="hero-description hero-enter" style="--d:2">
        A Web GIS platform for mapping the mangrove forests of Southern Leyte. Residents draw mangrove boundaries on the map, experts review them, and drone imagery is classified by an AI model to help outline mangrove cover for conservation and restoration planning.
      </p>

      <div class="hero-actions hero-enter" style="--d:3">
        <a href="#map-section" onclick="scrollToMap(event)" class="btn-hero-primary" id="exploreMapHeroBtn">
          <i class="bi bi-compass-fill"></i> Explore Map
        </a>
        <a href="#how-it-works" class="btn-hero-secondary">
          <i class="bi bi-play-circle-fill"></i> See How It Works
        </a>
      </div>

      <!-- Live GIS KPI / Statistics Strip -->
      <div class="hero-stats-strip hero-enter" style="--d:4">
        {{-- All figures are from expert-approved delineations. data-count drives the count-up animation. --}}
        <div class="hero-stat-card">
          <div class="stat-val"><b data-count="{{ round($mappedAreaHa ?? 0, 2) }}" data-decimals="2">{{ number_format($mappedAreaHa ?? 0, 2) }}</b><span>ha</span></div>
          <div class="stat-label">Mapped Mangrove Area</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val"><b data-count="{{ $barangayCount ?? 0 }}">{{ number_format($barangayCount ?? 0) }}</b><span>barangays</span></div>
          <div class="stat-label">Areas Mapped</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val"><b data-count="{{ $generaCount ?? 0 }}">{{ number_format($generaCount ?? 0) }}</b><span>genera</span></div>
          <div class="stat-label">Mangrove Genera Recorded</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val"><b data-count="{{ count($delineations ?? []) }}">{{ number_format(count($delineations ?? [])) }}</b><span>records</span></div>
          <div class="stat-label">Approved Delineations</div>
        </div>
        <div class="hero-stat-card">
          <div class="stat-val"><b data-count="{{ $contributorCount ?? 0 }}">{{ number_format($contributorCount ?? 0) }}</b><span>people</span></div>
          <div class="stat-label">Contributors</div>
        </div>
      </div>

      <a href="#features" class="scroll-cue" aria-label="Scroll to capabilities"></a>
    </div>

    {{-- Two looping waves; each path repeats once so translateX(-50%) loops seamlessly. --}}
    <div class="hero-waves" aria-hidden="true">
      <svg class="wave-back" viewBox="0 0 2880 90" preserveAspectRatio="none">
        <path d="M0 45 C240 10 480 80 720 45 C960 10 1200 80 1440 45 C1680 10 1920 80 2160 45 C2400 10 2640 80 2880 45 V90 H0 Z" />
      </svg>
      <svg class="wave-front" viewBox="0 0 2880 90" preserveAspectRatio="none">
        <path d="M0 60 C360 30 720 85 1080 60 C1260 48 1350 50 1440 60 C1800 30 2160 85 2520 60 C2700 48 2790 50 2880 60 V90 H0 Z" />
      </svg>
    </div>
  </section>

  <!-- Polished Feature Section -->
  <section class="section-wrap" id="features">
    <div class="section-header reveal">
      <div class="section-kicker">
        <i class="bi bi-stars"></i> Core Architecture
      </div>
      <h2 class="section-title">Comprehensive Environmental GIS Tools</h2>
      <p class="section-subtitle">
        Combining community mapping, expert review, and AI-assisted drone imagery to protect vital mangrove ecosystems.
      </p>
    </div>

    <div class="features-grid">
      <!-- Feature 1: Mangrove Mapping -->
      <div class="feature-card reveal" style="--i:0">
        <div class="feature-icon-box">
          <i class="bi bi-map-fill"></i>
        </div>
        <h3 class="feature-title">Mangrove Mapping</h3>
        <p class="feature-desc">
          Residents draw mangrove areas directly on the map and submit them for review. Each delineation is matched to its barangay and municipality, and experts approve it, reject it with feedback, or correct its boundary.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Draw polygons with area measured in hectares</li>
          <li><i class="bi bi-check-circle-fill"></i> Expert approval with revise-and-resubmit for rejected areas</li>
          <li><i class="bi bi-check-circle-fill"></i> Automatic barangay and municipality lookup</li>
        </ul>
      </div>

      <!-- Feature 2: GIS Visualization -->
      <div class="feature-card reveal" style="--i:1">
        <div class="feature-icon-box">
          <i class="bi bi-layers-half"></i>
        </div>
        <h3 class="feature-title">GIS Visualization</h3>
        <p class="feature-desc">
          An interactive map with ESRI World Imagery satellite, OpenStreetMap street, and topographic basemaps, with approved mangrove areas, location pins, and planting recommendations drawn as layers you can switch on and off.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Satellite, street, and topographic basemaps</li>
          <li><i class="bi bi-check-circle-fill"></i> Toggleable layers for areas, pins, and recommendations</li>
          <li><i class="bi bi-check-circle-fill"></i> Click any area for its genera, recommendation, and trend</li>
        </ul>
      </div>

      <!-- Feature: Drone Imagery & AI Segmentation -->
      <div class="feature-card reveal" style="--i:0">
        <div class="feature-icon-box">
          <i class="bi bi-airplane-fill"></i>
        </div>
        <h3 class="feature-title">Drone Imagery &amp; AI Detection</h3>
        <p class="feature-desc">
          Upload drone orthomosaics (GeoTIFF) to view them as high-resolution map layers. A U-Net deep learning model then detects mangrove cover in the imagery and turns it into draft boundaries you can review and submit.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> GeoTIFF upload with automatic map tiling</li>
          <li><i class="bi bi-check-circle-fill"></i> AI mangrove segmentation into draft polygons</li>
          <li><i class="bi bi-check-circle-fill"></i> Private layers, visible only to the uploader</li>
        </ul>
      </div>

      <!-- Feature 3: Mangrove Monitoring -->
      <div class="feature-card reveal" style="--i:1">
        <div class="feature-icon-box">
          <i class="bi bi-activity"></i>
        </div>
        <h3 class="feature-title">Mangrove Monitoring</h3>
        <p class="feature-desc">
          Experts record yearly observations of each mangrove area and track which genera are present, such as Rhizophora, Avicennia, and Sonneratia. They also add planting recommendations rated by site suitability to guide restoration.
        </p>
        <ul class="feature-points">
          <li><i class="bi bi-check-circle-fill"></i> Year-by-year coverage trend in hectares</li>
          <li><i class="bi bi-check-circle-fill"></i> Genus composition for each mangrove area</li>
          <li><i class="bi bi-check-circle-fill"></i> Planting recommendations with suitability ratings</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- How It Works: interactive step-through (auto-advances; click or use arrow keys) -->
  <section class="how-section" id="how-it-works">
    <div class="section-header reveal">
      <div class="section-kicker">
        <i class="bi bi-diagram-3"></i> How It Works
      </div>
      <h2 class="section-title">From Shoreline to Map in Four Steps</h2>
      <p class="section-subtitle">
        Select a step to see how a mangrove area goes from a first sketch to a monitored, expert-approved record.
      </p>
    </div>

    <div class="how-layout reveal">
      <ul class="how-steps" id="howSteps" role="tablist" aria-label="How MangroveMap works">
        <li><button type="button" class="how-step active" role="tab" aria-selected="true" aria-controls="howPanel0" data-step="0">
          <span class="how-step-num"><i class="bi bi-pencil-fill"></i></span>
          <span><span class="how-step-title">1. Draw the area</span><br><span class="how-step-sub">Residents outline mangroves on the map</span></span>
          <span class="how-step-bar"></span>
        </button></li>
        <li><button type="button" class="how-step" role="tab" aria-selected="false" aria-controls="howPanel1" data-step="1" tabindex="-1">
          <span class="how-step-num"><i class="bi bi-airplane-fill"></i></span>
          <span><span class="how-step-title">2. Detect with drone AI</span><br><span class="how-step-sub">Orthomosaics segmented by U-Net &amp; YOLO</span></span>
          <span class="how-step-bar"></span>
        </button></li>
        <li><button type="button" class="how-step" role="tab" aria-selected="false" aria-controls="howPanel2" data-step="2" tabindex="-1">
          <span class="how-step-num"><i class="bi bi-patch-check-fill"></i></span>
          <span><span class="how-step-title">3. Expert review</span><br><span class="how-step-sub">Approve, correct, or send back with feedback</span></span>
          <span class="how-step-bar"></span>
        </button></li>
        <li><button type="button" class="how-step" role="tab" aria-selected="false" aria-controls="howPanel3" data-step="3" tabindex="-1">
          <span class="how-step-num"><i class="bi bi-graph-up-arrow"></i></span>
          <span><span class="how-step-title">4. Monitor &amp; restore</span><br><span class="how-step-sub">Yearly observations and planting plans</span></span>
          <span class="how-step-bar"></span>
        </button></li>
      </ul>

      <div class="how-stage" id="howStage">
        {{-- Step 1: a polygon being drawn vertex by vertex. --}}
        <div class="how-panel active" id="howPanel0" role="tabpanel">
          <div class="how-art" aria-hidden="true">
            <svg viewBox="0 0 400 200">
              <path d="M10 170 C80 150 120 175 190 160 C260 145 320 170 390 150" fill="none" stroke="rgba(125,211,252,.45)" stroke-width="3" />
              <path class="fill-in" d="M70 120 L130 50 L220 40 L300 80 L280 140 L160 150 Z" fill="rgba(74,222,128,.28)" />
              <path class="draw-path" d="M70 120 L130 50 L220 40 L300 80 L280 140 L160 150 Z" fill="none" stroke="#4ade80" stroke-width="3" stroke-linejoin="round" />
              <circle class="pop" style="--d:0" cx="70" cy="120" r="6" fill="#fff" />
              <circle class="pop" style="--d:350" cx="130" cy="50" r="6" fill="#fff" />
              <circle class="pop" style="--d:700" cx="220" cy="40" r="6" fill="#fff" />
              <circle class="pop" style="--d:1050" cx="300" cy="80" r="6" fill="#fff" />
              <circle class="pop" style="--d:1400" cx="280" cy="140" r="6" fill="#fff" />
              <circle class="pop" style="--d:1700" cx="160" cy="150" r="6" fill="#fff" />
              <g class="fill-in"><rect x="150" y="82" width="86" height="26" rx="13" fill="rgba(0,0,0,.35)" /><text x="193" y="100" fill="#fff" font-size="13" font-weight="700" text-anchor="middle" font-family="Inter, sans-serif">2.84 ha</text></g>
            </svg>
          </div>
          <h3>Draw the mangrove boundary</h3>
          <p>Residents trace mangrove stands on satellite imagery. The area is measured in hectares and matched to its barangay and municipality automatically.</p>
        </div>

        {{-- Step 2: a drone sweeps over the canopy while a scan line passes. --}}
        <div class="how-panel" id="howPanel1" role="tabpanel">
          <div class="how-art" aria-hidden="true">
            <svg viewBox="0 0 400 200">
              <g>
                <circle class="pop" style="--d:100" cx="70" cy="120" r="26" fill="rgba(34,197,94,.55)" />
                <circle class="pop" style="--d:250" cx="120" cy="140" r="20" fill="rgba(74,222,128,.5)" />
                <circle class="pop" style="--d:400" cx="180" cy="115" r="30" fill="rgba(22,163,74,.6)" />
                <circle class="pop" style="--d:550" cx="245" cy="140" r="22" fill="rgba(74,222,128,.5)" />
                <circle class="pop" style="--d:700" cx="300" cy="118" r="28" fill="rgba(34,197,94,.55)" />
                <circle class="pop" style="--d:850" cx="350" cy="145" r="18" fill="rgba(134,239,172,.5)" />
              </g>
              <g class="fill-in" fill="none" stroke="#facc15" stroke-width="2" stroke-dasharray="5 4">
                <rect x="150" y="85" width="60" height="60" rx="6" />
                <rect x="272" y="90" width="56" height="56" rx="6" />
              </g>
              <g class="fill-in" font-family="Inter, sans-serif" font-size="11" font-weight="700" fill="#fde68a">
                <text x="150" y="80">Rhizophora 92%</text>
                <text x="272" y="85">Avicennia 87%</text>
              </g>
              <rect class="scan-line" x="0" y="0" width="400" height="3" fill="rgba(125,211,252,.8)" />
              <g class="drone">
                <rect x="40" y="10" width="34" height="10" rx="5" fill="#fff" />
                <line x1="30" y1="8" x2="84" y2="8" stroke="#fff" stroke-width="2" />
                <circle cx="57" cy="24" r="4" fill="#7dd3fc" />
              </g>
            </svg>
          </div>
          <h3>Detect mangroves from drone imagery</h3>
          <p>Upload a GeoTIFF orthomosaic and it is tiled for the map, segmented by a U-Net, and checked canopy by canopy by a YOLO model that suggests the genus.</p>
        </div>

        {{-- Step 3: a submission card gets the expert's check mark. --}}
        <div class="how-panel" id="howPanel2" role="tabpanel">
          <div class="how-art" aria-hidden="true">
            <svg viewBox="0 0 400 200">
              <rect class="pop" style="--d:0" x="90" y="20" width="220" height="150" rx="16" fill="rgba(255,255,255,.1)" stroke="rgba(255,255,255,.25)" />
              <rect class="pop" style="--d:200" x="112" y="44" width="120" height="12" rx="6" fill="rgba(255,255,255,.55)" />
              <rect class="pop" style="--d:300" x="112" y="68" width="170" height="8" rx="4" fill="rgba(255,255,255,.25)" />
              <rect class="pop" style="--d:400" x="112" y="84" width="150" height="8" rx="4" fill="rgba(255,255,255,.25)" />
              <rect class="pop" style="--d:500" x="112" y="100" width="160" height="8" rx="4" fill="rgba(255,255,255,.25)" />
              <circle class="pop" style="--d:1000" cx="300" cy="150" r="30" fill="#22c55e" />
              <path class="draw-path" style="animation-delay:1.3s; animation-duration:1.4s" d="M286 150 L296 160 L316 138" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <h3>Experts review every submission</h3>
          <p>Experts approve an area, correct its boundary, or reject it with feedback. Rejected areas can be revised and resubmitted, and only approved records reach the public map.</p>
        </div>

        {{-- Step 4: yearly coverage bars grow. --}}
        <div class="how-panel" id="howPanel3" role="tabpanel">
          <div class="how-art" aria-hidden="true">
            <svg viewBox="0 0 400 200">
              <line x1="30" y1="170" x2="380" y2="170" stroke="rgba(255,255,255,.3)" />
              <rect class="grow-bar" style="--d:0" x="50" y="110" width="40" height="60" rx="6" fill="rgba(74,222,128,.45)" />
              <rect class="grow-bar" style="--d:150" x="115" y="95" width="40" height="75" rx="6" fill="rgba(74,222,128,.55)" />
              <rect class="grow-bar" style="--d:300" x="180" y="80" width="40" height="90" rx="6" fill="rgba(74,222,128,.65)" />
              <rect class="grow-bar" style="--d:450" x="245" y="55" width="40" height="115" rx="6" fill="rgba(74,222,128,.8)" />
              <rect class="grow-bar" style="--d:600" x="310" y="30" width="40" height="140" rx="6" fill="#4ade80" />
              <path class="draw-path" d="M70 100 L135 85 L200 70 L265 45 L330 20" fill="none" stroke="#fde68a" stroke-width="3" stroke-linecap="round" />
              <g font-family="Inter, sans-serif" font-size="11" fill="rgba(255,255,255,.7)" text-anchor="middle">
                <text x="70" y="188">Y1</text><text x="135" y="188">Y2</text><text x="200" y="188">Y3</text><text x="265" y="188">Y4</text><text x="330" y="188">Y5</text>
              </g>
            </svg>
          </div>
          <h3>Monitor change and plan restoration</h3>
          <p>Yearly observations build a coverage timeline for each area, and experts add planting recommendations rated by site suitability to guide replanting.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Interactive GIS Map Section -->
  <section class="map-section" id="map-section">
    <div class="map-showcase-container">
      <div class="section-header reveal" style="margin-bottom: 30px;">
        <div class="section-kicker">
          <i class="bi bi-globe2"></i> Interactive Geospatial Platform
        </div>
        <h2 class="section-title">Live Mangrove Explorer</h2>
        <p class="section-subtitle">
          Explore expert-approved mangrove areas, see which genera grow there, and view planting recommendations.
        </p>
      </div>

      <!-- GIS Map Browser Frame -->
      <div class="map-browser-frame reveal">
        <!-- Frame Toolbar Header -->
        <div class="map-frame-toolbar">
          <div class="toolbar-left">
            <span class="live-status-pill"><span class="indicator-dot"></span> Live</span>
            <span class="map-frame-heading">{{ count($delineations ?? []) }} approved mangrove areas</span>
          </div>

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
                    @if (count($droneImagery ?? []))
                      <label class="leg-row leg-toggle">
                        <input type="checkbox" id="toggleDroneImagery" checked>
                        <i class="bi bi-camera-fill leg-pin" aria-hidden="true"></i>
                        Drone imagery <small>({{ count($droneImagery) }})</small>
                      </label>
                    @endif
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
              <div class="panel-grip" aria-hidden="true"></div>
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
      <div class="impact-card reveal" style="--i:0">
        <div class="impact-icon"><i class="bi bi-cloud-arrow-down-fill"></i></div>
        <div class="impact-figure">up to <span data-count="4">4</span>&times;</div>
        <h3 class="impact-title">Blue Carbon Sequestration</h3>
        <p class="impact-text">
          Mangrove forests capture carbon up to 4 times faster than tropical terrestrial rainforests, burying carbon safely in coastal sediments for millennia.
        </p>
      </div>

      <div class="impact-card reveal" style="--i:1">
        <div class="impact-icon"><i class="bi bi-shield-shaded"></i></div>
        <div class="impact-figure"><span data-count="66">66</span>%</div>
        <h3 class="impact-title">Coastal Storm Protection</h3>
        <p class="impact-text">
          Dense root networks absorb wave energy, attenuating storm surges by up to 66% and preventing critical shoreline erosion in vulnerable coastal regions.
        </p>
      </div>

      <div class="impact-card reveal" style="--i:2">
        <div class="impact-icon"><i class="bi bi-water"></i></div>
        <div class="impact-figure"><span data-count="100">100</span>s</div>
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
    // The button's bars turn into an X (CSS on aria-expanded) while the drawer is open.
    function setMobileMenu(open) {
      if (!mobileMenuBtn || !mobileMenuDrawer) return;
      mobileMenuDrawer.classList.toggle('open', open);
      mobileMenuDrawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      mobileMenuDrawer.toggleAttribute('inert', !open);
      mobileMenuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      mobileMenuBtn.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    }
    function closeMobileMenu() {
      setMobileMenu(false);
    }
    if (mobileMenuBtn && mobileMenuDrawer) {
      // Stagger order for the links' slide-in.
      mobileMenuDrawer.querySelectorAll('a').forEach((link, index) => link.style.setProperty('--i', index));
      setMobileMenu(false);

      mobileMenuBtn.addEventListener('click', () => {
        setMobileMenu(!mobileMenuDrawer.classList.contains('open'));
      });
      // Close with Escape, a tap outside the menu, or when the screen gets wide enough for the full nav.
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobileMenuDrawer.classList.contains('open')) {
          closeMobileMenu();
          mobileMenuBtn.focus();
        }
      });
      document.addEventListener('click', event => {
        if (!mobileMenuDrawer.classList.contains('open')) return;
        if (mobileMenuDrawer.contains(event.target) || mobileMenuBtn.contains(event.target)) return;
        closeMobileMenu();
      });
      window.addEventListener('resize', () => {
        if (getComputedStyle(mobileMenuBtn).display === 'none') closeMobileMenu();
      });
    }

    // GIS Basemap Tile Layers
    // Base maps are upscaled past their native zoom so the map stays filled when zooming into drone imagery.
    const satL = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxNativeZoom: 18,
      maxZoom: 22,
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    const osmL = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxNativeZoom: 18,
      maxZoom: 22,
      attribution: '&copy; OpenStreetMap contributors'
    });
    const topoL = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxNativeZoom: 17,
      maxZoom: 22,
      attribution: 'Map data: &copy; OpenStreetMap contributors, SRTM | Map style: &copy; OpenTopoMap'
    });

    let curBase = satL;
    let mainMap = L.map('mainMap', {
      zoomControl: true,
      maxZoom: 22,
      layers: [satL]
    }).setView([10.358, 124.973], 13);

    @include('partials.map-place-labels')
    attachPlaceLabels(mainMap, [satL]);

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
                ...panelFitPadding(),
                maxZoom: 14,
                animate: true,
                duration: 0.8
              });
            } else if (layer.getLatLng) {
              const point = layer.getLatLng();
              mainMap.flyToBounds(L.latLngBounds(point, point), {
                ...panelFitPadding(),
                maxZoom: 13,
                duration: 0.8
              });
            }
          }
        }
      }, wasOpen ? 50 : 330);
    }

    // Fit padding that keeps the selected feature clear of the details panel:
    // a bottom sheet on phones, a right-side drawer on wider screens.
    function panelFitPadding() {
      const panel = document.getElementById('mapRightPanel');
      if (!panel || !panel.classList.contains('open')) return { padding: [50, 50] };
      if (window.matchMedia('(max-width: 768px)').matches) {
        return { paddingTopLeft: [30, 30], paddingBottomRight: [30, panel.offsetHeight + 30] };
      }
      return { paddingTopLeft: [50, 50], paddingBottomRight: [panel.offsetWidth + 50, 50] };
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

    // Rough size used to pick which shape gets the record's pin.
    function shapeSize(feature, shape) {
      if (Number(feature.area_ha) > 0) return Number(feature.area_ha);
      if (!shape || !shape.getBounds) return 0;
      const b = shape.getBounds();
      return Math.abs((b.getNorth() - b.getSouth()) * (b.getEast() - b.getWest()));
    }

    function drawSavedDelineations() {
      (Array.isArray(savedDelineations) ? savedDelineations : []).forEach((record) => {
        // One pin per delineation (AI delineations can have many small areas), on its largest shape.
        let pinTarget = null;

        (Array.isArray(record.features) ? record.features : []).forEach((feature) => {
          let shape = null;
          if (feature.type === 'line') {
            shape = L.polyline(feature.coords, { color: '#1e9e62', weight: 3 }).addTo(delineationLayer);
          } else if (feature.type === 'area' || !feature.type) {
            shape = L.polygon(feature.coords, { color: '#1e9e62', fillColor: '#1e9e62', fillOpacity: 0.28, weight: 2 }).addTo(delineationLayer);
          }

          if (shape) {
            shape.bindPopup(delineationPopup(record, feature));
            shape.on('click', (e) => {
              if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
              showDelineationDetails(record, feature, shape);
            });
          }

          const position = pinPosition(feature, shape);
          const size = shapeSize(feature, shape);
          if (position && (!pinTarget || size > pinTarget.size)) {
            pinTarget = { feature, shape, position, size };
          }
        });

        if (!pinTarget) return;
        const { feature, shape, position } = pinTarget;
        // The pin stands for the whole record, so show the record's total area.
        const pinFeature = Number(record.area_ha) > 0 ? { ...feature, area_ha: record.area_ha } : feature;
        const pin = L.marker(position, { icon: pinIcon, title: record.name || 'Approved delineation', riseOnHover: true }).addTo(pinLayer);
        pin.bindTooltip(record.name || 'Approved delineation', { direction: 'top' });
        pin.bindPopup(delineationPopup(record, pinFeature));
        pin.on('click', (e) => {
          if (e && e.originalEvent) L.DomEvent.stopPropagation(e);
          showDelineationDetails(record, pinFeature, shape || pin);
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

    // Approved drone imagery (GeoTIFF tiles) under the delineations; upscaled past its native zoom.
    const droneImagery = @json($droneImagery ?? []);
    const droneImageryLayer = L.layerGroup().addTo(mainMap);
    const droneImageryBounds = L.latLngBounds([]);

    function drawDroneImagery() {
      droneImagery.forEach(layer => {
        const b = layer.bounds || {};
        if (![b.south, b.west, b.north, b.east].every(Number.isFinite)) return;
        const bounds = L.latLngBounds([b.south, b.west], [b.north, b.east]);
        droneImageryBounds.extend(bounds);
        L.tileLayer(`${layer.tile_url}?v=${encodeURIComponent(layer.version || '')}`, {
          bounds,
          opacity: Number(layer.opacity) || 0.78,
          minNativeZoom: layer.zoom_min,
          maxNativeZoom: layer.zoom_max,
          maxZoom: 22,
          updateWhenZooming: false,
          updateWhenIdle: true,
          keepBuffer: 2,
          zIndex: 5,
        }).addTo(droneImageryLayer);
      });
    }

    drawDroneImagery();
    drawSavedDelineations();
    drawPlantingRecommendations();

    // Open on the mapped areas rather than a fixed view.
    (function fitToData() {
      const bounds = L.latLngBounds([]);
      [delineationLayer, pinLayer, recommendationLayer].forEach(group => {
        if (group.getLayers().length) bounds.extend(group.getBounds());
      });
      if (droneImageryBounds.isValid()) bounds.extend(droneImageryBounds);
      if (bounds.isValid()) mainMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    })();

    // Legend checkboxes show or hide each layer.
    [
      ['toggleDelineations', delineationLayer],
      ['togglePins', pinLayer],
      ['toggleRecommendations', recommendationLayer],
      ['toggleDroneImagery', droneImageryLayer],
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
          mainMap.flyToBounds(L.latLngBounds([z.lat, z.lng], [z.lat, z.lng]), { ...panelFitPadding(), maxZoom: 10, duration: 0.8 });
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

  {{-- Landing page motion: scroll progress, scrollspy, reveals, counters, hero effects, how-it-works. --}}
  <div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>
  <script>
    (() => {
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
      document.documentElement.classList.add('js-motion');

      // Scroll progress bar, condensed nav and active nav link.
      const progress = document.getElementById('scrollProgress');
      const nav = document.querySelector('.site-nav');
      const navLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];
      const spySections = navLinks
        .map(link => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);
      let ticking = false;

      function onScroll() {
        ticking = false;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.setProperty('--progress', max > 0 ? Math.min(1, window.scrollY / max) : 0);
        nav.classList.toggle('scrolled', window.scrollY > 24);

        const probe = window.scrollY + window.innerHeight * 0.35;
        let current = spySections[0];
        spySections.forEach(section => {
          if (section.offsetTop <= probe) current = section;
        });
        navLinks.forEach(link => link.classList.toggle('active', current && link.getAttribute('href') === '#' + current.id));
      }

      window.addEventListener('scroll', () => {
        if (!ticking) {
          ticking = true;
          requestAnimationFrame(onScroll);
        }
      }, { passive: true });
      onScroll();

      // Count-up numbers (hero stats and impact figures) when they come into view.
      function countUp(el) {
        const target = parseFloat(el.dataset.count) || 0;
        const decimals = parseInt(el.dataset.decimals || '0', 10);
        const format = value => value.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        if (reduceMotion || target === 0) {
          el.textContent = format(target);
          return;
        }
        const duration = 1600;
        const start = performance.now();
        const step = now => {
          const t = Math.min(1, (now - start) / duration);
          const eased = 1 - Math.pow(1 - t, 4);
          el.textContent = format(target * eased);
          if (t < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
      }

      // Reveal-on-scroll; counters start when their block appears.
      const revealObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('visible');
          revealObserver.unobserve(entry.target);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
      document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

      const countObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          countUp(entry.target);
          countObserver.unobserve(entry.target);
        });
      }, { threshold: 0.6 });
      document.querySelectorAll('[data-count]').forEach(el => {
        if (!reduceMotion) el.textContent = '0';
        countObserver.observe(el);
      });

      // Rotating word in the hero headline.
      const rotator = document.getElementById('heroRotator');
      if (rotator && !reduceMotion) {
        const words = [...rotator.children];
        let index = 0;
        // The box takes the current word's width so there is no gap around shorter words.
        const fit = () => { rotator.style.width = words[index].getBoundingClientRect().width + 'px'; };
        fit();
        document.fonts && document.fonts.ready.then(fit);
        window.addEventListener('resize', () => { rotator.style.transition = 'none'; fit(); requestAnimationFrame(() => rotator.style.transition = ''); });
        setInterval(() => {
          if (document.hidden) return;
          const prev = words[index];
          index = (index + 1) % words.length;
          prev.classList.remove('on');
          prev.classList.add('out');
          words[index].classList.remove('out');
          words[index].classList.add('on');
          fit();
          setTimeout(() => prev.classList.remove('out'), 600);
        }, 2800);
      }

      // Hero parallax and cursor glow.
      const hero = document.querySelector('.hero-section');
      const heroBg = hero && hero.querySelector('.hero-bg');
      const heroGlow = hero && hero.querySelector('.hero-glow');
      if (hero && finePointer && !reduceMotion) {
        hero.addEventListener('pointermove', event => {
          const rect = hero.getBoundingClientRect();
          const x = (event.clientX - rect.left) / rect.width - 0.5;
          const y = (event.clientY - rect.top) / rect.height - 0.5;
          heroBg.style.setProperty('--px', x.toFixed(3));
          heroBg.style.setProperty('--py', y.toFixed(3));
          heroGlow.style.setProperty('--gx', (event.clientX - rect.left) + 'px');
          heroGlow.style.setProperty('--gy', (event.clientY - rect.top) + 'px');
        });
        hero.addEventListener('pointerleave', () => {
          heroBg.style.setProperty('--px', 0);
          heroBg.style.setProperty('--py', 0);
        });
      }

      // Hero particles: drifting glowing motes, like fireflies over the mangroves.
      const canvas = document.getElementById('heroParticles');
      if (canvas && hero && !reduceMotion) {
        const ctx = canvas.getContext('2d');
        let width = 0, height = 0, motes = [], running = true;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);

        function resize() {
          width = hero.clientWidth;
          height = hero.clientHeight;
          canvas.width = width * dpr;
          canvas.height = height * dpr;
          ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
          const count = Math.round(Math.min(70, width / 18));
          motes = Array.from({ length: count }, () => ({
            x: Math.random() * width,
            y: Math.random() * height,
            r: Math.random() * 2.2 + 0.6,
            vx: (Math.random() - 0.5) * 0.25,
            vy: -(Math.random() * 0.35 + 0.08),
            phase: Math.random() * Math.PI * 2,
            hue: Math.random() < 0.75 ? '134, 239, 172' : '253, 230, 138',
          }));
        }

        function frame(time) {
          if (!running) return;
          ctx.clearRect(0, 0, width, height);
          motes.forEach(m => {
            m.x += m.vx + Math.sin(time / 1800 + m.phase) * 0.15;
            m.y += m.vy;
            if (m.y < -10) { m.y = height + 10; m.x = Math.random() * width; }
            if (m.x < -10) m.x = width + 10;
            if (m.x > width + 10) m.x = -10;
            const alpha = 0.35 + Math.sin(time / 600 + m.phase) * 0.3;
            ctx.beginPath();
            ctx.fillStyle = `rgba(${m.hue}, ${Math.max(0.05, alpha)})`;
            ctx.shadowColor = `rgba(${m.hue}, 0.9)`;
            ctx.shadowBlur = 8;
            ctx.arc(m.x, m.y, m.r, 0, Math.PI * 2);
            ctx.fill();
          });
          requestAnimationFrame(frame);
        }

        resize();
        window.addEventListener('resize', resize);
        // Only animate while the hero is on screen.
        new IntersectionObserver(([entry]) => {
          const wasRunning = running;
          running = entry.isIntersecting;
          if (running && !wasRunning) requestAnimationFrame(frame);
        }).observe(hero);
        requestAnimationFrame(frame);
      }

      // Feature cards: 3D tilt and spotlight.
      if (finePointer && !reduceMotion) {
        document.querySelectorAll('.feature-card').forEach(card => {
          card.addEventListener('pointermove', event => {
            const rect = card.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;
            card.classList.add('tilting');
            card.style.setProperty('--ry', ((x - 0.5) * 8).toFixed(2) + 'deg');
            card.style.setProperty('--rx', ((0.5 - y) * 8).toFixed(2) + 'deg');
            card.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
            card.style.setProperty('--my', (y * 100).toFixed(1) + '%');
          });
          card.addEventListener('pointerleave', () => {
            card.classList.remove('tilting');
            card.style.setProperty('--rx', '0deg');
            card.style.setProperty('--ry', '0deg');
          });
        });
      }

      // How it works: tabs that auto-advance, pause on hover/focus, and follow arrow keys.
      const stepList = document.getElementById('howSteps');
      if (stepList) {
        const stepMs = 6000;
        const steps = [...stepList.querySelectorAll('.how-step')];
        const panels = steps.map(step => document.getElementById(step.getAttribute('aria-controls')));
        let active = 0, timer = null, paused = false, inView = false;
        stepList.style.setProperty('--how-ms', stepMs + 'ms');

        function show(index, focus) {
          active = (index + steps.length) % steps.length;
          steps.forEach((step, i) => {
            const on = i === active;
            step.classList.toggle('active', on);
            step.setAttribute('aria-selected', on ? 'true' : 'false');
            step.tabIndex = on ? 0 : -1;
            panels[i].classList.toggle('active', on);
          });
          if (focus) steps[active].focus();
          restartBar();
          schedule();
        }

        // Restarting the timer bar keeps it in step with a restarted timeout.
        function restartBar() {
          const bar = steps[active].querySelector('.how-step-bar');
          bar.style.animation = 'none';
          void bar.offsetWidth;
          bar.style.animation = '';
        }

        function schedule() {
          clearTimeout(timer);
          if (reduceMotion || paused || !inView) return;
          timer = setTimeout(() => show(active + 1), stepMs);
        }

        function setPaused(value) {
          paused = value;
          stepList.classList.toggle('paused', paused);
          if (!paused) restartBar();
          schedule();
        }

        steps.forEach((step, i) => {
          step.addEventListener('click', () => show(i));
          step.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') { event.preventDefault(); show(active + 1, true); }
            if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') { event.preventDefault(); show(active - 1, true); }
          });
        });

        const layout = stepList.closest('.how-layout');
        layout.addEventListener('pointerenter', () => setPaused(true));
        layout.addEventListener('pointerleave', () => setPaused(false));
        layout.addEventListener('focusin', () => setPaused(true));
        layout.addEventListener('focusout', event => {
          if (!layout.contains(event.relatedTarget)) setPaused(false);
        });

        new IntersectionObserver(([entry]) => {
          const wasInView = inView;
          inView = entry.isIntersecting;
          if (inView && !wasInView) show(active);
          else if (!inView) clearTimeout(timer);
        }, { threshold: 0.35 }).observe(layout);
      }
    })();
  </script>
</body>

</html>