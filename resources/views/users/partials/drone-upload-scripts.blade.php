{{-- Drone Upload tab (users.drone-upload): GeoTIFF upload with progress, and the owner's layers with their
     pipeline state (prepare → AI detection → review → map tiles), polled while server work is running. --}}
<script>
  (() => {
    const page = @json($page);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const form = document.getElementById('droneUploadForm');
    const nameInput = document.getElementById('droneUploadName');
    const fileInput = document.getElementById('droneUploadFiles');
    const drop = document.getElementById('droneUploadDrop');
    const selected = document.getElementById('droneUploadSelected');
    const submit = document.getElementById('droneUploadSubmit');
    const progress = document.getElementById('droneUploadProgress');
    const progressBar = document.getElementById('droneUploadBar');
    const progressFill = progressBar.querySelector('.drone-progress-fill');
    const progressPercent = document.getElementById('droneUploadPercent');
    const message = document.getElementById('droneUploadMessage');
    const list = document.getElementById('droneList');

    let highlightId = page.highlight_id;
    let pollTimer = null;

    function showMessage(text, isError = false) {
      message.textContent = text;
      message.classList.toggle('is-error', isError);
    }

    function formatBytes(bytes) {
      if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
      if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
      return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    // ---- Upload button: like the delineation's Save, only clickable once there is something to send ----
    const SUBMIT_IDLE_HTML = submit.innerHTML;
    let uploading = false;

    function uploadBlocker() {
      const count = fileInput.files?.length || 0;
      if (!count) return 'Choose at least one .tif or .tiff file first.';
      if (page.max_files && count > page.max_files) return `Up to ${page.max_files} files per upload.`;
      return null;
    }

    function updateSubmitState() {
      const blocker = uploadBlocker();
      submit.disabled = uploading || blocker !== null;
      submit.classList.toggle('is-saving', uploading);
      submit.setAttribute('aria-busy', uploading ? 'true' : 'false');
      submit.innerHTML = uploading ? '<span class="drone-spinner" aria-hidden="true"></span> Uploading…' : SUBMIT_IDLE_HTML;
      submit.title = uploading ? '' : (blocker || '');
    }

    // ---- File selection and drag & drop ------------------------------------------------------
    function describeSelection() {
      updateSubmitState();
      const files = [...(fileInput.files || [])];
      if (!files.length) {
        selected.textContent = '';
        return;
      }
      const total = files.reduce((sum, file) => sum + file.size, 0);
      selected.textContent = files.length === 1
        ? `${files[0].name} (${formatBytes(total)})`
        : `${files.length} files selected (${formatBytes(total)}), stitched into one mosaic`;
      if (!nameInput.value.trim()) nameInput.value = files[0].name.replace(/\.(tiff?|TIFF?)$/, '').slice(0, 120);
    }

    fileInput.addEventListener('change', describeSelection);
    ['dragenter', 'dragover'].forEach(type => drop.addEventListener(type, event => {
      event.preventDefault();
      drop.classList.add('is-over');
    }));
    ['dragleave', 'drop'].forEach(type => drop.addEventListener(type, event => {
      event.preventDefault();
      drop.classList.remove('is-over');
    }));
    drop.addEventListener('drop', event => {
      const files = [...(event.dataTransfer?.files || [])].filter(file => /\.tiff?$/i.test(file.name));
      if (!files.length) {
        showMessage('Only .tif or .tiff files can be uploaded.', true);
        return;
      }
      const transfer = new DataTransfer();
      files.forEach(file => transfer.items.add(file));
      fileInput.files = transfer.files;
      describeSelection();
    });

    // ---- Upload ---------------------------------------------------------------------------
    function setProgress(percentage) {
      const value = Math.max(0, Math.min(100, Math.round(percentage)));
      progress.hidden = false;
      progressBar.setAttribute('aria-valuenow', String(value));
      progressFill.style.width = `${value}%`;
      progressPercent.textContent = value === 0 ? 'Waiting for the server…'
        : (value < 100 ? `${value}%` : 'Saving files on the server…');
    }

    function upload(formData) {
      return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', page.upload_url);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        request.upload.addEventListener('progress', event => {
          if (event.lengthComputable) setProgress((event.loaded / event.total) * 100);
        });
        request.addEventListener('load', () => {
          let payload;
          try {
            payload = JSON.parse(request.responseText);
          } catch {
            // Usually a PHP warning or HTML error page (upload limits, timeout, expired session).
            const status = request.status ? ` (HTTP ${request.status})` : '';
            const hint = request.status === 413 ? ' The files are larger than the server allows.'
              : (request.status === 419 ? ' Your session expired; reload the page and try again.' : ' Check the server upload limits in php.ini.');
            reject(new Error(`The server returned an invalid response while uploading${status}.${hint}`));
            return;
          }
          if (request.status < 200 || request.status >= 300) {
            const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            reject(new Error(validationError || payload.message || 'Unable to upload the GeoTIFF files.'));
            return;
          }
          resolve(payload);
        });
        request.addEventListener('error', () => reject(new Error('Network error while uploading.')));
        request.addEventListener('abort', () => reject(new Error('The upload was cancelled.')));
        request.send(formData);
      });
    }

    window.addEventListener('beforeunload', event => {
      if (!uploading) return;
      event.preventDefault();
      event.returnValue = '';
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!fileInput.files?.length) {
        showMessage('Choose at least one .tif or .tiff file.', true);
        return;
      }
      if (page.max_files && fileInput.files.length > page.max_files) {
        showMessage(`You selected ${fileInput.files.length} files, but the server accepts up to ${page.max_files} per upload. Upload them in smaller batches.`, true);
        return;
      }

      // Confirmation → Processing (button label + progress bar) → Success or Error (message below the form).
      const count = fileInput.files.length;
      const confirmed = await window.AppActions.confirm({
        title: 'Upload and process?',
        message: count === 1
          ? `"${fileInput.files[0].name}" is uploaded, prepared for review and checked by the AI.`
          : `${count} GeoTIFF files are uploaded, stitched into one mosaic and checked by the AI.`,
        confirmLabel: 'Upload',
      });
      if (!confirmed || uploading || uploadBlocker()) return;

      uploading = true;
      updateSubmitState();
      setProgress(0);
      showMessage('Uploading… keep this page open until the upload finishes.');
      try {
        const payload = await upload(new FormData(form));
        form.reset();
        selected.textContent = '';
        highlightId = payload.id;
        showMessage('Upload complete. The server is preparing a preview and running AI mangrove detection. You can leave this page; come back when it says "Ready for your review".');
        await loadLayers();
      } catch (error) {
        showMessage(error.message, true);
        loadLayers().catch(() => {});
      } finally {
        uploading = false;
        updateSubmitState(); // the form was reset on success, so this disables it again
        progress.hidden = true;
      }
    });

    updateSubmitState();

    // ---- Layer list ------------------------------------------------------------------------
    const STEPS = ['Upload', 'Preview', 'AI detection', 'Your review', 'On the map'];
    const isSegmenting = layer => ['queued', 'processing'].includes(layer.segmentation_status);

    // step = index of the current step; busy = server work running (polled); waiting = needs the user.
    function stageOf(layer) {
      switch (layer.status) {
        case 'queued': return { step: 1, busy: true, badge: 'Queued', tone: 'busy', text: 'Waiting for the server to prepare the preview…' };
        case 'processing': return { step: 1, busy: true, badge: 'Processing', tone: 'busy', text: 'Stitching the imagery and preparing the preview…' };
        case 'awaiting_review':
          return isSegmenting(layer)
            ? { step: 2, busy: true, badge: 'AI running', tone: 'busy', text: 'The AI is detecting mangroves. You can already open the review, but you can only confirm once the AI has finished.' }
            : { step: 3, waiting: true, badge: 'Ready for your review', tone: 'review', text: 'Remove the parts that are not mangrove (the AI result can do most of the work), then add the imagery to the map.' };
        case 'finalizing': return { step: 4, busy: true, badge: 'Building map', tone: 'busy', text: 'Applying your edits and building the map tiles…' };
        case 'ready':
          return isSegmenting(layer)
            ? { step: 4, busy: true, badge: 'On the map', tone: 'ready', text: 'On the map. The AI is detecting mangroves…' }
            : { step: 5, badge: 'On the map', tone: 'ready', text: layer.has_pending_edits ? 'On the map. You saved edits that are not applied yet; open the editor and confirm them.' : 'On the map.' };
        default: return { step: -1, badge: 'Failed', tone: 'failed', text: `Processing failed: ${layer.error_message || 'unknown error'}` };
      }
    }

    function aiSummary(layer) {
      if (layer.segmentation_status === 'ready' && layer.segmentation_stats) {
        const stats = layer.segmentation_stats;
        return `${stats.edited ? 'Mangrove areas (corrected by you)' : 'AI'}: ${Number(stats.mangrove_area_ha || 0).toFixed(2)} ha of mangrove`
          + `${stats.mangrove_coverage_percent ? ` (${Number(stats.mangrove_coverage_percent).toFixed(1)}% of the imagery)` : ''}.`;
      }
      if (layer.segmentation_status === 'failed') return `AI detection failed: ${layer.segmentation_error || 'unknown error'}`;
      return '';
    }

    function element(tag, className, text) {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text !== undefined) node.textContent = text;
      return node;
    }

    function linkButton(href, label, icon, primary = false) {
      const link = element('a', `drone-btn${primary ? ' primary' : ''}`);
      link.href = href;
      link.innerHTML = `<i class="bi ${icon}"></i> `;
      link.append(label);
      return link;
    }

    function renderLayer(layer) {
      const stage = stageOf(layer);
      const item = element('article', 'drone-item');
      item.dataset.layerId = layer.id;

      const head = element('div', 'drone-item-head');
      const title = element('div');
      title.append(element('div', 'drone-item-name', layer.name));
      const uploaded = layer.created_at ? new Date(layer.created_at).toLocaleString() : '';
      title.append(element('div', 'drone-item-meta', `${layer.source_type === 'batch' ? 'Mosaic of several GeoTIFFs' : 'Single GeoTIFF'}${uploaded ? ` · uploaded ${uploaded}` : ''}`));
      head.append(title, element('span', `drone-badge is-${stage.tone}`, stage.badge));

      const steps = element('div', 'drone-steps');
      steps.setAttribute('aria-hidden', 'true');
      STEPS.forEach((label, index) => {
        let state = '';
        if (stage.step === -1) state = index === 0 ? 'is-done' : (index === 1 ? 'is-failed' : '');
        else if (index < stage.step) state = 'is-done';
        else if (index === stage.step) state = stage.waiting ? 'is-waiting' : (stage.busy ? 'is-active' : 'is-done');
        steps.append(element('span', `drone-step ${state}`, label));
      });

      const stateText = element('div', `drone-item-state${stage.tone === 'failed' ? ' is-failed' : ''}`, stage.text);
      const ai = aiSummary(layer);
      if (ai) stateText.append(document.createElement('br'), ai);

      const actions = element('div', 'drone-item-actions');
      if (layer.review_url) {
        const label = layer.status === 'awaiting_review' ? 'Review & add to map'
          : (layer.status === 'failed' ? 'Edit & retry' : 'Edit imagery');
        actions.append(linkButton(layer.review_url, label, 'bi-pencil-square', layer.status !== 'ready'));
      }
      if (layer.status === 'ready') {
        actions.append(linkButton(`${page.map_url}?drone=upload&drone_layer=${encodeURIComponent(layer.id)}`, 'View on map', 'bi-map', !layer.review_url));
      }
      const remove = element('button', 'drone-btn danger');
      remove.type = 'button';
      remove.innerHTML = '<i class="bi bi-trash"></i> Delete';
      remove.disabled = Boolean(stage.busy); // the server refuses deletes while a job uses the files
      remove.title = remove.disabled ? 'Wait until processing finishes before deleting.' : 'Delete this imagery and its files';
      remove.addEventListener('click', async () => {
        // Confirmation → Processing → Success or Error (partials.action-feedback).
        const outcome = await window.AppActions.run({
          confirm: {
            title: 'Delete this record?',
            message: `"${layer.name}" and its GeoTIFF files are permanently deleted. This cannot be undone.`,
            confirmLabel: 'Delete',
            tone: 'danger',
          },
          button: remove,
          busyLabel: 'Deleting...',
          task: async () => {
            const response = await fetch(`${page.layer_url}/${encodeURIComponent(layer.id)}`, {
              method: 'DELETE',
              headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
              credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || 'Unable to delete this imagery.');
          },
          successMessage: `"${layer.name}" was deleted.`,
        });
        if (outcome.status === 'done') await loadLayers();
      });
      actions.append(remove);

      item.append(head, steps, stateText, actions);
      return item;
    }

    function render(layers) {
      list.replaceChildren();
      if (!layers.length) {
        const empty = element('div', 'drone-empty');
        empty.innerHTML = '<i class="bi bi-airplane"></i>';
        empty.append('No drone imagery yet. Upload a GeoTIFF to get started.');
        list.append(empty);
        return;
      }
      layers.forEach(layer => list.append(renderLayer(layer)));

      if (highlightId) {
        const target = list.querySelector(`[data-layer-id="${Number(highlightId)}"]`);
        if (target) {
          target.classList.add('is-highlighted');
          target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          const id = highlightId;
          setTimeout(() => {
            if (highlightId === id) highlightId = null;
            list.querySelector(`[data-layer-id="${Number(id)}"]`)?.classList.remove('is-highlighted');
          }, 4000);
        }
      }
    }

    async function loadLayers() {
      const response = await fetch(page.index_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !Array.isArray(payload.layers)) {
        throw new Error(payload.message || 'Unable to load your drone imagery.');
      }
      // Shared expert layers are shown on the map; this tab lists only the viewer's own uploads.
      const owned = payload.layers.filter(layer => layer.is_owner);
      render(owned);

      clearTimeout(pollTimer);
      if (owned.some(layer => stageOf(layer).busy)) {
        pollTimer = setTimeout(() => loadLayers().catch(error => console.error('Unable to refresh drone imagery.', error)), 5000);
      }
      return owned;
    }

    loadLayers().catch(error => {
      list.replaceChildren(element('div', 'drone-empty', error.message));
    });
  })();
</script>
