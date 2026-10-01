  <script>
    const uploadUrl = @json(route('delineation.store'));
    const destroyAllUrl = @json(route('delineation.destroyAll'));
    const destroyUrlTpl = @json(url('/delineation/analyses'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── State ──
    let queue = [];  // { file, thumb, originalUrl, overlayUrl, result, status }
    let activeIndex = -1;
    let isRunning = false;
    let previewMode = 'original';

    // ── Drop zone ──
    const dropZone = document.getElementById('dropZone');
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
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
          <div class="qi-progress"><div class="qi-progress-fill" id="prog-${idx}" style="width:${item.status === 'done' ? '100' : item.status === 'running' ? '50' : '0'}%"></div></div>
        </div>
        <span class="qi-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>`;
        list.appendChild(div);
      });
    }

    function selectQueueItem(idx) {
      activeIndex = idx;
      renderQueue();
      const item = queue[idx];
      showPreview(item.originalUrl, item.overlayUrl, item.result);
      if (window.innerWidth <= 860) {
        const previewEl = document.querySelector('.preview-card');
        if (previewEl) previewEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    function normalizeUrl(url) {
      if (!url) return '';
      if (url.startsWith('blob:') || url.startsWith('data:')) return url;
      const idx = url.indexOf('/storage/');
      if (idx !== -1) return url.substring(idx);
      return url;
    }

    function showPreview(originalUrl, overlayUrl, result) {
      const img = document.getElementById('previewImg');
      const noMsg = document.getElementById('noImgMsg');
      const stats = document.getElementById('resultStats');
      const btnO = document.getElementById('btnOriginal');
      const btnOv = document.getElementById('btnOverlay');

      const cleanOriginal = normalizeUrl(originalUrl);
      const cleanOverlay = normalizeUrl(overlayUrl);

      const targetUrl = (previewMode === 'overlay' && cleanOverlay) ? cleanOverlay : cleanOriginal;
      if (targetUrl) {
        img.style.display = 'block';
        noMsg.style.display = 'none';
        img.src = targetUrl;
      }
      btnO.disabled = !cleanOriginal;
      btnOv.disabled = !cleanOverlay;

      if (result) {
        const cov = Number(result.mangrove_coverage_percent || 0);
        const conf = Math.round(Number(result.mean_confidence || 0) * 100);
        document.getElementById('rsLabel').textContent = result.label || '—';
        document.getElementById('rsCoverage').textContent = cov.toFixed(1) + '%';
        document.getElementById('rsConf').textContent = conf + '%';
        document.getElementById('rsManBar').style.width = cov + '%';
        document.getElementById('rsManPct').textContent = cov.toFixed(1) + '%';
        document.getElementById('rsBgBar').style.width = Math.max(0, 100 - cov) + '%';
        document.getElementById('rsBgPct').textContent = Math.max(0, 100 - cov).toFixed(1) + '%';
        document.getElementById('rsRec').textContent = result.recommendations || '';
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
          const res = await fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
          });
          const data = await res.json();

          if (!res.ok || !data.ok) throw new Error(data.message || 'Segmentation failed.');

          item.status = 'done';
          item.overlayUrl = data.overlay_url || null;
          item.result = data;
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
      document.getElementById('noImgMsg').style.display = 'block';
      document.getElementById('resultStats').style.display = 'none';
      document.getElementById('btnOriginal').disabled = true;
      document.getElementById('btnOverlay').disabled = true;
    }

    // ── History ──
    function viewHistoryRow(tr) {
      const original = tr.dataset.original;
      const overlay = tr.dataset.overlay;
      const result = {
        label: tr.dataset.label,
        mangrove_coverage_percent: parseFloat(tr.dataset.coverage),
        mean_confidence: parseFloat(tr.dataset.conf) / 100,
        recommendations: tr.dataset.rec,
      };
      activeIndex = -1;
      previewMode = overlay ? 'overlay' : 'original';
      document.getElementById('btnOriginal').classList.toggle('active', previewMode === 'original');
      document.getElementById('btnOverlay').classList.toggle('active', previewMode === 'overlay');
      showPreview(original, overlay, result);
      const previewEl = document.querySelector('.preview-card');
      if (previewEl && window.innerWidth <= 860) {
        previewEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    function appendHistoryRow(data, filename) {
      let tbody = document.querySelector('#histTable tbody');
      if (!tbody) {
        const empty = document.querySelector('.empty-hist');
        if (empty) {
          const card = empty.closest('.card');
          empty.remove();
          if (card) {
            const wrap = document.createElement('div');
            wrap.className = 'tbl-responsive';
            wrap.innerHTML = `
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
                <tbody></tbody>
              </table>`;
            card.appendChild(wrap);
            tbody = wrap.querySelector('tbody');
          }
        }
      }
      if (!tbody) return;
      const cov = Number(data.mangrove_coverage_percent || 0).toFixed(1);
      const conf = Math.round(Number(data.mean_confidence || 0) * 100);
      const now = new Date().toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
      const tr = document.createElement('tr');
      tr.dataset.id = data.analysis_id || '';
      tr.dataset.original = data.image_url || '';
      tr.dataset.overlay = data.overlay_url || '';
      tr.dataset.label = data.label || '—';
      tr.dataset.coverage = cov;
      tr.dataset.conf = conf;
      tr.dataset.rec = data.recommendations || '';
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
    }

    function exportCSV() {
      const rows = [['#', 'Filename', 'Date', 'Coverage %', 'Confidence %', 'Status', 'Label']];
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
      const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], { type: 'text/csv' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
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
      const card = table ? table.closest('.card') : null;
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
