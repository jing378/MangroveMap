<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>MangroveMap — Image Delineation</title>
  <meta name="description" content="Upload single or batch mangrove images for U-Net canopy segmentation and delineation analysis." />
  <link rel="icon" type="image/png" href="/icon-192.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green:   #1e9e62;
      --green-d: #16a34a;
      --green-l: #edf7f2;
      --border:  #e0e8e0;
      --text:    #1a2e1a;
      --muted:   #9ab0a0;
      --danger:  #d04030;
      --warn:    #c07818;
      --bg:      #f4f8f4;
      --card:    #fff;
      --header-h: 62px;
    }

    body { font-family: 'Manrope', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* ── HEADER ── */
    .header {
      position: fixed; top: 0; left: 0; right: 0; z-index: 200;
      height: var(--header-h);
      background: #fff;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; gap: 16px;
    }
    .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .logo-icon { width: 34px; height: 34px; background: var(--green); border-radius: 9px;
      display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
    .logo span { font-size: 17px; font-weight: 800; color: var(--text); }
    .logo small { font-size: 11px; color: var(--muted); font-weight: 500; }
    .header-right { display: flex; align-items: center; gap: 12px; }
    .btn-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; font-size: 13px; font-weight: 600;
      color: var(--text); border: 1px solid var(--border);
      border-radius: 9px; background: #fff; cursor: pointer;
      text-decoration: none; transition: all .15s;
    }
    .btn-back:hover { background: var(--green-l); border-color: var(--green); color: var(--green); }

    /* ── LAYOUT ── */
    .page { margin-top: var(--header-h); padding: 28px 24px; max-width: 1280px; margin-left: auto; margin-right: auto; }
    .page-title { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
    .page-sub { font-size: 14px; color: var(--muted); margin-bottom: 24px; }

    .layout { display: grid; grid-template-columns: 340px 1fr; gap: 20px; align-items: start; }
    @media (max-width: 860px) { .layout { grid-template-columns: 1fr; } }

    /* ── CARDS ── */
    .card {
      background: var(--card); border: 1px solid var(--border);
      border-radius: 16px; padding: 20px; box-shadow: 0 1px 6px rgba(0,0,0,.04);
    }
    .card + .card { margin-top: 16px; }
    .card-title { font-size: 13px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .6px; color: var(--muted); margin-bottom: 14px; }

    /* ── UPLOAD ZONE ── */
    .drop-zone {
      border: 2px dashed var(--border); border-radius: 14px;
      padding: 32px 20px; text-align: center; cursor: pointer;
      background: #fff; transition: all .18s; position: relative;
    }
    .drop-zone.drag-over { border-color: var(--green); background: var(--green-l); }
    .drop-zone:hover { border-color: #b0d0b8; background: #fafdf9; }
    .drop-zone .dz-ico { font-size: 38px; color: var(--muted); margin-bottom: 10px; line-height: 1; }
    .drop-zone h3 { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 5px; }
    .drop-zone p { font-size: 13px; color: var(--muted); }
    #batchFileInput { display: none; }
    .choose-btn {
      display: inline-flex; align-items: center; gap: 6px; margin-top: 14px;
      padding: 8px 20px; background: var(--green); color: #fff;
      border-radius: 9px; font-size: 13px; font-weight: 700;
      cursor: pointer; border: none; transition: background .15s;
    }
    .choose-btn:hover { background: var(--green-d); }

    /* ── QUEUE ── */
    .queue-list { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
    .queue-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; background: #f7faf7;
      border: 1px solid var(--border); border-radius: 10px;
      cursor: pointer; transition: border-color .15s;
    }
    .queue-item:hover { border-color: #a0c8b0; }
    .queue-item.active { border-color: var(--green); background: var(--green-l); }
    .qi-thumb {
      width: 40px; height: 40px; border-radius: 7px;
      object-fit: cover; flex-shrink: 0; background: #e8eee8;
    }
    .qi-info { flex: 1; min-width: 0; }
    .qi-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .qi-sub  { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .qi-progress { height: 4px; border-radius: 2px; background: var(--border); margin-top: 5px; overflow: hidden; }
    .qi-progress-fill { height: 100%; border-radius: 2px; background: var(--green); width: 0%; transition: width .4s; }
    .qi-badge {
      font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 100px;
      flex-shrink: 0; white-space: nowrap;
    }
    .qi-badge.queued    { background: #f0f4f0; color: var(--muted); }
    .qi-badge.running   { background: #fff8e8; color: var(--warn); }
    .qi-badge.done      { background: var(--green-l); color: var(--green); }
    .qi-badge.failed    { background: #fdf0ee; color: var(--danger); }

    /* ── ACTION BUTTONS ── */
    .action-row { display: flex; gap: 8px; margin-top: 14px; }
    .btn-primary {
      flex: 1; padding: 10px; font-size: 14px; font-weight: 700;
      background: var(--green); color: #fff; border: none; border-radius: 10px;
      cursor: pointer; transition: background .15s; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-primary:hover { background: var(--green-d); }
    .btn-primary:disabled { opacity: .45; cursor: not-allowed; }
    .btn-secondary {
      padding: 10px 14px; font-size: 13px; font-weight: 600;
      background: #fff; color: var(--text); border: 1px solid var(--border);
      border-radius: 10px; cursor: pointer; transition: all .15s;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-secondary:hover { background: #f7faf7; border-color: #c0d0c0; }

    /* ── PREVIEW (right column) ── */
    .preview-card { min-height: 420px; display: flex; flex-direction: column; }
    .preview-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
    .toggle-row { display: flex; gap: 6px; }
    .toggle-btn {
      padding: 5px 14px; font-size: 13px; font-weight: 600;
      border: 1px solid var(--border); border-radius: 7px;
      background: #fff; color: var(--muted); cursor: pointer; transition: all .15s;
    }
    .toggle-btn.active { background: var(--green); color: #fff; border-color: var(--green); }
    .toggle-btn:disabled { opacity: .4; cursor: not-allowed; }

    .img-wrapper {
      flex: 1; background: #f2f6f2; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden; min-height: 320px; position: relative;
    }
    .img-wrapper img { max-width: 100%; max-height: 480px; object-fit: contain; display: none; }
    .no-img-msg { text-align: center; color: var(--muted); padding: 24px; }
    .no-img-msg i { font-size: 52px; display: block; margin-bottom: 12px; }
    .no-img-msg p { font-size: 14px; font-weight: 500; }
    .no-img-msg small { font-size: 12px; }

    /* ── RESULT STATS ── */
    .result-stats {
      display: none; margin-top: 16px; padding: 14px 16px;
      background: var(--green-l); border: 1px solid #c0e0d0; border-radius: 12px;
    }
    .result-stats .rs-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; border-bottom: 1px solid #d8eed8; }
    .result-stats .rs-row:last-child { border-bottom: none; }
    .result-stats .rs-key { color: #5a7a5a; }
    .result-stats .rs-val { font-weight: 700; color: var(--text); }
    .result-stats .rs-rec { font-size: 12px; color: #5a7a5a; margin-top: 10px; line-height: 1.6; font-style: italic; }
    .conf-row-stat { display: flex; align-items: center; gap: 8px; margin: 8px 0; }
    .conf-track { flex: 1; height: 5px; background: #d0e8d8; border-radius: 3px; overflow: hidden; }
    .conf-fill  { height: 100%; border-radius: 3px; }
    .conf-pct   { font-size: 12px; font-weight: 700; width: 32px; text-align: right; }

    /* ── HISTORY TABLE ── */
    .hist-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
    .tbl th { text-align: left; padding: 8px 10px; color: var(--muted); font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
    .tbl td { padding: 10px 10px; border-bottom: 1px solid #f0f4f0; vertical-align: middle; }
    .tbl tr:last-child td { border-bottom: none; }
    .tbl tr:hover td { background: #f9fbf9; }
    .st-badge {
      display: inline-block; padding: 2px 8px; border-radius: 100px; font-size: 11px; font-weight: 700;
    }
    .st-completed { background: var(--green-l); color: var(--green); }
    .st-failed    { background: #fdf0ee; color: var(--danger); }
    .st-processing { background: #fff8e8; color: var(--warn); }
    .view-btn {
      padding: 4px 10px; font-size: 12px; font-weight: 600; border: 1px solid var(--border);
      border-radius: 7px; background: #fff; cursor: pointer; color: var(--text); transition: all .15s;
      text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
    }
    .view-btn:hover { border-color: var(--green); color: var(--green); background: var(--green-l); }
    .hist-actions { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
    .tbl th:last-child, .tbl td:last-child { white-space: nowrap; min-width: 168px; }
    .del-btn { color: var(--danger); }
    .del-btn:hover { border-color: var(--danger); color: var(--danger); background: #fdf0ee; }
    .btn-danger {
      padding: 10px 14px; font-size: 13px; font-weight: 600;
      background: #fff; color: var(--danger); border: 1px solid #f0c8c0;
      border-radius: 10px; cursor: pointer; transition: all .15s;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-danger:hover { background: #fdf0ee; border-color: var(--danger); }
    .hist-header-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .empty-hist { text-align: center; padding: 32px; color: var(--muted); font-size: 14px; }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="{{ Auth::user()->isExpert() ? route('expert.dashboard') : route('dashboard') }}" class="logo">
    <div class="logo-icon"><i class="bi bi-tree-fill"></i></div>
    <div>
      <span>MangroveMap</span><br>
      <small>Image Delineation</small>
    </div>
  </a>
  <div class="header-right">
    <a href="{{ Auth::user()->isExpert() ? route('expert.dashboard') : route('dashboard') }}" class="btn-back">
      <i class="bi bi-arrow-left-short"></i> Back to Dashboard
    </a>
  </div>
</header>

<!-- PAGE -->
<main class="page">
  <h1 class="page-title">Image Delineation</h1>
  <p class="page-sub">Upload one or more mangrove images for U-Net canopy segmentation. Images are processed sequentially.</p>

  <div class="layout">

    <!-- LEFT COLUMN: Upload + Queue -->
    <div>
      <!-- Drop Zone -->
      <div class="card">
        <div class="card-title">Upload Images</div>
        <div class="drop-zone" id="dropZone" onclick="document.getElementById('batchFileInput').click()">
          <div class="dz-ico"><i class="bi bi-cloud-arrow-up"></i></div>
          <h3>Drop images here</h3>
          <p>JPG, PNG — field, drone, or satellite photos</p>
          <input type="file" id="batchFileInput" accept="image/*" multiple />
          <label class="choose-btn" onclick="event.stopPropagation(); document.getElementById('batchFileInput').click()">
            <i class="bi bi-folder2-open"></i> Choose Files
          </label>
        </div>

        <div class="action-row">
          <button id="uploadAllBtn" class="btn-primary" onclick="startBatchUpload()" disabled>
            <i class="bi bi-play-fill"></i> Run Segmentation
          </button>
          <button class="btn-secondary" onclick="clearQueue()" title="Clear queue">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>

      <!-- Queue -->
      <div class="card" id="queueCard" style="display:none;">
        <div class="card-title">Queue (<span id="queueCount">0</span> images)</div>
        <div class="queue-list" id="queueList"></div>
      </div>
    </div>

    <!-- RIGHT COLUMN: Preview + Stats -->
    <div>
      <div class="card preview-card">
        <div class="preview-header">
          <div class="card-title" style="margin-bottom:0">Preview</div>
          <div class="toggle-row">
            <button class="toggle-btn active" id="btnOriginal" onclick="setPreview('original')" disabled>Original</button>
            <button class="toggle-btn" id="btnOverlay" onclick="setPreview('overlay')" disabled><i class="bi bi-bounding-box"></i> Delineation</button>
          </div>
        </div>
        <div class="img-wrapper" id="imgWrapper">
          <div class="no-img-msg" id="noImgMsg">
            <i class="bi bi-tree"></i>
            <p>No image selected</p>
            <small>Upload images and click one in the queue</small>
          </div>
          <img id="previewImg" alt="Mangrove preview" />
        </div>
        <div class="result-stats" id="resultStats">
          <div class="rs-row"><span class="rs-key">Label</span><span class="rs-val" id="rsLabel">—</span></div>
          <div class="rs-row"><span class="rs-key">Mangrove cover</span><span class="rs-val" id="rsCoverage">—</span></div>
          <div class="conf-row-stat">
            <span style="font-size:12px;color:#5a7a5a;width:120px;flex-shrink:0">Mangrove</span>
            <div class="conf-track"><div class="conf-fill" id="rsManBar" style="background:#1e9e62;width:0%"></div></div>
            <span class="conf-pct" id="rsManPct">0%</span>
          </div>
          <div class="conf-row-stat">
            <span style="font-size:12px;color:#5a7a5a;width:120px;flex-shrink:0">Background</span>
            <div class="conf-track"><div class="conf-fill" id="rsBgBar" style="background:#c0c8b8;width:0%"></div></div>
            <span class="conf-pct" id="rsBgPct">0%</span>
          </div>
          <div class="rs-row"><span class="rs-key">Mean confidence</span><span class="rs-val" id="rsConf">—</span></div>
          <p class="rs-rec" id="rsRec"></p>
        </div>
      </div>
    </div>
  </div>

  <!-- HISTORY TABLE -->
  <div class="card" style="margin-top: 24px;">
    <div class="hist-header">
      <div class="card-title" style="margin-bottom:0">Past Analyses</div>
      <div class="hist-header-actions">
        @if($analyses->isNotEmpty())
        <button class="btn-danger" id="deleteAllBtn" onclick="deleteAllHistory()" title="Delete all past analyses">
          <i class="bi bi-trash"></i> Delete All
        </button>
        @endif
        <button class="btn-secondary" onclick="exportCSV()" title="Export as CSV">
          <i class="bi bi-download"></i> Export CSV
        </button>
      </div>
    </div>
    @if($analyses->isEmpty())
      <div class="empty-hist">
        <i class="bi bi-inbox" style="font-size:36px;display:block;margin-bottom:10px"></i>
        No analyses yet. Upload images above to get started.
      </div>
    @else
    <table class="tbl" id="histTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Filename / Date</th>
          <th>Coverage</th>
          <th>Confidence</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @foreach($analyses as $i => $a)
        <tr data-id="{{ $a->id }}"
            data-original="{{ $a->image_url }}"
            data-overlay="{{ $a->results['overlay_url'] ?? '' }}"
            data-label="{{ $a->species_detected ?? '—' }}"
            data-coverage="{{ number_format($a->results['mangrove_coverage_percent'] ?? 0, 1) }}"
            data-conf="{{ round(($a->classification_confidence ?? 0) * 100) }}"
            data-rec="{{ $a->recommendations ?? '' }}">
          <td style="color:var(--muted);font-size:12px">{{ $i + 1 }}</td>
          <td>
            <div style="font-weight:600;font-size:13px">{{ basename($a->image_url ?? 'Unknown') }}</div>
            <div style="font-size:11px;color:var(--muted)">{{ $a->created_at->format('M d, Y · H:i') }}</div>
          </td>
          <td style="font-weight:700">{{ number_format($a->results['mangrove_coverage_percent'] ?? 0, 1) }}%</td>
          <td style="font-weight:700">{{ round(($a->classification_confidence ?? 0) * 100) }}%</td>
          <td>
            <span class="st-badge st-{{ $a->status }}">{{ ucfirst($a->status) }}</span>
          </td>
          <td>
            <div class="hist-actions">
              @if($a->status === 'completed')
                <button class="view-btn" onclick="viewHistoryRow(this.closest('tr'))">
                  <i class="bi bi-eye"></i>
                </button>
              @endif
              <button class="view-btn del-btn" onclick="deleteHistoryRow(this.closest('tr'))" title="Delete this analysis">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
</main>

<script>
  const uploadUrl     = @json(route('delineation.store'));
  const destroyAllUrl = @json(route('delineation.destroyAll'));
  const destroyUrlTpl = @json(url('/delineation/analyses'));
  const csrfToken     = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  // ── State ──
  let queue        = [];  // { file, thumb, originalUrl, overlayUrl, result, status }
  let activeIndex  = -1;
  let isRunning    = false;
  let previewMode  = 'original';

  // ── Drop zone ──
  const dropZone = document.getElementById('dropZone');
  dropZone.addEventListener('dragover',  e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    addFiles([...e.dataTransfer.files]);
  });
  document.getElementById('batchFileInput').addEventListener('change', e => {
    addFiles([...e.target.files]);
    e.target.value = '';
  });

  function addFiles(files) {
    files.filter(f => f.type.startsWith('image/')).forEach(file => {
      queue.push({ file, thumb: URL.createObjectURL(file), originalUrl: URL.createObjectURL(file), overlayUrl: null, result: null, status: 'queued' });
    });
    renderQueue();
  }

  function renderQueue() {
    const list = document.getElementById('queueList');
    list.innerHTML = '';
    document.getElementById('queueCount').textContent = queue.length;
    document.getElementById('queueCard').style.display = queue.length ? 'block' : 'none';
    document.getElementById('uploadAllBtn').disabled = queue.length === 0 || isRunning;

    queue.forEach((item, idx) => {
      const div = document.createElement('div');
      div.className = 'queue-item' + (idx === activeIndex ? ' active' : '');
      div.dataset.idx = idx;
      div.onclick = () => selectQueueItem(idx);
      div.innerHTML = `
        <img class="qi-thumb" src="${item.thumb}" alt="" />
        <div class="qi-info">
          <div class="qi-name">${item.file.name}</div>
          <div class="qi-sub">${(item.file.size / 1024).toFixed(0)} KB</div>
          <div class="qi-progress"><div class="qi-progress-fill" id="prog-${idx}" style="width:${item.status==='done'?'100':item.status==='running'?'50':'0'}%"></div></div>
        </div>
        <span class="qi-badge ${item.status}">${item.status.charAt(0).toUpperCase()+item.status.slice(1)}</span>`;
      list.appendChild(div);
    });
  }

  function selectQueueItem(idx) {
    activeIndex = idx;
    renderQueue();
    const item = queue[idx];
    showPreview(item.originalUrl, item.overlayUrl, item.result);
  }

  function normalizeUrl(url) {
    if (!url) return '';
    if (url.startsWith('blob:') || url.startsWith('data:')) return url;
    const idx = url.indexOf('/storage/');
    if (idx !== -1) return url.substring(idx);
    return url;
  }

  function showPreview(originalUrl, overlayUrl, result) {
    const img     = document.getElementById('previewImg');
    const noMsg   = document.getElementById('noImgMsg');
    const stats   = document.getElementById('resultStats');
    const btnO    = document.getElementById('btnOriginal');
    const btnOv   = document.getElementById('btnOverlay');

    const cleanOriginal = normalizeUrl(originalUrl);
    const cleanOverlay  = normalizeUrl(overlayUrl);

    const targetUrl = (previewMode === 'overlay' && cleanOverlay) ? cleanOverlay : cleanOriginal;
    if (targetUrl) {
      img.style.display = 'block';
      noMsg.style.display = 'none';
      img.src = targetUrl;
    }
    btnO.disabled  = !cleanOriginal;
    btnOv.disabled = !cleanOverlay;

    if (result) {
      const cov  = Number(result.mangrove_coverage_percent || 0);
      const conf = Math.round(Number(result.mean_confidence || 0) * 100);
      document.getElementById('rsLabel').textContent    = result.label || '—';
      document.getElementById('rsCoverage').textContent = cov.toFixed(1) + '%';
      document.getElementById('rsConf').textContent     = conf + '%';
      document.getElementById('rsManBar').style.width   = cov + '%';
      document.getElementById('rsManPct').textContent   = cov.toFixed(1) + '%';
      document.getElementById('rsBgBar').style.width    = Math.max(0, 100 - cov) + '%';
      document.getElementById('rsBgPct').textContent    = Math.max(0, 100 - cov).toFixed(1) + '%';
      document.getElementById('rsRec').textContent      = result.recommendations || '';
      stats.style.display = 'block';
    } else {
      stats.style.display = 'none';
    }
  }

  function setPreview(mode) {
    previewMode = mode;
    document.getElementById('btnOriginal').classList.toggle('active', mode === 'original');
    document.getElementById('btnOverlay').classList.toggle('active', mode === 'overlay');
    if (activeIndex >= 0) {
      const item = queue[activeIndex];
      showPreview(item.originalUrl, item.overlayUrl, item.result);
    }
  }

  // ── Batch upload ──
  async function startBatchUpload() {
    if (isRunning) return;
    isRunning = true;
    document.getElementById('uploadAllBtn').disabled = true;

    for (let i = 0; i < queue.length; i++) {
      const item = queue[i];
      if (item.status === 'done') continue;

      item.status = 'running';
      activeIndex = i;
      renderQueue();
      showPreview(item.originalUrl, item.overlayUrl, item.result);

      const prog = document.getElementById('prog-' + i);
      if (prog) prog.style.width = '40%';

      const fd = new FormData();
      fd.append('image', item.file);

      try {
        const res  = await fetch(uploadUrl, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        });
        const data = await res.json();

        if (!res.ok || !data.ok) throw new Error(data.message || 'Segmentation failed.');

        item.status     = 'done';
        item.overlayUrl = data.overlay_url || null;
        item.result     = data;
        if (prog) prog.style.width = '100%';
        renderQueue();
        if (item.overlayUrl) {
          setPreview('overlay');
        } else {
          showPreview(item.originalUrl, item.overlayUrl, item.result);
        }

        // Append to history table immediately
        appendHistoryRow(data, item.file.name);

      } catch (err) {
        item.status = 'failed';
        if (prog) prog.style.width = '0%';
        renderQueue();
      }
    }

    isRunning = false;
    document.getElementById('uploadAllBtn').disabled = false;
  }

  function clearQueue() {
    queue = [];
    activeIndex = -1;
    isRunning = false;
    renderQueue();
    document.getElementById('previewImg').style.display = 'none';
    document.getElementById('noImgMsg').style.display  = 'block';
    document.getElementById('resultStats').style.display = 'none';
    document.getElementById('btnOriginal').disabled = true;
    document.getElementById('btnOverlay').disabled  = true;
  }

  // ── History ──
  function viewHistoryRow(tr) {
    const original  = tr.dataset.original;
    const overlay   = tr.dataset.overlay;
    const result    = {
      label:                     tr.dataset.label,
      mangrove_coverage_percent: parseFloat(tr.dataset.coverage),
      mean_confidence:           parseFloat(tr.dataset.conf) / 100,
      recommendations:           tr.dataset.rec,
    };
    activeIndex = -1;
    previewMode = overlay ? 'overlay' : 'original';
    document.getElementById('btnOriginal').classList.toggle('active', previewMode === 'original');
    document.getElementById('btnOverlay').classList.toggle('active', previewMode === 'overlay');
    showPreview(original, overlay, result);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function appendHistoryRow(data, filename) {
    const tbody = document.querySelector('#histTable tbody');
    if (!tbody) return;
    const cov  = Number(data.mangrove_coverage_percent || 0).toFixed(1);
    const conf = Math.round(Number(data.mean_confidence || 0) * 100);
    const now  = new Date().toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const tr = document.createElement('tr');
    tr.dataset.id       = data.analysis_id || '';
    tr.dataset.original = data.image_url || '';
    tr.dataset.overlay  = data.overlay_url || '';
    tr.dataset.label    = data.label || '—';
    tr.dataset.coverage = cov;
    tr.dataset.conf     = conf;
    tr.dataset.rec      = data.recommendations || '';
    tr.innerHTML = `
      <td style="color:var(--muted);font-size:12px">—</td>
      <td><div style="font-weight:600;font-size:13px">${filename}</div><div style="font-size:11px;color:var(--muted)">${now}</div></td>
      <td style="font-weight:700">${cov}%</td>
      <td style="font-weight:700">${conf}%</td>
      <td><span class="st-badge st-completed">Completed</span></td>
      <td>
        <div class="hist-actions">
          <button class="view-btn" onclick="viewHistoryRow(this.closest('tr'))"><i class="bi bi-eye"></i> View</button>
          <button class="view-btn del-btn" onclick="deleteHistoryRow(this.closest('tr'))" title="Delete this analysis"><i class="bi bi-trash"></i> Delete</button>
        </div>
      </td>`;
    tbody.insertAdjacentElement('afterbegin', tr);
    // Remove empty-hist message if present
    const empty = document.querySelector('.empty-hist');
    if (empty) empty.remove();
  }

  function exportCSV() {
    const rows = [['#','Filename','Date','Coverage %','Confidence %','Status','Label']];
    document.querySelectorAll('#histTable tbody tr').forEach((tr, i) => {
      const tds = [...tr.querySelectorAll('td')];
      rows.push([
        i + 1,
        tds[1]?.querySelector('div')?.textContent?.trim() || '',
        tds[1]?.querySelectorAll('div')[1]?.textContent?.trim() || '',
        tds[2]?.textContent?.trim() || '',
        tds[3]?.textContent?.trim() || '',
        tr.dataset.label || '',
        tds[4]?.textContent?.trim() || '',
      ]);
    });
    const csv  = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = 'delineation_results.csv';
    a.click();
  }

  async function deleteHistoryRow(tr) {
    const id = tr?.dataset?.id;
    if (!id) return;
    if (!confirm('Delete this analysis? This cannot be undone.')) return;

    try {
      const res = await fetch(`${destroyUrlTpl}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) throw new Error(data.message || 'Unable to delete analysis.');
      tr.remove();
      if (!document.querySelector('#histTable tbody tr')) showEmptyHistory();
    } catch (err) {
      alert(err.message || 'Unable to delete analysis. Please try again.');
    }
  }

  async function deleteAllHistory() {
    if (!confirm('Delete all past analyses? This cannot be undone.')) return;

    const btn = document.getElementById('deleteAllBtn');
    if (btn) btn.disabled = true;

    try {
      const res = await fetch(destroyAllUrl, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) throw new Error(data.message || 'Unable to delete analyses.');
      document.querySelectorAll('#histTable tbody tr').forEach(row => row.remove());
      showEmptyHistory();
    } catch (err) {
      alert(err.message || 'Unable to delete analyses. Please try again.');
      if (btn) btn.disabled = false;
    }
  }

  function showEmptyHistory() {
    const table = document.getElementById('histTable');
    const card  = table ? table.closest('.card') : null;
    table?.remove();
    document.getElementById('deleteAllBtn')?.remove();
    if (card && !card.querySelector('.empty-hist')) {
      const empty = document.createElement('div');
      empty.className = 'empty-hist';
      empty.innerHTML = '<i class="bi bi-inbox" style="font-size:36px;display:block;margin-bottom:10px"></i>No analyses yet. Upload images above to get started.';
      card.appendChild(empty);
    }
  }
</script>
</body>
</html>
