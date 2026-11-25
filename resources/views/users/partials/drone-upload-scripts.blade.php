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

    // Files over the per-file limit (the server would refuse them after the upload starts), or null.
    function oversizedFiles() {
      if (!page.max_file_bytes) return null;
      const big = [...(fileInput.files || [])].filter(file => file.size > page.max_file_bytes);
      if (!big.length) return null;
      const names = big.slice(0, 3).map(file => `${file.name} (${formatBytes(file.size)})`).join(', ');
      return `Each file can be at most ${formatBytes(page.max_file_bytes)}. Too large: ${names}${big.length > 3 ? ` and ${big.length - 3} more` : ''}.`;
    }

    // ---- File kinds: GeoTIFFs, or drone photos (JPG/PNG) with optional location files --------
    // Photos are placed on the map from their own GPS and camera data on the server (DronePhotoGeoreferencer);
    // a .jgw/.pgw/.wld/.prj/.aux.xml file belongs to the photo with the same name.
    const ACCEPTED_FILE = /\.(tiff?|jpe?g|png|jgw|jpgw|jpegw|pgw|pngw|wld|prj|aux\.xml)$/i;
    function fileKind(name) {
      if (/\.tiff?$/i.test(name)) return 'geotiff';
      if (/\.(jpe?g|png)$/i.test(name)) return 'picture';
      return ACCEPTED_FILE.test(name) ? 'sidecar' : null;
    }
    // "a" for a.jpg, a.jgw, a.prj and a.jpg.aux.xml (DronePhotoGeoreferencer::stem).
    function fileStem(name) {
      const lower = name.toLowerCase();
      if (lower.endsWith('.aux.xml')) return lower.slice(0, -8).replace(/\.(jpe?g|png)$/, '');
      return lower.replace(/\.[^.]+$/, '');
    }
    const kindCount = (files, kind) => files.filter(file => fileKind(file.name) === kind).length;

    // Why this mix of files cannot be uploaded (like DroneImageryUploadController::validateDroneBatch), or null.
    function batchProblem(files) {
      const geotiffs = kindCount(files, 'geotiff');
      const pictures = kindCount(files, 'picture');
      if (geotiffs && pictures) return 'Upload GeoTIFFs and photos separately: one upload is either GeoTIFF files or photos.';
      if (page.max_files && geotiffs > page.max_files) return `Up to ${page.max_files} GeoTIFF files per upload.`;
      if (page.max_photos && pictures > page.max_photos) return `Up to ${page.max_photos} photos per upload. Upload the rest as another survey.`;
      const stems = new Set(files.filter(file => fileKind(file.name) === 'picture').map(file => fileStem(file.name)));
      const orphan = files.find(file => fileKind(file.name) === 'sidecar' && !stems.has(fileStem(file.name)));
      if (orphan) return `"${orphan.name}" is a location file, but no photo with the same name is selected. Add the photo or remove the file.`;
      return null;
    }

    function uploadBlocker() {
      const files = [...(fileInput.files || [])];
      if (!files.length) return 'Choose GeoTIFF files or drone photos first.';
      if (!kindCount(files, 'geotiff') && !kindCount(files, 'picture')) return 'Add the photo that goes with the location file.';
      const problem = batchProblem(files);
      if (problem) return problem;
      const tooBig = oversizedFiles();
      if (tooBig) return tooBig;
      if (placeCheck.state === 'checking') return 'Checking where the files are…';
      if (placeCheck.state === 'far') return placeCheck.message;
      return null;
    }

    // ---- Same-place check ------------------------------------------------------------------
    // One upload becomes one mosaic, so its files must come from one site: files from different towns
    // (e.g. Macrohon + Bontoc) would make a huge, mostly empty image. Before anything is sent, read each
    // GeoTIFF's header (geotiff.js only fetches the bytes it needs) and group the footprints by distance,
    // like DroneBatchFootprints on the server. Unknown CRS or unreadable headers skip the check here;
    // ProcessDroneImagery still checks every batch after upload.
    const MAX_GAP_M = Number(page.batch_max_gap_m) || 1000;
    let placeCheck = { state: 'idle', message: '' };
    let placeCheckRun = 0;

    const WGS84 = { a: 6378137, f: 1 / 298.257223563 };
    const CLARKE_1866 = { a: 6378206.4, f: 1 / 294.9786982 };

    // Inverse transverse Mercator (Snyder), latitude of origin 0. Returns [lng, lat] in degrees.
    function inverseTransverseMercator(x, y, { lon0, k0, fe, fn, a, f }) {
      const e2 = f * (2 - f);
      const ep2 = e2 / (1 - e2);
      const mu = ((y - fn) / k0) / (a * (1 - e2 / 4 - 3 * e2 ** 2 / 64 - 5 * e2 ** 3 / 256));
      const e1 = (1 - Math.sqrt(1 - e2)) / (1 + Math.sqrt(1 - e2));
      const phi1 = mu + (3 * e1 / 2 - 27 * e1 ** 3 / 32) * Math.sin(2 * mu)
        + (21 * e1 ** 2 / 16 - 55 * e1 ** 4 / 32) * Math.sin(4 * mu)
        + (151 * e1 ** 3 / 96) * Math.sin(6 * mu)
        + (1097 * e1 ** 4 / 512) * Math.sin(8 * mu);
      const sin1 = Math.sin(phi1);
      const cos1 = Math.cos(phi1);
      const c1 = ep2 * cos1 ** 2;
      const t1 = Math.tan(phi1) ** 2;
      const n1 = a / Math.sqrt(1 - e2 * sin1 ** 2);
      const r1 = a * (1 - e2) / (1 - e2 * sin1 ** 2) ** 1.5;
      const d = (x - fe) / (n1 * k0);
      const lat = phi1 - (n1 * Math.tan(phi1) / r1) * (d ** 2 / 2
        - (5 + 3 * t1 + 10 * c1 - 4 * c1 ** 2 - 9 * ep2) * d ** 4 / 24
        + (61 + 90 * t1 + 298 * c1 + 45 * t1 ** 2 - 252 * ep2 - 3 * c1 ** 2) * d ** 6 / 720);
      const lng = lon0 + (d - (1 + 2 * t1 + c1) * d ** 3 / 6
        + (5 - 2 * c1 + 28 * t1 - 3 * c1 ** 2 + 8 * ep2 + 24 * t1 ** 2) * d ** 5 / 120) / cos1;
      return [lng * 180 / Math.PI, lat * 180 / Math.PI];
    }

    // [x, y] in the file's CRS → [lng, lat], for the CRSs drone software exports here; null when unknown.
    function projector(geoKeys) {
      const projected = geoKeys.ProjectedCSTypeGeoKey;
      if (!projected && geoKeys.GTModelTypeGeoKey === 2) return (x, y) => [x, y]; // geographic (degrees)
      if ([3857, 3785, 900913].includes(projected)) {
        return (x, y) => [x / WGS84.a * 180 / Math.PI, Math.atan(Math.sinh(y / WGS84.a)) * 180 / Math.PI];
      }
      const rad = degrees => degrees * Math.PI / 180;
      if (projected >= 32601 && projected <= 32760 && projected % 100 >= 1 && projected % 100 <= 60) {
        const zone = projected % 100; // UTM / WGS 84, north (326xx) or south (327xx)
        const params = { ...WGS84, lon0: rad(zone * 6 - 183), k0: 0.9996, fe: 500000, fn: projected >= 32701 ? 10000000 : 0 };
        return (x, y) => inverseTransverseMercator(x, y, params);
      }
      // PRS92 / Philippines zones I–V (3121–3125) and Luzon 1911 (25391–25395): Clarke 1866.
      const philippineZone = projected >= 3121 && projected <= 3125 ? projected - 3120
        : (projected >= 25391 && projected <= 25395 ? projected - 25390 : 0);
      if (philippineZone) {
        const params = { ...CLARKE_1866, lon0: rad(115 + philippineZone * 2), k0: 0.99995, fe: 500000, fn: 0 };
        return (x, y) => inverseTransverseMercator(x, y, params);
      }
      return null;
    }

    // WGS84 box of one GeoTIFF, or null when it can't be told from the header.
    async function readFootprint(file) {
      const tiff = await window.GeoTIFF.fromBlob(file);
      const image = await tiff.getImage();
      const toLngLat = projector(image.getGeoKeys() || {});
      if (!toLngLat) return null;
      const [minX, minY, maxX, maxY] = image.getBoundingBox();
      const corners = [[minX, minY], [minX, maxY], [maxX, minY], [maxX, maxY]].map(([x, y]) => toLngLat(x, y));
      const lngs = corners.map(corner => corner[0]);
      const lats = corners.map(corner => corner[1]);
      const box = { west: Math.min(...lngs), south: Math.min(...lats), east: Math.max(...lngs), north: Math.max(...lats) };
      return Object.values(box).every(Number.isFinite) && Math.abs(box.south) <= 90 && Math.abs(box.west) <= 180 ? box : null;
    }

    // A drone photo's position from the camera data near the start of the file (DJI XMP), as a small box;
    // null for photos without it (e.g. pictures with a world file: the server checks those).
    async function readPhotoFootprint(file) {
      const head = new TextDecoder('latin1').decode(await file.slice(0, 512 * 1024).arrayBuffer());
      const value = key => {
        const match = head.match(new RegExp(`drone-dji:${key}="\\s*([-+]?[0-9.]+)"`))
          || head.match(new RegExp(`<drone-dji:${key}>\\s*([-+]?[0-9.]+)\\s*<`));
        return match ? Number(match[1]) : null;
      };
      const lat = value('GpsLatitude') ?? value('Latitude');
      const lng = value('GpsLongitude') ?? value('Longitude');
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || (lat === 0 && lng === 0) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
      const halfLat = 50 / 110574; // a photo covers a few dozen metres around the camera
      const halfLng = 50 / (111320 * Math.cos(lat * Math.PI / 180));
      return { west: lng - halfLng, south: lat - halfLat, east: lng + halfLng, north: lat + halfLat };
    }

    function gapMetres(a, b) {
      const lngGap = Math.max(0, a.west - b.east, b.west - a.east);
      const latGap = Math.max(0, a.south - b.north, b.south - a.north);
      const latitude = (a.south + a.north + b.south + b.north) / 4 * Math.PI / 180;
      return Math.hypot(lngGap * 111320 * Math.cos(latitude), latGap * 110574);
    }

    // Files within MAX_GAP_M of each other (directly or through other files) form one place; largest first.
    function groupByPlace(footprints) {
      const parent = footprints.map((_, index) => index);
      const find = index => (parent[index] === index ? index : (parent[index] = find(parent[index])));
      footprints.forEach((first, i) => footprints.slice(i + 1).forEach((second, offset) => {
        if (gapMetres(first.box, second.box) <= MAX_GAP_M) parent[find(i)] = find(i + 1 + offset);
      }));
      const groups = new Map();
      footprints.forEach((footprint, index) => {
        const root = find(index);
        groups.set(root, [...(groups.get(root) || []), footprint]);
      });
      return [...groups.values()].sort((a, b) => b.length - a.length);
    }

    function unionBox(footprints) {
      return {
        west: Math.min(...footprints.map(item => item.box.west)),
        south: Math.min(...footprints.map(item => item.box.south)),
        east: Math.max(...footprints.map(item => item.box.east)),
        north: Math.max(...footprints.map(item => item.box.north)),
      };
    }

    function farApartMessage(groups) {
      const names = group => group.slice(0, 3).map(item => item.name).join(', ')
        + (group.length > 3 ? ` and ${group.length - 3} more` : '');
      const kilometres = gapMetres(unionBox(groups[0]), unionBox(groups[1])) / 1000;
      const distance = kilometres >= 1 ? `${kilometres.toFixed(1)} km` : `${Math.round(kilometres * 1000)} m`;
      const places = groups.map((group, index) => `place ${index + 1}: ${names(group)}`).join('; ');
      return `These files come from ${groups.length} places about ${distance} apart (${places}). `
        + 'One upload becomes one image: remove the files of the other place (× next to each file) and upload them separately.';
    }

    async function checkSamePlace(files) {
      const run = ++placeCheckRun;
      const hadNote = ['far', 'checking'].includes(placeCheck.state);
      const placeable = files.filter(file => fileKind(file.name) !== 'sidecar');
      if (placeable.length < 2 || (!window.GeoTIFF && kindCount(files, 'geotiff'))) {
        placeCheck = { state: 'idle', message: '' };
        if (hadNote) showMessage('');
        updateSubmitState();
        return;
      }

      placeCheck = { state: 'checking', message: '' };
      showMessage('Checking that all files come from one place…');
      updateSubmitState();
      const footprints = [];
      for (const [index, file] of files.entries()) {
        const kind = fileKind(file.name);
        if (kind === 'sidecar') continue;
        let box = null;
        try {
          box = kind === 'picture' ? await readPhotoFootprint(file) : await readFootprint(file);
        } catch {
          box = null; // unreadable header: the server reports it after upload
        }
        if (run !== placeCheckRun) return; // a newer selection replaced this one
        if (box) footprints.push({ index, name: file.name, box });
      }

      const groups = groupByPlace(footprints);
      placeCheck = groups.length > 1
        ? {
          state: 'far',
          message: farApartMessage(groups),
          places: new Map(groups.flatMap((group, place) => group.map(item => [item.index, place + 1]))),
        }
        : { state: 'ok', message: '' };
      showMessage(placeCheck.message, placeCheck.state === 'far');
      updateSubmitState();
      renderSelection();
    }

    let sizeNoteShown = false;

    function updateSubmitState() {
      // A file over the limit is named on the page right away (not only in the disabled button's tooltip),
      // and the note goes again once those files are removed.
      if (!uploading) {
        const tooBig = oversizedFiles();
        if (tooBig) {
          showMessage(tooBig, true);
          sizeNoteShown = true;
        } else if (sizeNoteShown) {
          sizeNoteShown = false;
          showMessage(placeCheck.state === 'far' ? placeCheck.message : '', placeCheck.state === 'far');
        }
      }
      const blocker = uploadBlocker();
      submit.disabled = uploading || blocker !== null;
      submit.classList.toggle('is-saving', uploading);
      submit.setAttribute('aria-busy', uploading ? 'true' : 'false');
      submit.innerHTML = uploading ? '<span class="drone-spinner" aria-hidden="true"></span> Uploading…' : SUBMIT_IDLE_HTML;
      submit.title = uploading ? '' : (blocker || '');
    }

    // ---- File selection and drag & drop ------------------------------------------------------
    let autoName = ''; // survey name filled in from the first file, cleared again with the selection

    function describeSelection() {
      const files = [...(fileInput.files || [])];
      checkSamePlace(files); // updates the Upload button and the place tags when done
      if (!files.length) {
        if (autoName && nameInput.value === autoName) nameInput.value = '';
        autoName = '';
      } else if (!nameInput.value.trim()) {
        nameInput.value = autoName = files[0].name.replace(/\.(tiff?|jpe?g|png)$/i, '').slice(0, 120);
      }
      renderSelection();
    }

    // The selection lives here: picking or dropping files again adds to it (a plain <input type=file>
    // would replace it), and × / Clear take files out.
    let chosenFiles = [];
    const fileKey = file => `${file.name}|${file.size}|${file.lastModified}`;

    function addFiles(files) {
      const known = new Set(chosenFiles.map(fileKey));
      const added = files.filter(file => !known.has(fileKey(file)) && known.add(fileKey(file)));
      setFiles([...chosenFiles, ...added]);
      const skipped = files.length - added.length;
      if (skipped) {
        window.AppActions?.notify(skipped === 1 ? 'That file is already in the list.' : `${skipped} files were already in the list.`, 'success', { icon: 'bi-info-circle-fill' });
      }
    }

    // Set the chosen files (an <input type=file> list can only be swapped whole).
    function setFiles(files) {
      chosenFiles = files;
      const transfer = new DataTransfer();
      files.forEach(file => transfer.items.add(file));
      fileInput.files = transfer.files;
      describeSelection();
    }

    // Selected files with a remove button each, a Clear button, and (files far apart) the place of each file.
    function renderSelection() {
      const files = [...(fileInput.files || [])];
      selected.replaceChildren();
      if (!files.length) return;

      const total = files.reduce((sum, file) => sum + file.size, 0);
      const photos = kindCount(files, 'picture');
      const head = element('div', 'drone-files-head');
      head.append(element('span', '', photos
        ? `${photos === 1 ? '1 photo' : `${photos} photos`}${files.length > photos ? ` + ${files.length - photos} location file${files.length - photos === 1 ? '' : 's'}` : ''} selected (${formatBytes(total)})${photos > 1 ? ', placed into one image' : ''}`
        : (files.length === 1
          ? `1 file selected (${formatBytes(total)})`
          : `${files.length} files selected (${formatBytes(total)}), stitched into one mosaic`)));
      const clear = element('button', 'drone-btn drone-files-clear');
      clear.type = 'button';
      clear.disabled = uploading;
      clear.innerHTML = '<i class="bi bi-x-lg"></i> Clear';
      clear.addEventListener('click', () => {
        setFiles([]);
      });
      head.append(clear);
      selected.append(head);
      if (photos) {
        // Photos are placed one by one, not stitched: say so before the user uploads.
        const note = element('p', 'drone-files-note');
        note.innerHTML = '<i class="bi bi-info-circle" aria-hidden="true"></i> ';
        note.append('Each photo is placed on the map from its own GPS and camera data, taken straight down. '
          + 'They are not stitched, so neighbouring photos can be a few metres apart. '
          + 'For the most accurate map, upload a stitched orthomosaic (GeoTIFF) instead.');
        selected.append(note);
      }
      const problem = batchProblem(files);
      if (problem) selected.append(element('p', 'drone-files-note is-error', problem));

      const listNode = element('ul', 'drone-file-list');
      files.forEach((file, index) => {
        const item = element('li');
        item.append(element('span', 'drone-file-name', file.name));
        const place = placeCheck.places?.get(index);
        if (place) {
          const tag = element('span', `drone-file-place place-${place}`, `Place ${place}`);
          tag.title = place === 1 ? 'Most of the files are here' : 'Far from the other files';
          item.append(tag);
        }
        item.append(element('span', 'drone-file-size', formatBytes(file.size)));
        const remove = element('button', 'drone-file-remove');
        remove.type = 'button';
        remove.disabled = uploading;
        remove.title = `Remove ${file.name}`;
        remove.setAttribute('aria-label', `Remove ${file.name}`);
        remove.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
        remove.addEventListener('click', () => setFiles(files.filter((_, other) => other !== index)));
        item.append(remove);
        listNode.append(item);
      });
      selected.append(listNode);
    }

    fileInput.addEventListener('change', () => {
      if (uploading) {
        setFiles(chosenFiles); // keep the batch being uploaded
        return;
      }
      addFiles([...(fileInput.files || [])]);
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
      const files = [...(event.dataTransfer?.files || [])].filter(file => ACCEPTED_FILE.test(file.name));
      if (!files.length) {
        showMessage('Only GeoTIFFs (.tif, .tiff) or drone photos (.jpg, .png) can be uploaded.', true);
        return;
      }
      if (!uploading) addFiles(files);
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

    // ---- Leaving with chosen files or a running upload ------------------------------------------
    // Browsers forget chosen files when the page goes, and leaving stops an upload. Links, Logout, F5 /
    // Ctrl+R / Cmd+R and phone pull-to-refresh ask first in our modal (like the review editor). The browser's
    // reload button, closing the tab or typing another address can't be caught by a page: those only get
    // the browser's own "Leave site?" prompt (beforeunload below), whose text browsers don't let pages set.
    let leaving = false;
    let asking = false;
    const hasWorkToLose = () => !leaving && (uploading || chosenFiles.length > 0);

    // Resolves true (and stops the browser prompt) when the user chooses to leave or reload.
    async function askToLeave(reload = false) {
      if (asking) return false;
      const count = chosenFiles.length;
      const them = count === 1 ? 'it' : 'them';
      const going = reload ? 'reload the page' : 'leave';
      asking = true;
      const confirmed = await window.AppActions.confirm(uploading
        ? {
          title: 'Stop the upload?',
          message: `The upload is still running and stops if you ${going}. To continue it later, choose the same files again and click Upload.`,
          confirmLabel: reload ? 'Reload and stop upload' : 'Leave and stop upload',
          cancelLabel: reload ? 'Keep uploading' : 'Stay on this page',
          tone: 'danger',
          icon: 'bi-exclamation-triangle',
        }
        : {
          title: reload ? 'Reload without uploading?' : 'Leave without uploading?',
          message: `You chose ${count === 1 ? '1 file' : `${count} files`} but did not upload ${them}. `
            + `If you ${going}, you will need to choose ${them} again.`,
          confirmLabel: reload ? 'Reload' : 'Leave',
          cancelLabel: reload ? 'Keep my files' : 'Stay on this page',
          tone: 'danger',
          icon: 'bi-exclamation-triangle',
        });
      asking = false;
      if (confirmed) leaving = true;
      return Boolean(confirmed);
    }

    async function confirmLeave(leave) {
      if (await askToLeave()) leave();
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
      confirmLeave(() => { window.location.href = url.href; });
    }, true);

    document.addEventListener('submit', event => {
      const target = event.target;
      if (!(target instanceof HTMLFormElement) || !/\/logout$/.test(new URL(target.action, window.location.href).pathname)) return;
      if (!hasWorkToLose()) return;
      event.preventDefault();
      event.stopPropagation();
      confirmLeave(() => target.submit());
    }, true);

    window.addEventListener('beforeunload', event => {
      if (!hasWorkToLose()) return;
      event.preventDefault();
      event.returnValue = '';
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!fileInput.files?.length) {
        showMessage('Choose GeoTIFF files or drone photos first.', true);
        return;
      }
      const problem = batchProblem([...fileInput.files]);
      if (problem) {
        showMessage(problem, true);
        return;
      }
      const tooBig = oversizedFiles();
      if (tooBig) {
        showMessage(tooBig, true);
        return;
      }
      if (placeCheck.state === 'far') {
        showMessage(placeCheck.message, true);
        return;
      }

      // Confirmation → Processing (button label + progress bar) → Success or Error (message below the form).
      const count = fileInput.files.length;
      const photoCount = kindCount([...fileInput.files], 'picture');
      const confirmed = await window.AppActions.confirm({
        title: 'Upload and process?',
        message: photoCount
          ? `${photoCount === 1 ? 'The photo is' : `${photoCount} photos are`} uploaded, placed on the map from their GPS and camera data${photoCount > 1 ? ', combined into one image' : ''} and checked by the AI.`
          : (count === 1
            ? `"${fileInput.files[0].name}" is uploaded, prepared for review and checked by the AI.`
            : `${count} GeoTIFF files are uploaded, stitched into one mosaic and checked by the AI.`),
        confirmLabel: 'Upload',
      });
      if (!confirmed || uploading || uploadBlocker()) return;

      uploading = true;
      updateSubmitState();
      renderSelection(); // remove/Clear buttons are disabled while the files upload
      setProgress(0);
      showMessage('Uploading… keep this page open until the upload finishes.');
      try {
        const payload = await upload([...fileInput.files], nameInput.value.trim());
        form.reset();
        chosenFiles = [];
        autoName = '';
        placeCheck = { state: 'idle', message: '' };
        highlightId = payload.id;
        showMessage('Upload complete. The server is preparing a preview and running AI mangrove detection. You can leave this page; come back when it says "Ready for your review".');
        await loadLayers();
      } catch (error) {
        showMessage(error.message, true);
        loadLayers().catch(() => {});
      } finally {
        uploading = false;
        updateSubmitState(); // the form was reset on success, so this disables it again
        renderSelection(); // empty after a successful upload; buttons usable again after a failed one
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
    // Genus classification: its own job, run after the mangrove areas are ready.
    const isClassifying = layer => ['queued', 'processing'].includes(layer.classification_status);

    // A tiled layer: where its submitted delineation stands with the experts.
    function readyStage(layer) {
      if (isSegmenting(layer)) {
        return { step: 4, busy: true, badge: 'AI running', tone: 'busy', text: 'The AI is detecting mangroves…' };
      }
      if (isClassifying(layer)) {
        return { step: 4, busy: true, badge: 'AI running', tone: 'busy', text: 'The AI is detecting the genus…' };
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
            : { step: 3, waiting: true, badge: 'Ready for your review', tone: 'review', text: (page.needs_expert_review
              ? 'Check the mangrove areas (the AI result does most of the work), choose the genus present, then confirm and submit them for expert review.'
              : 'Check the mangrove areas (the AI result does most of the work), choose the genus present, then confirm to publish them with the imagery.')
              + (isClassifying(layer) ? ' The AI is still detecting the genus; its suggestion appears in the editor when it is done.' : '') };
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
          + (isClassifying(layer) ? ' Detecting the genus…'
            : (canopyCount > 0 ? ` ${canopyCount.toLocaleString()} canop${canopyCount === 1 ? 'y' : 'ies'} pinpointed.` : ''));
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

    function sourceLabel(layer) {
      if (layer.source_format === 'photos') return layer.source_type === 'batch' ? 'Drone photos placed into one image' : 'Single drone photo';
      return layer.source_type === 'batch' ? 'Mosaic of several GeoTIFFs' : 'Single GeoTIFF';
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
      title.append(element('div', 'drone-item-meta', `${sourceLabel(layer)}${uploaded ? ` · uploaded ${uploaded}` : ''}`));
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
      remove.disabled = Boolean(stage.busy) || isClassifying(layer); // the server refuses deletes while a job uses the files
      remove.title = remove.disabled ? 'Wait until processing finishes before deleting.' : 'Delete this imagery and its files';
      remove.addEventListener('click', async () => {
        // Confirmation → Processing → Success or Error (partials.action-feedback).
        const outcome = await window.AppActions.run({
          confirm: {
            title: 'Delete this record?',
            // The delineation submitted from this imagery is deleted with it.
            message: `"${layer.name}" and its GeoTIFF files${layer.review_state ? `, plus its ${layer.review_state} delineation on the map,` : ''} are permanently deleted. This cannot be undone.`,
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
          successMessage: layer.review_state ? `"${layer.name}" and its delineation were deleted.` : `"${layer.name}" was deleted.`,
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
        empty.append('No drone imagery yet. Upload a GeoTIFF or drone photos to get started.');
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
      title.append(element('div', 'drone-item-meta', `${sourceLabel(layer)}${uploaded ? ` · uploaded ${uploaded}` : ''}`));
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
      if (owned.some(layer => stageOf(layer).busy || isClassifying(layer)) || others.some(layer => otherStage(layer).busy)) {
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
