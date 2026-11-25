{{--
  Genus photo + description cards (App\Support\MangroveGenera), shared by the landing page map,
  the Map/Delineate details panel and the genus picker. Include once per page, before scripts that use it.

  window.GenusInfo:
    chips(genera)              -> [chipsRow, card]: one chip per genus; a click opens its photo and description
    bindInfoButtons(root, card) -> wires [data-genus-info="Name"] buttons inside root to open that card
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

    // The photo + text of one genus, as the children of a card.
    function cardContent(name) {
      const info = lookup(name);
      let photo;
      if (info?.image) {
        photo = el('img', { className: 'genus-info-photo', src: info.image, alt: `${info.genus} sample photo`, decoding: 'async' });
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
      return [photo, body];
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

    function chips(genera) {
      const row = el('div', { className: 'genus-info-chips' });
      const card = emptyCard();
      const buttons = genera.map(name => {
        const info = lookup(name);
        const chip = el('button', { type: 'button', className: 'genus-info-chip', title: 'Show what it looks like' });
        chip.setAttribute('aria-expanded', 'false');
        chip.setAttribute('aria-controls', card.id);
        chip.append(info ? info.genus : name);
        if (info?.local) chip.append(' ', el('span', { className: 'genus-info-local', textContent: `(${info.local})` }));
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

    return { lookup, chips, bindInfoButtons };
  })();
</script>
