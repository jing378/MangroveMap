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
    // Chunked, parallel and resumable: see partials/chunked-upload.
    function setProgress(percentage, detail = '') {
      const value = Math.max(0, Math.min(100, Math.round(percentage)));
      progress.hidden = false;
      progressBar.setAttribute('aria-valuenow', String(value));
      progressFill.style.width = `${value}%`;
      progressPercent.textContent = value === 0 && !detail ? 'Starting…'
        : (value < 100 ? `${value}%${detail ? ` · ${detail}` : ''}` : 'Finishing…');
    }

    async function upload(files, name) {
      const result = await window.ChunkedUpload.upload({
        url: page.uploads_url,
        files,
        fields: { name },
        parallel: page.upload_parallel,
        resumeKey: 'droneUpload.resume',
        onProgress: ({ loaded, total, speed }) => {
          const detail = [`${formatBytes(loaded)} of ${formatBytes(total)}`, speed > 0 ? `${formatBytes(speed)}/s` : '']
            .filter(Boolean).join(' · ');
          setProgress(total ? (loaded / total) * 100 : 100, detail);
        },
        onResumed: bytes => showMessage(`Resuming the earlier upload: ${formatBytes(bytes)} was already on the server.`),
      });
      setProgress(100);
      return result;
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
        const payload = await upload([...fileInput.files], nameInput.value.trim());
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
    // Residents' imagery and delineation go on the map once an expert approves them; experts publish directly.
    const STEPS = page.needs_expert_review
      ? ['Upload', 'Preview', 'AI detection', 'Your review', 'Expert review', 'On the map']
      : ['Upload', 'Preview', 'AI detection', 'Your review', 'On the map'];
    const MAP_STEP = STEPS.length - 1;
    const isSegmenting = layer => ['queued', 'processing'].includes(layer.segmentation_status);

    // A tiled layer: where its submitted delineation stands with the experts.
    function readyStage(layer) {
      if (isSegmenting(layer)) {
        return { step: 4, busy: true, badge: 'AI running', tone: 'busy', text: 'The AI is detecting mangroves…' };
      }
      const unapplied = layer.has_pending_edits ? ' You saved edits that are not applied yet; open the editor and confirm them.' : '';
      if (layer.review_state === 'pending') {
        return { step: 4, waiting: true, badge: 'Awaiting expert review', tone: 'review', text: `Submitted. An expert will check the imagery and its mangrove areas; once approved, both appear on the map.${unapplied}` };
      }
      if (layer.review_state === 'rejected') {
        return { step: 4, rejected: true, badge: 'Not approved', tone: 'failed', text: `An expert did not approve this delineation${layer.rejection_notes ? `: “${layer.rejection_notes}”` : '.'} Revise it and submit it again.${unapplied}` };
      }
      if (layer.is_published) {
        return { step: MAP_STEP, badge: 'On the map', tone: 'ready', text: `The imagery and its delineation are on the map.${unapplied}` };
      }
      // Confirmed before delineations were submitted with the imagery: confirm again to submit one.
      return { step: 3, waiting: true, badge: 'Not submitted', tone: 'review', text: 'Open the editor, choose the genus present and confirm to submit the mangrove areas.' };
    }

    // step = index of the current step; busy = server work running (polled); waiting = needs the user.
    function stageOf(layer) {
      switch (layer.status) {
        case 'queued': return { step: 1, busy: true, badge: 'Queued', tone: 'busy', text: 'Waiting for the server to prepare the preview…' };
        case 'processing': return { step: 1, busy: true, badge: 'Processing', tone: 'busy', text: 'Stitching the imagery and preparing the preview…' };
        case 'awaiting_review':
          return isSegmenting(layer)
            ? { step: 2, busy: true, badge: 'AI running', tone: 'busy', text: 'The AI is detecting mangroves. You can already open the review, but you can only confirm once the AI has finished.' }
            : { step: 3, waiting: true, badge: 'Ready for your review', tone: 'review', text: page.needs_expert_review
              ? 'Check the mangrove areas (the AI result does most of the work), choose the genus present, then confirm and submit them for expert review.'
              : 'Check the mangrove areas (the AI result does most of the work), choose the genus present, then confirm to publish them with the imagery.' };
        case 'finalizing': return { step: 3, busy: true, badge: 'Building map', tone: 'busy', text: 'Applying your edits and building the map tiles…' };
        case 'ready': return readyStage(layer);
        default: return { step: -1, badge: 'Failed', tone: 'failed', text: `Processing failed: ${layer.error_message || 'unknown error'}` };
      }
    }

    function aiSummary(layer) {
      if (layer.segmentation_status === 'ready' && layer.segmentation_stats) {
        const stats = layer.segmentation_stats;
        const canopyCount = Number(stats.canopy_count ?? 0);
        return `${stats.edited ? 'Mangrove areas (corrected by you)' : 'AI'}: ${Number(stats.mangrove_area_ha || 0).toFixed(2)} ha of mangrove`
          + `${stats.mangrove_coverage_percent ? ` (${Number(stats.mangrove_coverage_percent).toFixed(1)}% of the imagery)` : ''}.`
          + (canopyCount > 0 ? ` ${canopyCount.toLocaleString()} canop${canopyCount === 1 ? 'y' : 'ies'} pinpointed.` : '');
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
      steps.style.gridTemplateColumns = `repeat(${STEPS.length}, 1fr)`; // residents have an extra review step
      STEPS.forEach((label, index) => {
        let state = '';
        if (stage.step === -1) state = index === 0 ? 'is-done' : (index === 1 ? 'is-failed' : '');
        else if (index < stage.step) state = 'is-done';
        else if (index === stage.step) state = stage.rejected ? 'is-failed' : (stage.waiting ? 'is-waiting' : (stage.busy ? 'is-active' : 'is-done'));
        steps.append(element('span', `drone-step ${state}`, label));
      });

      const stateText = element('div', `drone-item-state${stage.tone === 'failed' ? ' is-failed' : ''}`, stage.text);
      const ai = aiSummary(layer);
      if (ai) stateText.append(document.createElement('br'), ai);

      const actions = element('div', 'drone-item-actions');
      if (layer.review_state === 'approved' && (layer.delineation_url || layer.review_url)) {
        // Approved by an expert: open it on the map (focused on its delineation, the imagery shown under it).
        actions.append(linkButton(layer.delineation_url || layer.review_url, 'View', 'bi-eye'));
      } else if (layer.review_url) {
        const label = layer.status === 'awaiting_review' ? (page.needs_expert_review ? 'Review & submit' : 'Review & publish')
          : (layer.status === 'failed' ? 'Edit & retry' : 'Edit imagery');
        actions.append(linkButton(layer.review_url, label, 'bi-pencil-square', layer.status !== 'ready' || stage.badge === 'Not submitted'));
      }
      // A rejected delineation is revised where it was drawn (the existing revise flow on the Delineate map).
      if (layer.review_state === 'rejected' && layer.delineation_url) {
        actions.append(linkButton(layer.delineation_url, 'Revise delineation', 'bi-arrow-repeat', true));
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

    // ---- Experts: everyone else's uploads (residents and other experts), read-only ---------------
    const othersList = document.getElementById('droneOthersList');
    const ROLE_LABELS = { end_user: 'Resident', expert: 'Expert', admin: 'Admin' };

    // Where someone else's upload stands, worded for the expert looking at it.
    function otherStage(layer) {
      switch (layer.status) {
        case 'queued':
        case 'processing': return { busy: true, badge: 'Processing', tone: 'busy', text: 'The server is preparing the preview.' };
        case 'awaiting_review': return { badge: 'With the uploader', tone: 'review', text: 'Prepared and checked by the AI; the uploader has not submitted it yet.' };
        case 'finalizing': return { busy: true, badge: 'Building map', tone: 'busy', text: 'The uploader confirmed it; the map tiles are being built.' };
        case 'ready':
          if (layer.review_state === 'pending') return { badge: 'Awaiting expert review', tone: 'review', text: 'Submitted for review. Open the delineation to approve or reject it; the imagery is shown under it.' };
          if (layer.review_state === 'rejected') return { badge: 'Not approved', tone: 'failed', text: `Not approved${layer.rejection_notes ? `: “${layer.rejection_notes}”` : '.'} The uploader can revise and resubmit it.` };
          if (layer.is_published) return { badge: 'On the map', tone: 'ready', text: 'The imagery and its delineation are on the map.' };
          return { badge: 'Not submitted', tone: 'review', text: 'Processed, but no delineation was submitted from it yet.' };
        default: return { badge: 'Failed', tone: 'failed', text: `Processing failed: ${layer.error_message || 'unknown error'}` };
      }
    }

    function renderOtherLayer(layer) {
      const stage = otherStage(layer);
      const item = element('article', 'drone-item');
      item.dataset.layerId = layer.id;

      const head = element('div', 'drone-item-head');
      const title = element('div');
      title.append(element('div', 'drone-item-name', layer.name));
      const uploader = element('div', 'drone-item-uploader');
      uploader.append(element('i', 'bi bi-person-circle'), element('span', '', `Uploaded by ${layer.uploaded_by || 'Unknown user'}`));
      if (ROLE_LABELS[layer.uploader_role]) {
        uploader.append(element('span', `drone-role${layer.uploader_role === 'expert' ? ' is-expert' : ''}`, ROLE_LABELS[layer.uploader_role]));
      }
      title.append(uploader);
      const uploaded = layer.created_at ? new Date(layer.created_at).toLocaleString() : '';
      title.append(element('div', 'drone-item-meta', `${layer.source_type === 'batch' ? 'Mosaic of several GeoTIFFs' : 'Single GeoTIFF'}${uploaded ? ` · uploaded ${uploaded}` : ''}`));
      head.append(title, element('span', `drone-badge is-${stage.tone}`, stage.badge));

      const stateText = element('div', `drone-item-state${stage.tone === 'failed' ? ' is-failed' : ''}`, stage.text);
      const ai = aiSummary(layer).replace('(corrected by you)', '(corrected by the uploader)');
      if (ai) stateText.append(document.createElement('br'), ai);

      item.append(head, stateText);
      if (layer.delineation_url) {
        const actions = element('div', 'drone-item-actions');
        actions.append(linkButton(
          layer.delineation_url,
          layer.review_state === 'pending' ? 'Review delineation' : 'Open delineation',
          'bi-geo-alt',
          layer.review_state === 'pending'
        ));
        item.append(actions);
      }
      return item;
    }

    function renderOthers(layers) {
      if (!othersList) return;
      othersList.replaceChildren();
      if (!layers.length) {
        othersList.append(element('div', 'drone-empty', 'No uploads from residents or other experts yet.'));
        return;
      }
      layers.forEach(layer => othersList.append(renderOtherLayer(layer)));
    }

    async function loadLayers() {
      const response = await fetch(page.index_url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !Array.isArray(payload.layers)) {
        throw new Error(payload.message || 'Unable to load your drone imagery.');
      }
      const owned = payload.layers.filter(layer => layer.is_owner);
      render(owned);
      const others = page.shows_others ? payload.layers.filter(layer => !layer.is_owner) : [];
      renderOthers(others);

      clearTimeout(pollTimer);
      if (owned.some(layer => stageOf(layer).busy) || others.some(layer => otherStage(layer).busy)) {
        pollTimer = setTimeout(() => loadLayers().catch(error => console.error('Unable to refresh drone imagery.', error)), 5000);
      }
      return owned;
    }

    loadLayers().catch(error => {
      list.replaceChildren(element('div', 'drone-empty', error.message));
      othersList?.replaceChildren(element('div', 'drone-empty', error.message));
    });
  })();
</script>
