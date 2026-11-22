{{-- Tile Explorer (users.tile-explorer): upload a GeoTIFF (partials/chunked-upload), list the viewer's uploads, make tiles
     at a size (polled while the worker runs), browse them in a lazily rendered grid, select, preview and download the
     selection as a ZIP. --}}
<script>
  (() => {
    const page = @json($page);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = id => document.getElementById(id);

    // Upload
    const uploadForm = $('txUploadForm');
    const fileInput = $('txFile');
    const drop = $('txDrop');
    const fileInfo = $('txFileInfo');
    const uploadSize = $('txUploadSize');
    const uploadButton = $('txUploadButton');
    const uploadProgress = $('txUploadProgress');
    const uploadBar = $('txUploadBar');
    const uploadFill = $('txUploadFill');
    const uploadPercent = $('txUploadPercent');
    const uploadMessage = $('txUploadMessage');
    // Uploads list
    const sourceList = $('txSourceList');
    const noSources = $('txNoSources');
    // Current upload
    const current = $('txCurrent');
    const sizeSelect = $('txSize');
    const generateButton = $('txGenerate');
    const status = $('txStatus');
    const statusTitle = $('txStatusTitle');
    const statusText = $('txStatusText');
    const statusAction = $('txStatusAction');
    const progressTrack = $('txProgressTrack');
    const progressFill = $('txProgressFill');
    // Grid
    const browser = $('txBrowser');
    const grid = $('txGrid');
    const gridEmpty = $('txGridEmpty');
    const sentinel = $('txSentinel');
    const searchInput = $('txSearch');
    const sortSelect = $('txSort');
    const showAreas = $('txShowAreas');
    const selectAll = $('txSelectAll');
    const selectAllLabel = $('txSelectAllLabel');
    const selCount = $('txSelCount');
    const selbar = $('txSelbar');
    const selectMangroveButton = $('txSelectMangrove');
    const clearButton = $('txClear');
    const downloadButton = $('txDownload');
    const sendDroneButton = $('txSendDrone');
    const dialog = $('txDialog');

    const PAGE_SIZE = 120;
    const POLL_MS = 2500;
    const threshold = Number(page.mangrove_threshold) || 0;
    const sources = new Map(page.sources.map(source => [source.id, source]));
    const formatBytes = bytes => window.ChunkedUpload.formatBytes(bytes);

    let currentSourceId = null;
    let currentSet = null;     // summary + tiles of the set on screen
    let tiles = [];
    let filtered = [];
    let rendered = 0;
    let selection = new Set();
    let filter = 'all';
    let lastClicked = null;
    let pollTimer = null;
    let loadToken = 0;
    let previewIndex = -1;
    let uploading = false;
    let downloading = false;
    let sendingToDrone = false;
    let downloadWatch = null;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const storage = {
      get(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; } },
      set(key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch {} },
    };
    const notify = (message, type = 'success') => window.AppActions?.notify ? window.AppActions.notify(message, type) : null;
    const sizeOptions = () => page.tile_sizes.map(size =>
      `<option value="${size}">${size} × ${size} px${size === page.default_tile_size ? ' (Roboflow default)' : ''}</option>`).join('');

    async function request(method, url, body) {
      const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          ...(body ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(validation || payload.message || `The server answered with HTTP ${response.status}.`);
      }
      return payload;
    }

    const isBusy = set => set && ['queued', 'detecting', 'processing'].includes(set.status);
    const currentSource = () => sources.get(currentSourceId) || null;
    const setFor = (source, size) => source?.tile_sets.find(set => set.tile_size === size) || null;

    // ---- Upload ----------------------------------------------------------------------------
    function chosenFile() {
      return fileInput.files?.[0] || null;
    }

    function uploadProblem(file) {
      if (!file) return 'Choose a .tif or .tiff file first.';
      if (!/\.tiff?$/i.test(file.name)) return 'Only .tif or .tiff files can be uploaded.';
      if (file.size > page.max_file_bytes) return `This file is ${formatBytes(file.size)}; the limit is ${formatBytes(page.max_file_bytes)}.`;
      return null;
    }

    function describeFile() {
      const file = chosenFile();
      const problem = file ? uploadProblem(file) : null;
      renderChosenFile();
      setUploadMessage(problem || '', Boolean(problem));
      uploadButton.disabled = uploading || !file || Boolean(problem);
    }

    // Set (or clear, with null) the chosen GeoTIFF. One file per upload, so choosing another replaces it.
    function setChosenFile(file) {
      const transfer = new DataTransfer();
      if (file) transfer.items.add(file);
      fileInput.files = transfer.files;
      describeFile();
    }

    // The chosen file with its size, a remove button and Clear, like the Drone Upload page.
    function renderChosenFile() {
      const file = chosenFile();
      fileInfo.replaceChildren();
      if (!file) return;

      const head = document.createElement('div');
      head.className = 'tx-files-head';
      const summary = document.createElement('span');
      summary.textContent = `1 file selected (${formatBytes(file.size)})`;
      const clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'tx-btn tx-files-clear';
      clear.disabled = uploading;
      clear.innerHTML = '<i class="bi bi-x-lg"></i> Clear';
      clear.addEventListener('click', () => setChosenFile(null));
      head.append(summary, clear);

      const list = document.createElement('ul');
      list.className = 'tx-file-list';
      const item = document.createElement('li');
      const name = document.createElement('span');
      name.className = 'tx-file-name';
      name.textContent = file.name;
      const size = document.createElement('span');
      size.className = 'tx-file-size';
      size.textContent = formatBytes(file.size);
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'tx-file-remove';
      remove.disabled = uploading;
      remove.title = `Remove ${file.name}`;
      remove.setAttribute('aria-label', `Remove ${file.name}`);
      remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
      remove.addEventListener('click', () => setChosenFile(null));
      item.append(name, size, remove);
      list.append(item);
      fileInfo.append(head, list);
    }

    function setUploadMessage(text, isError = false) {
      uploadMessage.textContent = text;
      uploadMessage.classList.toggle('is-error', isError);
    }

    function setUploadProgress(percentage, detail = '') {
      const value = Math.max(0, Math.min(100, Math.round(percentage)));
      uploadProgress.hidden = false;
      uploadBar.setAttribute('aria-valuenow', String(value));
      uploadFill.style.width = `${value}%`;
      uploadPercent.textContent = value < 100 ? `${value}%${detail ? ` · ${detail}` : ''}` : 'Finishing…';
    }

    let uploadingFile = null; // the file being sent; choosing another meanwhile is undone

    fileInput.addEventListener('change', () => {
      if (uploading) {
        setChosenFile(uploadingFile);
        return;
      }
      describeFile();
    });
    ['dragenter', 'dragover'].forEach(type => drop.addEventListener(type, event => {
      event.preventDefault();
      drop.classList.add('is-over');
    }));
    ['dragleave', 'drop'].forEach(type => drop.addEventListener(type, event => {
      event.preventDefault();
      drop.classList.remove('is-over');
    }));
    drop.addEventListener('drop', event => {
      const file = [...(event.dataTransfer?.files || [])].find(item => /\.tiff?$/i.test(item.name));
      if (!file) {
        setUploadMessage('Only .tif or .tiff files can be uploaded.', true);
        return;
      }
      if (!uploading) setChosenFile(file);
    });

    // ---- Leaving with a chosen file or a running upload ----------------------------------------
    // Same as Drone Upload: browsers forget the chosen file when the page goes, and leaving stops an upload.
    // Links, Logout, F5 / Ctrl+R / Cmd+R and phone pull-to-refresh ask first in our modal. The browser's
    // reload button, closing the tab or typing another address only get the browser's own prompt (its text
    // can't be set by pages).
    let leaving = false;
    let asking = false;
    const hasWorkToLose = () => !leaving && (uploading || Boolean(chosenFile()));

    // Resolves true (and stops the browser prompt) when the user chooses to leave or reload.
    async function askToLeave(reload = false) {
      if (asking) return false;
      const going = reload ? 'reload the page' : 'leave';
      asking = true;
      const confirmed = await window.AppActions.confirm(uploading
        ? {
          title: 'Stop the upload?',
          message: `The upload is still running and stops if you ${going}. To continue it later, choose the same file again and click Upload.`,
          confirmLabel: reload ? 'Reload and stop upload' : 'Leave and stop upload',
          cancelLabel: reload ? 'Keep uploading' : 'Stay on this page',
          tone: 'danger',
          icon: 'bi-exclamation-triangle',
        }
        : {
          title: reload ? 'Reload without uploading?' : 'Leave without uploading?',
          message: `You chose "${chosenFile()?.name}" but did not upload it. If you ${going}, you will need to choose it again.`,
          confirmLabel: reload ? 'Reload' : 'Leave',
          cancelLabel: reload ? 'Keep my file' : 'Stay on this page',
          tone: 'danger',
          icon: 'bi-exclamation-triangle',
        });
      asking = false;
      if (confirmed) leaving = true;
      return Boolean(confirmed);
    }

    // Phone pull-to-refresh (partials/pull-to-refresh) asks through this before reloading.
    window.confirmReload = () => (hasWorkToLose() ? askToLeave(true) : Promise.resolve(true));

    // Keyboard reload: F5, Ctrl+R / Cmd+R (with or without Shift).
    document.addEventListener('keydown', event => {
      const isReload = event.key === 'F5'
        || ((event.ctrlKey || event.metaKey) && !event.altKey && (event.key === 'r' || event.key === 'R'));
      if (!isReload || !hasWorkToLose()) return;
      event.preventDefault();
      askToLeave(true).then(reload => { if (reload) window.location.reload(); });
    });

    document.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
      const url = new URL(link.href, window.location.href);
      // Same-page anchors (href="#") do not leave the page.
      if (url.origin !== window.location.origin || (url.pathname === window.location.pathname && url.search === window.location.search)) return;
      if (!hasWorkToLose()) return;
      event.preventDefault();
      event.stopPropagation();
      askToLeave().then(leave => { if (leave) window.location.href = url.href; });
    }, true);

    document.addEventListener('submit', event => {
      const target = event.target;
      if (!(target instanceof HTMLFormElement) || !/\/logout$/.test(new URL(target.action, window.location.href).pathname)) return;
      if (!hasWorkToLose()) return;
      event.preventDefault();
      event.stopPropagation();
      askToLeave().then(leave => { if (leave) target.submit(); });
    }, true);

    window.addEventListener('beforeunload', event => {
      if (!hasWorkToLose()) return;
      event.preventDefault();
      event.returnValue = '';
    });

    uploadForm.addEventListener('submit', async event => {
      event.preventDefault();
      const file = chosenFile();
      const problem = uploadProblem(file);
      if (problem) {
        setUploadMessage(problem, true);
        return;
      }

      uploading = true;
      uploadingFile = file;
      renderChosenFile(); // remove/Clear are disabled while the file uploads
      uploadButton.disabled = true;
      uploadButton.innerHTML = '<span class="tx-spinner" aria-hidden="true"></span> <span>Uploading…</span>';
      setUploadProgress(0, 'starting');
      setUploadMessage('Uploading… keep this page open until the upload finishes.');
      try {
        const source = await window.ChunkedUpload.upload({
          url: page.uploads_url,
          files: [file],
          fields: { purpose: 'tiles', tile_size: Number(uploadSize.value), name: file.name.replace(/\.tiff?$/i, '').slice(0, 120) },
          parallel: page.upload_parallel,
          resumeKey: 'tileExplorer.resume',
          onProgress: ({ loaded, total, speed }) => setUploadProgress(total ? (loaded / total) * 100 : 100,
            [`${formatBytes(loaded)} of ${formatBytes(total)}`, speed > 0 ? `${formatBytes(speed)}/s` : ''].filter(Boolean).join(' · ')),
          onResumed: bytes => setUploadMessage(`Resuming the earlier upload: ${formatBytes(bytes)} was already on the server.`),
        });
        uploadForm.reset();
        uploadSize.value = String(source.tile_sets[0]?.tile_size || page.default_tile_size);
        setUploadMessage('Upload complete. The tiles are being made below.');
        sources.set(source.id, source);
        renderSources();
        selectSource(source.id, source.tile_sets[0]?.tile_size);
        current.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        setUploadMessage(error.message, true);
      } finally {
        uploading = false;
        uploadingFile = null;
        uploadProgress.hidden = true;
        uploadButton.innerHTML = '<i class="bi bi-upload"></i> <span>Upload and make tiles</span>';
        describeFile();
        if (!chosenFile()) uploadButton.disabled = true;
      }
    });

    // ---- Your GeoTIFFs ---------------------------------------------------------------------
    function sourcePill(source) {
      const sets = source.tile_sets;
      if (sets.some(isBusy)) return '<span class="tx-pill is-busy"><span class="tx-spinner" aria-hidden="true"></span>Working</span>';
      const ready = sets.filter(set => set.status === 'ready');
      if (ready.length) {
        const shown = setFor(source, Number(sizeSelect.value)) && source.id === currentSourceId
          ? setFor(source, Number(sizeSelect.value)) : ready[0];
        return `<span class="tx-pill">${shown.status === 'ready' ? `${shown.tile_count} tiles` : `${ready[0].tile_count} tiles`}</span>`;
      }
      if (sets.some(set => set.status === 'failed')) return '<span class="tx-pill is-failed">Failed</span>';
      return '';
    }

    function renderSources() {
      const list = [...sources.values()].sort((a, b) => (b.uploaded_at || '').localeCompare(a.uploaded_at || ''));
      noSources.hidden = list.length > 0;
      sourceList.innerHTML = list.map(source => {
        const date = source.uploaded_at ? new Date(source.uploaded_at).toLocaleDateString() : '';
        const meta = [formatBytes(source.file_size), source.crs, date].filter(Boolean).join(' · ');
        const busy = source.tile_sets.some(set => ['detecting', 'processing'].includes(set.status));
        return `<div class="tx-source-row${source.id === currentSourceId ? ' is-active' : ''}" role="listitem">
            <button type="button" class="tx-source-open" data-open="${source.id}" aria-current="${source.id === currentSourceId}">
              <i class="bi bi-file-earmark-image" aria-hidden="true"></i>
              <span class="tx-source-text"><strong>${escapeHtml(source.name)}</strong><small>${escapeHtml(meta)}</small></span>
              ${sourcePill(source)}
            </button>
            <button type="button" class="tx-source-delete" data-delete="${source.id}" ${busy ? 'disabled title="Wait until the tiles are finished"' : ''}
              aria-label="Delete ${escapeHtml(source.name)}"><i class="bi bi-trash"></i></button>
          </div>`;
      }).join('');
    }

    sourceList.addEventListener('click', async event => {
      const open = event.target.closest('[data-open]');
      if (open) {
        selectSource(Number(open.dataset.open));
        return;
      }
      const remove = event.target.closest('[data-delete]');
      if (!remove) return;
      const source = sources.get(Number(remove.dataset.delete));
      if (!source) return;
      const confirmed = await window.AppActions.confirm({
        title: 'Delete this GeoTIFF?',
        message: `"${source.name}" and all tiles made from it are removed from the server. Tiles you already downloaded are not affected.`,
        confirmLabel: 'Delete',
        tone: 'danger',
      });
      if (!confirmed) return;
      try {
        await request('DELETE', `${page.sources_url}/${source.id}`);
        sources.delete(source.id);
        if (currentSourceId === source.id) {
          currentSourceId = null;
          currentSet = null;
          tiles = [];
          current.hidden = true;
          clearTimeout(pollTimer);
        }
        renderSources();
        notify(`"${source.name}" was deleted.`);
      } catch (error) {
        notify(error.message, 'error');
      }
    });

    // ---- Current upload: tile size + status ---------------------------------------------
    function selectSource(id, size = null) {
      const source = sources.get(id);
      if (!source) return;
      if (currentSourceId !== id) {
        currentSet = null;
        tiles = [];
      }
      currentSourceId = id;
      const remembered = storage.get('tileExplorer.current');
      const wanted = size ?? (remembered?.source === id ? remembered.size : null);
      const existing = source.tile_sets.find(set => set.status === 'ready') || source.tile_sets[0];
      sizeSelect.value = String([wanted, existing?.tile_size, page.default_tile_size].find(value => page.tile_sizes.includes(value)));
      current.hidden = false;
      $('txTitle').textContent = source.name;
      renderSources();
      refreshSet();
    }

    function sourceMeta(source, set = null) {
      const parts = [source.original_name, formatBytes(source.file_size)];
      if (source.width && source.height) parts.push(`${source.width.toLocaleString()} × ${source.height.toLocaleString()} px`);
      if (source.crs) parts.push(source.crs);
      if (set?.status === 'ready' && set.columns && set.rows) parts.push(`${set.tile_count} tiles in a ${set.columns} × ${set.rows} grid`);
      return parts.join(' · ');
    }

    function refreshSet() {
      clearTimeout(pollTimer);
      const source = currentSource();
      if (!source) return;
      const size = Number(sizeSelect.value);
      storage.set('tileExplorer.current', { source: source.id, size });
      const set = setFor(source, size);
      $('txMeta').textContent = sourceMeta(source, set);

      generateButton.disabled = isBusy(set);
      generateButton.innerHTML = !set
        ? `<i class="bi bi-grid-3x3-gap"></i> Make ${size} px tiles`
        : (isBusy(set) ? '<span class="tx-spinner" aria-hidden="true"></span> Working…' : '<i class="bi bi-arrow-repeat"></i> Make again');
      generateButton.classList.toggle('primary', !set);

      if (!set) {
        showBrowser(false);
        showStatus('info', `No ${size} px tiles yet`, `Press “Make ${size} px tiles” to cut this GeoTIFF at this size.`);
        return;
      }
      if (isBusy(set)) {
        showBrowser(false);
        showProgress(set);
        schedulePoll();
        return;
      }
      if (set.status === 'failed') {
        showBrowser(false);
        showStatus('error', 'Making the tiles failed', set.error_message || 'Something went wrong while cutting the tiles.', 'Try again', () => generate(true));
        return;
      }
      loadSet(set.id);
    }

    function showStatus(kind, title, text, actionLabel = null, action = null) {
      status.hidden = false;
      status.classList.toggle('is-error', kind === 'error');
      status.classList.toggle('is-warning', kind === 'warning');
      statusTitle.textContent = title;
      statusText.textContent = text;
      progressTrack.hidden = kind !== 'progress';
      statusAction.hidden = !actionLabel;
      statusAction.textContent = actionLabel || '';
      statusAction.onclick = action;
    }

    function hideStatus() {
      status.hidden = true;
    }

    function showProgress(set) {
      const percent = set.progress;
      const texts = {
        queued: 'Waiting for the imagery worker to pick up the job… If this does not start, the drone imagery worker may not be running.',
        detecting: 'The AI is looking for mangroves in the image. Large files can take a few minutes.',
        processing: percent === null ? 'Cutting the tiles…' : `Cutting ${set.tile_size} px tiles… ${Math.round(percent)}%`,
      };
      const indeterminate = set.status !== 'processing' || percent === null;
      showStatus('progress', set.status === 'detecting' ? 'Detecting mangroves' : 'Making tiles', texts[set.status] || 'Working…');
      progressFill.classList.toggle('is-indeterminate', indeterminate);
      progressFill.style.width = indeterminate ? '' : `${Math.max(2, percent)}%`;
    }

    function rememberSource(payload) {
      if (!payload) return;
      const known = sources.get(payload.id);
      if (known) sources.set(payload.id, { ...known, ...payload });
    }

    function rememberSet(summary) {
      const source = sources.get(summary.source_id);
      if (!source) return;
      const { tiles: _tiles, source: _source, ...stored } = summary;
      const index = source.tile_sets.findIndex(set => set.id === summary.id);
      if (index >= 0) source.tile_sets[index] = { ...source.tile_sets[index], ...stored };
      else source.tile_sets.push(stored);
    }

    async function generate(force = false) {
      const source = currentSource();
      if (!source) return;
      const size = Number(sizeSelect.value);
      const existing = setFor(source, size);
      if (existing?.status === 'ready' && force) {
        const confirmed = await window.AppActions.confirm({
          title: 'Make the tiles again?',
          message: 'The current tiles at this size are replaced. Your selection is kept for tiles that still exist.',
          confirmLabel: 'Make again',
        });
        if (!confirmed) return;
      }
      generateButton.disabled = true;
      currentSet = null; // never show the old tile list for a set that is being remade
      tiles = [];
      try {
        rememberSet(await request('POST', `${page.sources_url}/${source.id}/sets`, { tile_size: size, force }));
      } catch (error) {
        notify(error.message, 'error');
      }
      renderSources();
      refreshSet();
    }

    // Polls every set that is still being made (the list pills follow too); stops when none are left.
    function schedulePoll() {
      clearTimeout(pollTimer);
      pollTimer = setTimeout(pollBusy, POLL_MS);
    }

    async function pollBusy() {
      const busy = [...sources.values()].flatMap(source => source.tile_sets.filter(isBusy));
      if (!busy.length) return;
      await Promise.all(busy.map(async set => {
        try {
          const payload = await request('GET', `${page.sets_url}/${set.id}`);
          rememberSource(payload.source);
          rememberSet(payload);
          const onScreen = currentSourceId === payload.source_id && Number(sizeSelect.value) === payload.tile_size;
          if (!onScreen) return;
          if (isBusy(payload)) {
            showProgress(payload);
          } else if (payload.status === 'ready') {
            notify(`${payload.tile_count} tiles are ready.`);
            refreshSet();
          } else {
            refreshSet();
          }
        } catch {
          // keep polling: a short network hiccup must not stop the progress
        }
      }));
      renderSources();
      if ([...sources.values()].some(source => source.tile_sets.some(isBusy))) schedulePoll();
    }

    async function loadSet(setId) {
      const token = ++loadToken;
      if (currentSet?.id === setId && currentSet.status === 'ready' && tiles.length) {
        showBrowser(true);
        labelNotice(currentSet);
        return;
      }
      showBrowser(false);
      showStatus('progress', 'Loading tiles', 'Reading the tile list…');
      progressFill.classList.add('is-indeterminate');
      try {
        const payload = await request('GET', `${page.sets_url}/${setId}`);
        if (token !== loadToken) return;
        rememberSource(payload.source);
        rememberSet(payload);
        if (payload.status === 'ready' && payload.tiles) {
          showSet(payload);
        } else {
          refreshSet();
        }
      } catch (error) {
        if (token === loadToken) showStatus('error', 'Could not load the tiles', error.message, 'Retry', () => loadSet(setId));
      }
    }

    // Unlabelled tiles: say why (no AI model, AI failed) instead of silently hiding the mangrove filters.
    function labelNotice(set) {
      if (set.has_areas) {
        hideStatus();
        return;
      }
      const source = currentSource();
      showStatus('warning', 'These tiles are not labelled',
        `${source?.areas_error || 'The AI could not check this image for mangroves.'} You can still look at the tiles and pick them yourself.`);
    }

    // ---- Grid ----------------------------------------------------------------------------
    const category = tile => tile.mangrove === null || tile.mangrove === undefined
      ? 'unknown'
      : (tile.mangrove >= threshold ? 'mangrove' : 'none');
    const selectionKey = () => `tileExplorer.selection.${currentSet?.id}`;

    function showBrowser(visible) {
      browser.hidden = !visible;
    }

    function showSet(payload) {
      currentSet = payload;
      tiles = payload.tiles || [];
      const known = new Set(tiles.map(tile => tile.id));
      selection = new Set((storage.get(selectionKey()) || []).filter(id => known.has(id)));
      lastClicked = null;

      const source = currentSource();
      if (source) $('txMeta').textContent = sourceMeta(source, payload);

      const counts = { all: tiles.length, mangrove: 0, none: 0 };
      tiles.forEach(tile => { const kind = category(tile); if (kind in counts) counts[kind]++; });
      document.querySelectorAll('[data-count]').forEach(element => { element.textContent = counts[element.dataset.count]; });
      const labelled = payload.has_areas;
      document.querySelectorAll('.tx-chip[data-filter="mangrove"], .tx-chip[data-filter="none"]').forEach(chip => { chip.hidden = !labelled; });
      selectMangroveButton.hidden = !labelled || counts.mangrove === 0;
      sortSelect.querySelectorAll('option[value="most"], option[value="least"]').forEach(option => { option.hidden = !labelled; });
      showAreas.closest('.tx-switch').hidden = !labelled;
      if (!labelled) {
        filter = 'all';
        sortSelect.value = 'position';
      }
      setFilterButtons();
      labelNotice(payload);
      showBrowser(true);
      applyView();
    }

    function applyView() {
      const query = searchInput.value.trim().toLowerCase();
      filtered = tiles.filter(tile =>
        (filter === 'all' || category(tile) === filter)
        && (!query || tile.name.toLowerCase().includes(query) || tile.id.includes(query)));
      if (sortSelect.value === 'most') filtered.sort((a, b) => (b.mangrove ?? -1) - (a.mangrove ?? -1));
      else if (sortSelect.value === 'least') filtered.sort((a, b) => (a.mangrove ?? 101) - (b.mangrove ?? 101));
      else filtered.sort((a, b) => a.row - b.row || a.col - b.col);

      grid.innerHTML = '';
      rendered = 0;
      gridEmpty.hidden = filtered.length > 0;
      renderMore();
      updateSelectionUi();
    }

    function tileUrl(tile, variant) {
      return `${page.sets_url}/${currentSet.id}/tiles/${tile.id}/${variant}?v=${encodeURIComponent(currentSet.generated_at || '')}`;
    }

    function badge(tile) {
      const kind = category(tile);
      if (kind === 'unknown') return '<span class="tx-badge is-unknown" title="The AI could not check this image">Not checked</span>';
      if (kind === 'mangrove') return `<span class="tx-badge is-mangrove" title="${tile.mangrove}% of this tile is mangrove"><i class="bi bi-tree-fill"></i>${Math.round(tile.mangrove)}%</span>`;
      return `<span class="tx-badge is-none" title="${tile.mangrove}% mangrove">${tile.mangrove > 0 ? `${tile.mangrove < 1 ? '<1' : Math.round(tile.mangrove)}%` : 'No mangrove'}</span>`;
    }

    function cardHtml(tile, index) {
      const selected = selection.has(tile.id);
      const size = currentSet.tile_size;
      const outline = tile.outline
        ? `<svg class="tx-outline" viewBox="0 0 ${size} ${size}" preserveAspectRatio="none" aria-hidden="true"><path d="${escapeHtml(tile.outline)}"/></svg>`
        : '';
      return `<div class="tx-tile${selected ? ' is-selected' : ''}" role="option" tabindex="0" aria-selected="${selected}"
          data-id="${escapeHtml(tile.id)}" data-index="${index}" title="${escapeHtml(tile.name)}">
          <div class="tx-thumb">
            <img src="${tileUrl(tile, 'thumb')}" alt="Tile ${escapeHtml(tile.id)}" loading="lazy" decoding="async" width="256" height="256">
            ${outline}
            <span class="tx-check" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
            <button type="button" class="tx-zoom" data-preview tabindex="-1" aria-label="Preview tile ${escapeHtml(tile.id)}"><i class="bi bi-arrows-angle-expand"></i></button>
            ${badge(tile)}
          </div>
          <div class="tx-name"><span class="tx-name-full">${escapeHtml(tile.name)}</span><span class="tx-name-id">${escapeHtml(tile.id)}</span></div>
        </div>`;
    }

    function renderMore() {
      if (rendered >= filtered.length) return;
      const end = Math.min(filtered.length, rendered + PAGE_SIZE);
      let html = '';
      for (let index = rendered; index < end; index++) html += cardHtml(filtered[index], index);
      grid.insertAdjacentHTML('beforeend', html);
      rendered = end;
    }

    new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting) && !browser.hidden) renderMore();
    }, { rootMargin: '800px 0px' }).observe(sentinel);

    // ---- Selection -----------------------------------------------------------------------
    function cardFor(id) {
      return grid.querySelector(`.tx-tile[data-id="${CSS.escape(id)}"]`);
    }

    function paintCard(card) {
      const selected = selection.has(card.dataset.id);
      card.classList.toggle('is-selected', selected);
      card.setAttribute('aria-selected', String(selected));
    }

    function setSelected(ids, selected) {
      ids.forEach(id => (selected ? selection.add(id) : selection.delete(id)));
      if (ids.length > 50) grid.querySelectorAll('.tx-tile').forEach(paintCard);
      else ids.forEach(id => { const card = cardFor(id); if (card) paintCard(card); });
      storage.set(selectionKey(), [...selection]);
      updateSelectionUi();
    }

    function toggleAt(index, withRange) {
      const tile = filtered[index];
      if (!tile) return;
      const select = !selection.has(tile.id);
      if (withRange && lastClicked !== null) {
        const [from, to] = [Math.min(lastClicked, index), Math.max(lastClicked, index)];
        setSelected(filtered.slice(from, to + 1).map(item => item.id), select);
      } else {
        setSelected([tile.id], select);
      }
      lastClicked = index;
    }

    function updateSelectionUi() {
      const count = selection.size;
      const shownSelected = filtered.reduce((sum, tile) => sum + (selection.has(tile.id) ? 1 : 0), 0);
      selectAll.checked = filtered.length > 0 && shownSelected === filtered.length;
      selectAll.indeterminate = shownSelected > 0 && shownSelected < filtered.length;
      selectAll.disabled = filtered.length === 0;
      selectAllLabel.textContent = filtered.length === tiles.length ? `Select all ${tiles.length}` : `Select all ${filtered.length} shown`;
      selCount.innerHTML = count
        ? `<strong>${count}</strong> of ${tiles.length} tile${tiles.length === 1 ? '' : 's'} selected`
        : 'Click tiles to select them · Shift-click selects a range';
      selbar.classList.toggle('has-selection', count > 0);
      grid.classList.toggle('is-selecting', count > 0);
      clearButton.hidden = count === 0;
      if (!downloading) {
        downloadButton.disabled = count === 0;
        // One tile downloads as its GeoTIFF; two or more come as one ZIP of GeoTIFFs.
        downloadButton.innerHTML = count > 1
          ? `<i class="bi bi-file-earmark-zip"></i> <span>Download ${count} as ZIP</span>`
          : `<i class="bi bi-download"></i> <span>${count === 1 ? 'Download GeoTIFF' : 'Download selected'}</span>`;
      }
      if (!sendingToDrone) sendDroneButton.disabled = count === 0;
      if (dialog.open) paintPreviewButton();
    }

    grid.addEventListener('click', event => {
      const card = event.target.closest('.tx-tile');
      if (!card) return;
      const index = Number(card.dataset.index);
      if (event.target.closest('[data-preview]')) {
        openPreview(index);
        return;
      }
      toggleAt(index, event.shiftKey);
    });
    grid.addEventListener('keydown', event => {
      const card = event.target.closest('.tx-tile');
      if (!card) return;
      if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        toggleAt(Number(card.dataset.index), event.shiftKey);
      } else if (event.key.toLowerCase() === 'p') {
        openPreview(Number(card.dataset.index));
      }
    });

    selectAll.addEventListener('change', () => setSelected(filtered.map(tile => tile.id), selectAll.checked));
    clearButton.addEventListener('click', () => setSelected([...selection], false));
    selectMangroveButton.addEventListener('click', () => {
      const ids = tiles.filter(tile => category(tile) === 'mangrove').map(tile => tile.id);
      setSelected(ids, true);
      notify(`${ids.length} mangrove tile${ids.length === 1 ? '' : 's'} selected.`);
    });

    // ---- Toolbar -------------------------------------------------------------------------
    function setFilterButtons() {
      document.querySelectorAll('.tx-chip').forEach(chip => {
        const active = chip.dataset.filter === filter;
        chip.classList.toggle('is-active', active);
        chip.setAttribute('aria-pressed', String(active));
      });
    }

    document.querySelectorAll('.tx-chip').forEach(chip => chip.addEventListener('click', () => {
      filter = chip.dataset.filter;
      setFilterButtons();
      lastClicked = null;
      applyView();
    }));
    let searchTimer = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => { lastClicked = null; applyView(); }, 150);
    });
    sortSelect.addEventListener('change', () => { lastClicked = null; applyView(); });

    const prefs = storage.get('tileExplorer.view') || {};
    showAreas.checked = prefs.showAreas !== false;
    browser.classList.toggle('tx-hide-areas', !showAreas.checked);
    showAreas.addEventListener('change', () => {
      browser.classList.toggle('tx-hide-areas', !showAreas.checked);
      storage.set('tileExplorer.view', { ...storage.get('tileExplorer.view'), showAreas: showAreas.checked });
    });

    function setDensity(density) {
      grid.classList.toggle('is-compact', density === 'compact');
      document.querySelectorAll('[data-density]').forEach(button => {
        const active = button.dataset.density === density;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
    }
    setDensity(prefs.density === 'compact' ? 'compact' : 'comfortable');
    document.querySelectorAll('[data-density]').forEach(button => button.addEventListener('click', () => {
      setDensity(button.dataset.density);
      storage.set('tileExplorer.view', { ...storage.get('tileExplorer.view'), density: button.dataset.density });
    }));

    sizeSelect.addEventListener('change', () => { renderSources(); refreshSet(); });
    generateButton.addEventListener('click', () => {
      const set = setFor(currentSource(), Number(sizeSelect.value));
      generate(set?.status === 'ready' || set?.status === 'failed');
    });

    // ---- Preview -------------------------------------------------------------------------
    function openPreview(index) {
      if (!filtered[index]) return;
      previewIndex = index;
      paintPreview();
      if (!dialog.open) dialog.showModal();
    }

    function paintPreviewButton() {
      const tile = filtered[previewIndex];
      if (!tile) return;
      const selected = selection.has(tile.id);
      const button = $('txPreviewSelect');
      button.classList.toggle('primary', !selected);
      button.innerHTML = selected ? '<i class="bi bi-check2-square"></i> Selected (click to remove)' : '<i class="bi bi-plus-square"></i> Select this tile';
    }

    function paintPreview() {
      const tile = filtered[previewIndex];
      const size = currentSet.tile_size;
      $('txPreviewName').textContent = tile.name;
      const image = $('txPreviewImg');
      image.src = tileUrl(tile, 'thumb'); // shows at once, then the full-size JPEG replaces it
      const full = new Image();
      full.onload = () => { if (filtered[previewIndex] === tile) image.src = full.src; };
      full.src = tileUrl(tile, 'full');
      image.alt = `Tile ${tile.id}`;
      const outline = $('txPreviewOutline');
      outline.setAttribute('viewBox', `0 0 ${size} ${size}`);
      outline.innerHTML = tile.outline && showAreas.checked ? `<path d="${escapeHtml(tile.outline)}"/>` : '';

      const [west, south, east, north] = tile.bounds;
      const midLat = (south + north) / 2;
      const widthM = (east - west) * 111320 * Math.cos(midLat * Math.PI / 180);
      const heightM = (north - south) * 110540;
      const kind = category(tile);
      const coverage = kind === 'unknown'
        ? 'Not checked'
        : `${tile.mangrove}%<div class="tx-coverage-bar"><span style="width:${Math.min(100, tile.mangrove)}%"></span></div>`;
      $('txFacts').innerHTML = `
        <dt>Label</dt><dd>${kind === 'mangrove' ? 'Mangrove' : (kind === 'none' ? 'No mangrove' : 'Not checked')}</dd>
        <dt>Mangrove</dt><dd>${coverage}</dd>
        <dt>Imagery</dt><dd>${tile.valid}% of the tile</dd>
        <dt>Position</dt><dd>Row ${tile.row}, column ${tile.col}</dd>
        <dt>Ground size</dt><dd>${widthM.toFixed(1)} × ${heightM.toFixed(1)} m</dd>
        <dt>Center</dt><dd>${midLat.toFixed(6)}, ${((west + east) / 2).toFixed(6)}</dd>
        <dt>Tile</dt><dd>${size} × ${size} px</dd>`;
      $('txPrev').disabled = previewIndex <= 0;
      $('txNext').disabled = previewIndex >= filtered.length - 1;
      paintPreviewButton();
    }

    function stepPreview(delta) {
      const next = previewIndex + delta;
      if (next < 0 || next >= filtered.length) return;
      previewIndex = next;
      while (rendered <= next) renderMore();
      paintPreview();
      cardFor(filtered[next].id)?.scrollIntoView({ block: 'nearest' });
    }

    $('txPrev').addEventListener('click', () => stepPreview(-1));
    $('txNext').addEventListener('click', () => stepPreview(1));
    $('txClose').addEventListener('click', () => dialog.close());
    $('txPreviewSelect').addEventListener('click', () => toggleAt(previewIndex, false));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); stepPreview(-1); }
      else if (event.key === 'ArrowRight') { event.preventDefault(); stepPreview(1); }
      else if (event.key === ' ' && !event.target.closest('button')) { event.preventDefault(); toggleAt(previewIndex, false); }
    });
    dialog.addEventListener('close', () => {
      const tile = filtered[previewIndex];
      if (tile) cardFor(tile.id)?.focus({ preventScroll: true });
    });

    // ---- Download ------------------------------------------------------------------------
    // The form posts into a hidden frame, so the browser saves the file like any download: one tile as its
    // GeoTIFF, several as a ZIP of GeoTIFFs. The server sets a cookie with our token when the file starts;
    // an error page loads in the frame instead.
    const frame = $('txDownloadFrame');

    function finishDownload(message, type) {
      downloading = false;
      clearInterval(downloadWatch);
      document.cookie = `${page.download_cookie}=; Max-Age=0; path=/`;
      downloadButton.classList.remove('is-saving');
      updateSelectionUi(); // restores the button's label
      if (message) notify(message, type);
    }

    frame.addEventListener('load', () => {
      if (!downloading) return;
      let text = '';
      try {
        const body = frame.contentDocument?.body;
        text = (body?.innerText || '').trim();
        const json = JSON.parse(text);
        text = json.errors ? Object.values(json.errors).flat()[0] : json.message;
      } catch {}
      finishDownload(text && text.length < 300 ? `Download failed: ${text}` : 'Download failed. Reload the page and try again.', 'error');
    });

    downloadButton.addEventListener('click', () => {
      const ids = tiles.filter(tile => selection.has(tile.id)).map(tile => tile.id);
      if (!ids.length || downloading || !currentSet) return;

      const token = Array.from(crypto.getRandomValues(new Uint8Array(12)), byte => byte.toString(16).padStart(2, '0')).join('');
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${page.sets_url}/${currentSet.id}/download`;
      form.target = 'txDownloadFrame';
      form.hidden = true;
      Object.entries({ _token: csrfToken, ids: ids.join(','), token }).forEach(([name, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      });

      downloading = true;
      downloadButton.disabled = true;
      downloadButton.classList.add('is-saving');
      const single = ids.length === 1;
      downloadButton.innerHTML = single
        ? '<span class="tx-spinner" aria-hidden="true"></span> Downloading…'
        : `<span class="tx-spinner" aria-hidden="true"></span> Preparing ZIP of ${ids.length}…`;
      const startedAt = Date.now();
      downloadWatch = setInterval(() => {
        if (document.cookie.split('; ').includes(`${page.download_cookie}=${token}`)) {
          finishDownload(single ? 'Your GeoTIFF is downloading.' : `Your ZIP with ${ids.length} GeoTIFFs is downloading.`, 'success');
        } else if (Date.now() - startedAt > 5 * 60 * 1000) {
          finishDownload('The download is taking long. Check your browser’s downloads.', 'success');
        }
      }, 400);

      document.body.appendChild(form);
      form.submit();
      form.remove();
    });

    // ---- Send to Drone Imagery ----------------------------------------------------------
    // The selected tiles' GeoTIFFs are copied into a new drone layer (one mosaic) and processed like a Drone
    // Upload; the tiles stay here. Then the Drone Upload page shows its progress.
    sendDroneButton.addEventListener('click', async () => {
      const ids = tiles.filter(tile => selection.has(tile.id)).map(tile => tile.id);
      if (!ids.length || sendingToDrone || !currentSet) return;
      const source = currentSource();
      const many = ids.length > 1;
      const confirmed = await window.AppActions.confirm({
        title: 'Send to Drone Imagery?',
        message: `${many ? `The ${ids.length} selected tiles are joined into one drone imagery layer` : 'The selected tile becomes a drone imagery layer'}`
          + ` named "${source?.name || 'Drone imagery'}". It is processed like a Drone Upload (map tiles, then the AI mangrove areas),`
          + ' and you review it on the Drone Upload page before it is submitted. The tiles stay here too.',
        confirmLabel: 'Send',
        tone: 'primary',
      });
      if (!confirmed) return;

      sendingToDrone = true;
      window.AppActions.setBusy(sendDroneButton, 'Sending…');
      try {
        const payload = await request('POST', `${page.sets_url}/${currentSet.id}/drone-imagery`, { ids });
        notify(`"${payload.name}" was sent to Drone Imagery with ${payload.file_count} tile${payload.file_count === 1 ? '' : 's'}. It is being processed.`, 'success', {
          title: 'Sent to Drone Imagery',
          icon: 'bi-send-check',
          action: { label: 'Open Drone Upload', href: payload.drone_upload_url },
          timeout: 10000,
        });
      } catch (error) {
        notify(error.message, 'error');
      } finally {
        sendingToDrone = false;
        window.AppActions.clearBusy(sendDroneButton);
        updateSelectionUi();
      }
    });

    // ---- Start ---------------------------------------------------------------------------
    uploadSize.innerHTML = sizeOptions();
    sizeSelect.innerHTML = sizeOptions();
    uploadSize.value = String(page.default_tile_size);
    renderSources();
    const remembered = storage.get('tileExplorer.current');
    const first = [...sources.values()].sort((a, b) => (b.uploaded_at || '').localeCompare(a.uploaded_at || ''))[0];
    const start = remembered && sources.has(remembered.source) ? remembered.source : first?.id;
    if (start) selectSource(start);
    if ([...sources.values()].some(source => source.tile_sets.some(isBusy))) schedulePoll();
  })();
</script>
