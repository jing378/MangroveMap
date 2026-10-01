  <main class="page">
    <h1 class="page-title">Image Delineation</h1>
    <p class="page-sub">Upload one or more mangrove images for U-Net canopy segmentation. Images are processed
      sequentially.</p>

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
            <label class="choose-btn"
              onclick="event.stopPropagation(); document.getElementById('batchFileInput').click()">
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
              <button class="toggle-btn active" id="btnOriginal" onclick="setPreview('original')"
                disabled>Original</button>
              <button class="toggle-btn" id="btnOverlay" onclick="setPreview('overlay')" disabled><i
                  class="bi bi-bounding-box"></i> Delineation</button>
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
            <div class="rs-row"><span class="rs-key">Mangrove cover</span><span class="rs-val" id="rsCoverage">—</span>
            </div>
            <div class="conf-row-stat">
              <span style="font-size:12px;color:#5a7a5a;width:120px;flex-shrink:0">Mangrove</span>
              <div class="conf-track">
                <div class="conf-fill" id="rsManBar" style="background:#1e9e62;width:0%"></div>
              </div>
              <span class="conf-pct" id="rsManPct">0%</span>
            </div>
            <div class="conf-row-stat">
              <span style="font-size:12px;color:#5a7a5a;width:120px;flex-shrink:0">Background</span>
              <div class="conf-track">
                <div class="conf-fill" id="rsBgBar" style="background:#c0c8b8;width:0%"></div>
              </div>
              <span class="conf-pct" id="rsBgPct">0%</span>
            </div>
            <div class="rs-row"><span class="rs-key">Mean confidence</span><span class="rs-val" id="rsConf">—</span>
            </div>
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
        <div class="tbl-responsive">
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
                <tr data-id="{{ $a->id }}" data-original="{{ $a->image_url }}"
                  data-overlay="{{ $a->results['overlay_url'] ?? '' }}" data-label="{{ $a->species_detected ?? '—' }}"
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
                      <button class="view-btn del-btn" onclick="deleteHistoryRow(this.closest('tr'))"
                        title="Delete this analysis">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </main>
