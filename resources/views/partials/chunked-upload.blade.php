{{-- Chunked, parallel, resumable upload client for DroneImageryUploadController (Drone Upload, Tile Explorer).
     Files go up in slices (chunk_size from the server), several at a time, each retried on its own. The session id is
     remembered per selection (resumeKey), so choosing the same files again after a dropped connection or a reload
     only sends the slices the server does not have yet.

     const result = await window.ChunkedUpload.upload({
       url, files, fields: { name, purpose, tile_size }, parallel, resumeKey,
       onProgress: ({ loaded, total, speed }) => {}, onResumed: bytes => {},
     });   // result = the complete() JSON; errors are thrown with .status --}}
<script>
  window.ChunkedUpload ??= (() => {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
    // Not worth retrying: signed out, not allowed, session gone, or the server's size limit.
    const FATAL_STATUSES = [401, 403, 404, 413, 419];
    const MAX_ATTEMPTS = 6;

    function formatBytes(bytes) {
      if (bytes >= 1024 ** 3) return `${(bytes / 1024 ** 3).toFixed(2)} GB`;
      if (bytes >= 1024 ** 2) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
      return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    function send(method, url, body = null, { contentType = null, onProgress = null } = {}) {
      return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open(method, url);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        if (contentType) request.setRequestHeader('Content-Type', contentType);
        if (onProgress) {
          request.upload.addEventListener('progress', event => {
            if (event.lengthComputable) onProgress(event.loaded);
          });
        }
        const fail = (text, status = 0) => reject(Object.assign(new Error(text), { status }));
        request.addEventListener('load', () => {
          let payload;
          try {
            payload = JSON.parse(request.responseText);
          } catch {
            // Usually a PHP warning or HTML error page (body limits, timeout, expired session).
            const hint = request.status === 413 ? ' The server refused the request size; ask the admin to raise its body size limit.'
              : (request.status === 419 ? ' Your session expired; reload the page and try again.' : '');
            fail(`The server returned an invalid response while uploading (HTTP ${request.status}).${hint}`, request.status);
            return;
          }
          if (request.status < 200 || request.status >= 300) {
            const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            fail(validationError || payload.message || 'Unable to upload the GeoTIFF files.', request.status);
            return;
          }
          resolve(payload);
        });
        request.addEventListener('error', () => fail('Network error while uploading.'));
        request.addEventListener('abort', () => fail('The upload was cancelled.'));
        request.send(body);
      });
    }

    function readResume(key) {
      try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
    }
    function writeResume(key, value) {
      try { value ? localStorage.setItem(key, JSON.stringify(value)) : localStorage.removeItem(key); } catch {}
    }

    async function openSession({ url, files, fields, resumeKey }, signature) {
      const saved = readResume(resumeKey);
      if (saved?.signature === signature) {
        try {
          const status = await send('GET', `${url}/${saved.id}`);
          return { id: saved.id, chunkSize: status.chunk_size, received: status.received, resumed: true };
        } catch {
          writeResume(resumeKey, null); // expired or removed: start over
        }
      }
      const created = await send('POST', url, JSON.stringify({
        ...fields,
        files: files.map(file => ({ name: file.name, size: file.size })),
      }), { contentType: 'application/json' });
      writeResume(resumeKey, { signature, id: created.upload_id });
      return { id: created.upload_id, chunkSize: created.chunk_size, received: files.map(() => []), resumed: false };
    }

    async function upload(options) {
      const { url, files, fields = {}, parallel = 4, resumeKey = 'chunkedUpload.resume', onProgress = () => {}, onResumed = () => {} } = options;
      const signature = JSON.stringify([fields, files.map(file => [file.name, file.size, file.lastModified])]);
      const session = await openSession({ url, files, fields, resumeKey }, signature);
      const sessionUrl = `${url}/${session.id}`;

      const total = files.reduce((sum, file) => sum + file.size, 0);
      let confirmed = 0;
      const tasks = [];
      files.forEach((file, fileIndex) => {
        const have = new Set(session.received[fileIndex] || []);
        for (let chunk = 0; chunk * session.chunkSize < file.size; chunk++) {
          const start = chunk * session.chunkSize;
          const end = Math.min(file.size, start + session.chunkSize);
          if (have.has(chunk)) confirmed += end - start;
          else tasks.push({ file, fileIndex, chunk, start, end });
        }
      });

      const inFlight = new Map();
      const startedAt = performance.now();
      const startBytes = confirmed;
      function report() {
        let loaded = confirmed;
        inFlight.forEach(bytes => { loaded += bytes; });
        const seconds = (performance.now() - startedAt) / 1000;
        onProgress({ loaded, total, speed: seconds > 2 ? (loaded - startBytes) / seconds : 0 });
      }
      report();
      if (session.resumed && confirmed > 0) onResumed(confirmed);

      let next = 0;
      let failure = null;
      async function sendChunk(task) {
        const chunkUrl = `${sessionUrl}/files/${task.fileIndex}/chunks/${task.chunk}`;
        for (let attempt = 1; ; attempt++) {
          try {
            await send('PUT', chunkUrl, task.file.slice(task.start, task.end), {
              contentType: 'application/octet-stream',
              onProgress: bytes => { inFlight.set(task, bytes); report(); },
            });
            inFlight.delete(task);
            confirmed += task.end - task.start;
            report();
            return;
          } catch (error) {
            inFlight.delete(task);
            report();
            if (failure || FATAL_STATUSES.includes(error.status) || attempt >= MAX_ATTEMPTS) throw error;
            await wait(Math.min(30000, 1000 * 2 ** attempt)); // 2 s, 4 s, 8 s … rides out short drop-outs
          }
        }
      }
      async function worker() {
        while (!failure && next < tasks.length) {
          const task = tasks[next++];
          try {
            await sendChunk(task);
          } catch (error) {
            failure ??= error;
          }
        }
      }
      await Promise.all(Array.from({ length: Math.min(parallel || 4, tasks.length) }, worker));
      if (failure) {
        if (failure.status === 404) writeResume(resumeKey, null);
        else failure.message += ` ${formatBytes(confirmed)} of ${formatBytes(total)} is saved; choose the same file${files.length === 1 ? '' : 's'} and upload again to continue from there.`;
        throw failure;
      }

      const result = await send('POST', `${sessionUrl}/complete`);
      writeResume(resumeKey, null);
      return result;
    }

    return { upload, formatBytes };
  })();
</script>
