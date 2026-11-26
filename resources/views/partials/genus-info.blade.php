{{--
  Genus photo + description cards (App\Support\MangroveGenera), shared by the landing page map,
  the Map/Delineate details panel and the genus picker. Include once per page, before scripts that use it.

  window.GenusInfo:
    chips(genera)              -> [chipsRow, card]: one chip per genus; a click opens its photo and description
    bindInfoButtons(root, card) -> wires [data-genus-info="Name"] buttons inside root to open that card
    openViewer(genus, index)   -> large photo viewer: the genus photo, then each species (MangroveGenera `species`).
                                  It opens over the page's visible .map-wrap (the map), else the whole window.

  Each card lists the genus's species as thumbnails; the photo and the thumbnails open the viewer.
--}}
<style>
  .genus-info-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .genus-info-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--bg-edf7f2, #edf7f2);
    border: 1px solid var(--bd-bce6cf, #bce6cf);
    color: var(--fg-147a46, #147a46);
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .genus-info-chip .genus-info-local {
    font-weight: 600;
    opacity: .8;
  }

  .genus-info-chip:hover {
    border-color: #1e9e62;
  }

  .genus-info-chip:focus-visible,
  .genus-info-btn:focus-visible {
    outline: 2px solid #1e9e62;
    outline-offset: 2px;
  }

  .genus-info-chip[aria-expanded="true"] {
    background: #1e9e62;
    border-color: #1e9e62;
    color: #ffffff;
  }

  .genus-info-share {
    margin-left: 2px;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--bg-ffffff, #ffffff);
    color: var(--fg-147a46, #147a46);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
  }

  .genus-info-chip[aria-expanded="true"] .genus-info-share {
    background: rgba(255, 255, 255, .22);
    color: #ffffff;
  }

  .genus-info-chip i {
    font-size: 10px;
    transition: transform .15s ease;
  }

  .genus-info-chip[aria-expanded="true"] i {
    transform: rotate(180deg);
  }

  .genus-info-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: var(--fg-6a8c79, #6a8c79);
    font-size: 13px;
    cursor: pointer;
  }

  .genus-info-btn:hover,
  .genus-info-btn[aria-expanded="true"] {
    color: var(--fg-1e9e62, #1e9e62);
    background: var(--bg-edf7f2, #edf7f2);
  }

  .genus-info-card {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-top: 8px;
    padding: 8px;
    border-radius: 10px;
    background: var(--bg-f5f7f6, #f5f7f6);
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    text-align: left;
  }

  .genus-info-card[hidden] {
    display: none;
  }

  .genus-info-photo {
    flex: 0 0 88px;
    width: 88px;
    height: 88px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--bg-edf7f2, #edf7f2);
  }

  .genus-info-photo.is-blank {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--fg-1e9e62, #1e9e62);
    font-size: 24px;
  }

  .genus-info-body {
    min-width: 0;
    flex: 1;
  }

  .genus-info-name {
    font-size: 13px;
    font-weight: 700;
    color: var(--fg-1a2e1a, #1a2e1a);
  }

  .genus-info-name em {
    font-style: normal;
    font-weight: 600;
    color: var(--fg-6a8c79, #6a8c79);
  }

  .genus-info-about {
    margin-top: 2px;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.4;
    color: var(--fg-3a5a3a, #3a5a3a);
  }

  .genus-info-credit {
    display: inline-block;
    margin-top: 3px;
    font-size: 10px;
    font-weight: 400;
    color: var(--fg-6a8a6a, #6a8a6a);
    text-decoration: none;
  }

  .genus-info-credit:hover {
    text-decoration: underline;
  }

  .genus-info-card {
    flex-wrap: wrap;
  }

  .genus-info-photo-btn {
    position: relative;
    flex: 0 0 88px;
    padding: 0;
    border: none;
    border-radius: 8px;
    background: none;
    cursor: zoom-in;
  }

  .genus-info-photo-btn .genus-info-photo {
    display: block;
  }

  .genus-info-zoom {
    position: absolute;
    right: 4px;
    bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(0, 0, 0, .55);
    color: #ffffff;
    font-size: 11px;
    pointer-events: none;
  }

  .genus-info-species {
    flex: 1 1 100%;
    min-width: 0;
  }

  .genus-info-species-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--fg-3a5a3a, #3a5a3a);
    margin-bottom: 5px;
  }

  .genus-info-species-title span {
    font-weight: 500;
    color: var(--fg-6a8c79, #6a8c79);
  }

  .genus-info-species-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(62px, 1fr));
    gap: 6px;
  }

  .genus-info-species-item {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 3px;
    min-width: 0;
    padding: 3px;
    border: 1px solid var(--bd-e0e8e0, #e0e8e0);
    border-radius: 8px;
    background: var(--bg-ffffff, #ffffff);
    font: inherit;
    cursor: zoom-in;
  }

  .genus-info-species-item:hover,
  .genus-info-photo-btn:hover .genus-info-photo {
    border-color: #1e9e62;
    box-shadow: 0 0 0 2px rgba(30, 158, 98, .25);
  }

  .genus-info-species-item:focus-visible,
  .genus-info-photo-btn:focus-visible {
    outline: 2px solid #1e9e62;
    outline-offset: 2px;
  }

  .genus-info-species-item img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 5px;
    background: var(--bg-edf7f2, #edf7f2);
  }

  .genus-info-species-item span {
    font-size: 10.5px;
    font-style: italic;
    font-weight: 600;
    line-height: 1.2;
    color: var(--fg-1a2e1a, #1a2e1a);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Large photo viewer: over the map when the page has one, else the whole window. Its colours stay dark in
     both themes (a photo viewer), so they are custom properties the theme script leaves alone. */
  .genus-viewer {
    --gv-bg: #0b1711;
    --gv-panel: rgba(255, 255, 255, .08);
    --gv-line: rgba(255, 255, 255, .18);
    --gv-text: #f2f7f4;
    --gv-muted: #a9c2b4;
    --gv-accent: #3ccf8e;
    --gv-on-accent: #06281a;
    position: fixed;
    inset: 0;
    z-index: 2500;
    display: flex;
    flex-direction: column;
    background: var(--gv-bg);
    color: var(--gv-text);
    font-family: 'Manrope', sans-serif;
  }

  .genus-viewer.in-map {
    position: absolute;
    z-index: 1500;
  }

  .genus-viewer[hidden] {
    display: none;
  }

  .genus-viewer-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px 10px 16px;
    border-bottom: 1px solid var(--gv-line);
  }

  .genus-viewer-head h2 {
    flex: 1;
    min-width: 0;
    margin: 0;
    font-size: 15px;
    font-weight: 800;
  }

  .genus-viewer-head h2 span {
    font-weight: 600;
    color: var(--gv-muted);
  }

  .genus-viewer-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    border: 1px solid var(--gv-line);
    border-radius: 50%;
    background: var(--gv-panel);
    color: var(--gv-text);
    font-size: 18px;
    cursor: pointer;
  }

  .genus-viewer-icon:hover {
    border-color: var(--gv-accent);
    color: var(--gv-accent);
  }

  .genus-viewer-icon:focus-visible,
  .genus-viewer-thumb:focus-visible {
    outline: 2px solid var(--gv-accent);
    outline-offset: 2px;
  }

  .genus-viewer-icon:disabled {
    opacity: .35;
    cursor: default;
  }

  .genus-viewer-stage {
    position: relative;
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
  }

  .genus-viewer-figure {
    flex: 1;
    min-width: 0;
    height: 100%;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .genus-viewer-figure img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .5);
  }

  .genus-viewer-parts {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 0 16px 8px;
  }

  .genus-viewer-parts[hidden] {
    display: none;
  }

  .genus-viewer-part {
    min-width: 76px;
    padding: 6px 14px;
    border: 1px solid var(--gv-line);
    border-radius: 999px;
    background: var(--gv-panel);
    color: var(--gv-text);
    font: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
  }

  .genus-viewer-part:hover {
    border-color: var(--gv-accent);
  }

  .genus-viewer-part:focus-visible {
    outline: 2px solid var(--gv-accent);
    outline-offset: 2px;
  }

  .genus-viewer-part[aria-selected="true"] {
    background: var(--gv-accent);
    border-color: var(--gv-accent);
    color: var(--gv-on-accent);
  }

  .genus-viewer-caption .tag {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 999px;
    background: var(--gv-panel);
    border: 1px solid var(--gv-line);
    color: var(--gv-muted);
    font-size: 11px;
    font-weight: 700;
  }

  .genus-viewer-caption {
    padding: 0 16px 8px;
    text-align: center;
  }

  .genus-viewer-caption .name {
    font-size: 17px;
    font-weight: 800;
    font-style: italic;
  }

  .genus-viewer-caption .name.is-genus {
    font-style: normal;
  }

  .genus-viewer-caption .local {
    margin-left: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--gv-muted);
  }

  .genus-viewer-caption .note {
    max-width: 640px;
    margin: 3px auto 0;
    font-size: 13px;
    line-height: 1.45;
    color: var(--gv-text);
  }

  .genus-viewer-strip {
    display: flex;
    gap: 8px;
    padding: 8px 16px 6px;
    overflow-x: auto;
    scrollbar-width: thin;
    border-top: 1px solid var(--gv-line);
    justify-content: safe center;
  }

  .genus-viewer-thumb {
    flex: 0 0 86px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
    border: 2px solid transparent;
    border-radius: 9px;
    background: var(--gv-panel);
    color: var(--gv-text);
    font: inherit;
    cursor: pointer;
  }

  .genus-viewer-thumb img {
    width: 100%;
    height: 58px;
    object-fit: cover;
    border-radius: 5px;
  }

  .genus-viewer-thumb span {
    font-size: 11px;
    font-weight: 600;
    font-style: italic;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .genus-viewer-thumb.is-genus span {
    font-style: normal;
  }

  .genus-viewer-thumb[aria-current="true"] {
    border-color: var(--gv-accent);
  }

  .genus-viewer-credit {
    padding: 2px 16px 10px;
    font-size: 10.5px;
    line-height: 1.4;
    text-align: center;
    color: var(--gv-muted);
  }

  .genus-viewer-credit a {
    color: inherit;
  }

  @media (max-width: 640px) {
    .genus-viewer-stage {
      padding: 8px 6px;
      gap: 4px;
    }

    .genus-viewer-icon.nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 1;
      background: rgba(0, 0, 0, .55);
    }

    .genus-viewer-icon.prev {
      left: 10px;
    }

    .genus-viewer-icon.next {
      right: 10px;
    }

    .genus-viewer-thumb {
      flex-basis: 72px;
    }

    .genus-viewer-thumb img {
      height: 48px;
    }
  }
</style>
<script>
  window.GenusInfo = (() => {
    const INFO = @json(\App\Support\MangroveGenera::forClient());
    let cardSeq = 0;

    function el(tag, props = {}) {
      return Object.assign(document.createElement(tag), props);
    }

    function lookup(name) {
      const key = String(name || '').trim().split(/\s+/)[0].toLowerCase();
      const match = Object.keys(INFO).find(genus => genus.toLowerCase() === key);
      return match ? { genus: match, ...INFO[match] } : null;
    }

    const SPECIES_CREDIT = @json(\App\Support\MangroveGenera::SPECIES_CREDIT);

    // "Rhizophora apiculata" -> "R. apiculata"
    function shortName(name) {
      const [genus, ...rest] = String(name).split(/\s+/);
      return rest.length ? `${genus[0]}. ${rest.join(' ')}` : genus;
    }

    // The genus photo first, then each species: what the viewer steps through.
    // Each item has `parts` (Tree / Leaves / Roots, from MangroveGenera::forClient); the viewer shows one at a time.
    function viewerItems(info) {
      const items = [];
      if (info.image) {
        items.push({
          name: info.genus, isGenus: true, local: info.local, image: info.image,
          parts: [{ key: 'tree', label: 'Photo', image: info.image, note: info.about, credit: info.credit, source: info.source }],
        });
      }
      (info.species || []).forEach(species => items.push({
        ...species,
        parts: (species.parts || []).map(part => ({ ...part, credit: part.credit || SPECIES_CREDIT })),
      }));
      return items;
    }

    // ---- Large photo viewer -------------------------------------------------------------------
    // Opens over the map (the page's visible .map-wrap) so the photo is big while the side panel
    // stays where it was; on phones or pages without a map it covers the window.
    const viewer = { root: null, items: [], index: 0, part: 'tree', info: null, returnFocus: null };

    function viewerHost() {
      if (window.matchMedia('(max-width: 720px)').matches) return null;
      return [...document.querySelectorAll('.map-wrap')]
        .find(wrap => wrap.offsetWidth >= 360 && wrap.offsetHeight >= 300) || null;
    }

    // A side panel that floats over the map (e.g. #mapRightPanel) and holds the clicked photo stays
    // visible: the viewer stops at that panel's edge instead of covering it.
    function fitBesidePanel(host, trigger) {
      const style = viewer.root.style;
      style.left = style.right = '';
      if (!host || !trigger) return;
      let panel = trigger.parentElement;
      while (panel && panel !== host && panel !== document.body) {
        const position = getComputedStyle(panel).position;
        if (position === 'absolute' || position === 'fixed') break;
        panel = panel.parentElement;
      }
      if (!panel || panel === host || panel === document.body) return;
      const hostRect = host.getBoundingClientRect();
      const panelRect = panel.getBoundingClientRect();
      const overlapsRight = panelRect.left > hostRect.left + hostRect.width / 2 && panelRect.left < hostRect.right;
      const overlapsLeft = panelRect.right < hostRect.right - hostRect.width / 2 && panelRect.right > hostRect.left;
      if (overlapsRight) style.right = `${Math.round(hostRect.right - panelRect.left)}px`;
      else if (overlapsLeft) style.left = `${Math.round(panelRect.right - hostRect.left)}px`;
    }

    function buildViewer() {
      const root = el('div', { className: 'genus-viewer' });
      root.hidden = true;
      root.setAttribute('role', 'dialog');
      root.setAttribute('aria-modal', 'true');
      root.setAttribute('aria-labelledby', 'genusViewerTitle');
      root.innerHTML = `
        <div class="genus-viewer-head">
          <h2 id="genusViewerTitle"></h2>
          <button type="button" class="genus-viewer-icon" data-gv="close" aria-label="Close photo" title="Close (Esc)"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="genus-viewer-stage">
          <button type="button" class="genus-viewer-icon nav prev" data-gv="prev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
          <figure class="genus-viewer-figure"><img alt=""></figure>
          <button type="button" class="genus-viewer-icon nav next" data-gv="next" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="genus-viewer-parts" role="tablist" aria-label="Part of the plant"></div>
        <div class="genus-viewer-caption" aria-live="polite"></div>
        <div class="genus-viewer-strip" role="group" aria-label="Photos in this genus"></div>
        <div class="genus-viewer-credit"></div>`;
      root.addEventListener('click', event => {
        const action = event.target.closest('[data-gv]')?.dataset.gv;
        if (action === 'close') closeViewer();
        else if (action === 'prev') showItem(viewer.index - 1);
        else if (action === 'next') showItem(viewer.index + 1);
        else if (event.target.closest('[data-gv-index]')) showItem(Number(event.target.closest('[data-gv-index]').dataset.gvIndex));
        else if (event.target.closest('[data-gv-part]')) showItem(viewer.index, event.target.closest('[data-gv-part]').dataset.gvPart);
        else if (event.target === root || event.target.classList.contains('genus-viewer-figure')) closeViewer();
        event.stopPropagation();
      });
      // Keep map gestures (wheel zoom, drag) from reaching Leaflet underneath.
      ['wheel', 'mousedown', 'touchstart', 'dblclick'].forEach(type => root.addEventListener(type, e => e.stopPropagation(), { passive: true }));
      root.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.stopPropagation(); closeViewer(); }
        else if (event.key === 'ArrowLeft') showItem(viewer.index - 1);
        else if (event.key === 'ArrowRight') showItem(viewer.index + 1);
        else if (event.key === 'Tab') {
          const focusable = [...root.querySelectorAll('button:not(:disabled), a[href]')];
          const first = focusable[0], last = focusable[focusable.length - 1];
          if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
          else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
      });
      return root;
    }

    // `partKey` comes from a tab click; stepping to another species keeps the chosen part (Leaves stays on
    // Leaves) when that species has it, so species are easy to compare.
    function showItem(index, partKey = null) {
      const items = viewer.items;
      if (index < 0 || index >= items.length) return;
      if (partKey) viewer.part = partKey;
      viewer.index = index;
      const item = items[index];
      const part = item.parts.find(p => p.key === viewer.part) || item.parts[0];
      const root = viewer.root;
      const img = root.querySelector('.genus-viewer-figure img');
      img.src = part.image;
      img.alt = item.isGenus ? `${item.name} sample photo` : `${item.name}: ${part.label.toLowerCase()}`;

      const tabs = root.querySelector('.genus-viewer-parts');
      tabs.replaceChildren(...item.parts.map(p => {
        const tab = el('button', { type: 'button', className: 'genus-viewer-part', textContent: p.label });
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-selected', p === part ? 'true' : 'false');
        tab.dataset.gvPart = p.key;
        return tab;
      }));
      tabs.hidden = item.parts.length < 2;

      const caption = root.querySelector('.genus-viewer-caption');
      caption.replaceChildren();
      const line = el('div');
      line.append(el('span', { className: `name${item.isGenus ? ' is-genus' : ''}`, textContent: item.isGenus ? `${item.name} (genus)` : item.name }));
      if (item.local) line.append(el('span', { className: 'local', textContent: item.isGenus ? item.local : `Local: ${item.local}` }));
      caption.append(line);
      if (part.note) {
        const note = el('div', { className: 'note', textContent: part.note });
        if (part.genusTypical) note.prepend(el('span', { className: 'tag', textContent: `Typical of ${viewer.info.genus}` }), ' ');
        caption.append(note);
      }

      const credit = root.querySelector('.genus-viewer-credit');
      credit.replaceChildren();
      if (part.source) {
        const link = el('a', { href: part.source, target: '_blank', rel: 'noopener', textContent: part.credit });
        link.dataset.noProgress = '';
        credit.append(link);
      } else {
        credit.textContent = part.credit || '';
      }

      root.querySelector('[data-gv="prev"]').disabled = index === 0;
      root.querySelector('[data-gv="next"]').disabled = index === items.length - 1;
      root.querySelectorAll('.genus-viewer-thumb').forEach((thumb, i) => {
        thumb.setAttribute('aria-current', i === index ? 'true' : 'false');
        if (i === index) thumb.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      });
    }

    function openViewer(name, startAt = 0, trigger = null) {
      const info = lookup(name);
      if (!info) return;
      const items = viewerItems(info);
      if (!items.length) return;
      viewer.root = viewer.root || buildViewer();
      viewer.items = items;
      viewer.info = info;
      viewer.returnFocus = document.activeElement;

      const host = viewerHost();
      viewer.root.classList.toggle('in-map', Boolean(host));
      (host || document.body).appendChild(viewer.root);
      fitBesidePanel(host, trigger);

      const speciesCount = (info.species || []).length;
      const title = viewer.root.querySelector('#genusViewerTitle');
      title.replaceChildren(info.genus);
      if (info.local) title.append(' ', el('span', { textContent: `(${info.local})` }));
      if (speciesCount) title.append(' ', el('span', { textContent: `· ${speciesCount} species in this genus` }));

      const strip = viewer.root.querySelector('.genus-viewer-strip');
      strip.replaceChildren(...items.map((item, i) => {
        const thumb = el('button', { type: 'button', className: `genus-viewer-thumb${item.isGenus ? ' is-genus' : ''}`, title: item.name });
        thumb.dataset.gvIndex = String(i);
        thumb.append(el('img', { src: item.thumb || item.image, alt: '', loading: 'lazy', decoding: 'async' }),
          el('span', { textContent: item.isGenus ? 'Genus photo' : shortName(item.name) }));
        return thumb;
      }));
      strip.hidden = items.length < 2;

      viewer.root.hidden = false;
      viewer.part = 'tree';
      showItem(Math.min(Math.max(startAt, 0), items.length - 1));
      viewer.root.querySelector('[data-gv="close"]').focus();
    }

    function closeViewer() {
      if (!viewer.root || viewer.root.hidden) return;
      viewer.root.hidden = true;
      viewer.returnFocus?.focus?.();
    }

    // The photo + text of one genus, as the children of a card.
    function cardContent(name) {
      const info = lookup(name);
      let photo;
      if (info?.image) {
        photo = el('button', { type: 'button', className: 'genus-info-photo-btn', title: 'View larger' });
        photo.setAttribute('aria-label', `View ${info.genus} photos larger`);
        photo.append(
          el('img', { className: 'genus-info-photo', src: info.image, alt: `${info.genus} sample photo`, decoding: 'async' }),
          Object.assign(el('span', { className: 'genus-info-zoom' }), { innerHTML: '<i class="bi bi-arrows-fullscreen"></i>' }),
        );
        photo.addEventListener('click', event => { event.stopPropagation(); openViewer(info.genus, 0, photo); });
      } else {
        photo = el('div', { className: 'genus-info-photo is-blank' });
        photo.setAttribute('aria-hidden', 'true');
        photo.appendChild(el('i', { className: 'bi bi-tree' }));
      }

      const body = el('div', { className: 'genus-info-body' });
      const title = el('div', { className: 'genus-info-name', textContent: info ? info.genus : name });
      if (info?.local) title.append(' ', el('em', { textContent: `(${info.local})` }));
      body.append(title, el('div', {
        className: 'genus-info-about',
        textContent: info?.about || 'Recorded by the mapper; no description available.',
      }));
      if (info?.credit && info?.source) {
        const credit = el('a', { className: 'genus-info-credit', href: info.source, target: '_blank', rel: 'noopener', textContent: info.credit });
        credit.dataset.noProgress = '';
        body.appendChild(credit);
      }
      const parts = [photo, body];

      const species = info?.species || [];
      if (species.length) {
        const offset = info.image ? 1 : 0; // the viewer lists the genus photo first
        const section = el('div', { className: 'genus-info-species' });
        const heading = el('div', { className: 'genus-info-species-title', textContent: `Species of ${info.genus} ` });
        heading.append(el('span', { textContent: `(${species.length}, all the same genus · tap to enlarge)` }));
        const list = el('div', { className: 'genus-info-species-list' });
        species.forEach((item, i) => {
          const button = el('button', { type: 'button', className: 'genus-info-species-item', title: `${item.name} (${item.local})` });
          button.setAttribute('aria-label', `View ${item.name} photo larger`);
          button.append(el('img', { src: item.thumb || item.image, alt: '', loading: 'lazy', decoding: 'async' }), el('span', { textContent: shortName(item.name) }));
          button.addEventListener('click', event => { event.stopPropagation(); openViewer(info.genus, i + offset, button); });
          list.append(button);
        });
        section.append(heading, list);
        parts.push(section);
      }
      return parts;
    }

    function emptyCard() {
      const card = el('div', { className: 'genus-info-card', id: `genusInfoCard${++cardSeq}` });
      card.hidden = true;
      card.setAttribute('aria-live', 'polite');
      return card;
    }

    // Opens `name` in `card` from `button`; a second click on the open button closes it.
    function toggle(button, card, name, group) {
      const wasOpen = button.getAttribute('aria-expanded') === 'true';
      group.forEach(other => other.setAttribute('aria-expanded', 'false'));
      if (wasOpen) {
        card.hidden = true;
        return;
      }
      button.setAttribute('aria-expanded', 'true');
      card.replaceChildren(...cardContent(name));
      card.hidden = false;
    }

    // options.shares: { Genus: percent } (the AI canopy share of a drone survey), shown inside each chip.
    function chips(genera, options = {}) {
      const shares = options.shares || {};
      const shareOf = name => {
        const key = Object.keys(shares).find(genus => genus.toLowerCase() === String(lookup(name)?.genus || name).toLowerCase());
        return key ? Number(shares[key]) : null;
      };
      const row = el('div', { className: 'genus-info-chips' });
      const card = emptyCard();
      const buttons = genera.map(name => {
        const info = lookup(name);
        const share = shareOf(name);
        const chip = el('button', { type: 'button', className: 'genus-info-chip', title: 'Show what it looks like' });
        chip.setAttribute('aria-expanded', 'false');
        chip.setAttribute('aria-controls', card.id);
        chip.append(info ? info.genus : name);
        if (info?.local) chip.append(' ', el('span', { className: 'genus-info-local', textContent: `(${info.local})` }));
        if (share != null) {
          const text = `${share.toLocaleString(undefined, { maximumFractionDigits: share < 10 ? 1 : 0 })}%`;
          chip.append(el('span', { className: 'genus-info-share', textContent: text }));
          chip.title = `${text} of the canopy (AI, drone survey). Show what it looks like`;
        }
        chip.appendChild(el('i', { className: 'bi bi-chevron-down' }));
        return chip;
      });
      buttons.forEach((chip, i) => chip.addEventListener('click', () => toggle(chip, card, genera[i], buttons)));
      row.append(...buttons);
      return [row, card];
    }

    function bindInfoButtons(root, card) {
      if (!root || !card) return;
      if (!card.id) card.id = `genusInfoCard${++cardSeq}`;
      card.classList.add('genus-info-card');
      card.setAttribute('aria-live', 'polite');
      const buttons = [...root.querySelectorAll('[data-genus-info]')];
      buttons.forEach(button => {
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', card.id);
        button.addEventListener('click', event => {
          event.stopPropagation();
          toggle(button, card, button.dataset.genusInfo, buttons);
        });
      });
    }

    return { lookup, chips, bindInfoButtons, openViewer, closeViewer };
  })();
</script>
