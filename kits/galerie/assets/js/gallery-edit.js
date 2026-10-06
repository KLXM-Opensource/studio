/*
 * Kit „galerie“ – Bilder und Videos für die Redaktion (nur angemeldet; Besucher bekommen weder Markup noch Skript).
 *
 *  [data-gmm]   Detailseite eines Werks, einer Ausstellung oder eines Künstlers: Ablagefläche „Bilder und Videos hierher ziehen“
 *               (auch Knopf „Dateien auswählen …“ und „Aus der Mediathek …“), Reihenfolge (↑ ↓), Entfernen. Jede Änderung
 *               wird sofort über die Eintrags-Schnittstelle des Kerns gespeichert (POST /admin/api/entries/{tabelle}/{id}).
 *  [data-gnew]  Block „Werke“ im Seiten-Editor: „Neues Werk aus Foto“ – legt je Foto einen Entwurf an (Titel aus dem
 *               Dateinamen) und öffnet ihn in der Seitenleiste des Kerns.
 *
 * Hochladen: window.CMSMedia.Uploader des Kerns (Stücke über /admin/media/chunk + /admin/media/finalize, Fortschritt,
 * Prüfung von Typ und Größe mit deutschen Meldungen). Der Alt-Text wird aus den Angaben des Eintrags vorgeschlagen, damit
 * ein Ziehen genügt; verbessern lässt er sich jederzeit in der Mediathek. Ereignis-Delegation: übersteht das Neuzeichnen
 * von Block-Vorschauen im Editor. Keine Inline-Skripte, keine style-Attribute (nur el.style).
 */
(() => {
  const d = document;
  const VISUAL = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4'];
  const IMAGE = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const cfgOf = el => { try { return JSON.parse(el.dataset.gmm || el.dataset.gnew || '{}'); } catch { return {}; } };
  const say = (el, msg, kind = '') => { if (!el) return; el.textContent = msg; el.dataset.kind = kind; };
  const title = name => name.replace(/\.[a-z0-9]{2,5}$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/^./, c => c.toUpperCase()) || 'Ohne Titel';

  /** CSRF für die Mediathek des Kerns (liest #adm-csrf im Dokument – wie _entry_edit.js) */
  function ensureCsrf(token) {
    let c = d.getElementById('adm-csrf');
    if (!c) { c = d.createElement('input'); c.type = 'hidden'; c.id = 'adm-csrf'; d.body.append(c); }
    if (!c.value && token) c.value = token;
    return c.value || token;
  }

  async function post(url, token, body) {
    const r = await fetch(url, { method: 'POST', credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': ensureCsrf(token) }, body: JSON.stringify(body) });
    if (r.redirected && /\/admin\/login/.test(r.url)) throw new Error('Ihre Sitzung ist abgelaufen – bitte neu anmelden.');
    const res = await r.json().catch(() => ({ ok: false, error: 'Fehler ' + r.status }));
    if (!res.ok) throw new Error(Object.values(res.errors || {})[0] || res.error || 'Speichern fehlgeschlagen.');
    return res;
  }

  /** Dateien hochladen (Uploader des Kerns), Alt-Text vorschlagen, automatisch starten. onDone(media) je Datei. */
  function upload(queue, files, { alt, accept = 'visual', onDone, status, csrf }) {
    const M = window.CMSMedia;
    ensureCsrf(csrf);
    if (!M?.Uploader) { say(status, 'Hochladen ist hier nicht verfügbar – bitte in der Verwaltung unter Medien hochladen.', 'error'); return; }
    const types = accept === 'image' ? IMAGE : VISUAL;
    const bad = files.filter(f => !types.includes(f.type));
    if (bad.length) say(status, (accept === 'image' ? 'Nur Fotos (JPG, PNG, WebP) möglich: ' : 'Nur Bilder (JPG, PNG, WebP) und Videos (MP4) möglich: ') + bad.map(f => f.name).join(', '), 'error');
    files = files.filter(f => types.includes(f.type));
    if (!files.length) return;
    const up = queue._up || (queue._up = new M.Uploader(queue, { accept: accept === 'image' ? 'image' : 'visual', onDone, tags: () => 'galerie' }));
    up.opts.onDone = onDone;
    up.add(files);
    up.items.forEach(i => { if (i.isImage && !i.alt && !i.error) i.alt = (typeof alt === 'function' ? alt(i.file) : alt) || title(i.file.name); });
    up.render();
    const waiting = up.items.filter(i => i.status === 'wait' && !i.error);
    if (waiting.length && waiting.every(i => up.valid(i))) {
      say(status, waiting.length > 1 ? `${waiting.length} Dateien werden hochgeladen …` : 'Datei wird hochgeladen …', 'busy');
      up.start().then(() => {
        const failed = up.items.filter(i => i.status === 'failed');
        if (failed.length) say(status, 'Hochladen fehlgeschlagen: ' + failed.map(i => `${i.file.name} (${i.error})`).join(', '), 'error');
      });
    } else if (waiting.length) say(status, 'Bitte den Alt-Text prüfen und „Hochladen“ wählen.', 'warn');
  }

  // ================================================================== Medien eines Eintrags (Detailseite)
  function initManager(box) {
    if (box._gmm) return;
    const cfg = cfgOf(box);
    const list = box.querySelector('[data-gmm-list]'), status = box.querySelector('[data-gmm-status]'), queue = box.querySelector('[data-gmm-queue]');
    const slots = cfg.slots || [];
    // Zustand: Feld → Medium (nur belegte)
    const state = {};
    (cfg.items || []).forEach(m => { state[m.f] = m; });
    box._gmm = { state };

    const isVideo = m => (m?.mime || '').startsWith('video/') || m?.kind === 'video';
    const ordered = () => slots.map(s => state[s.f]).filter(Boolean);

    function render() {
      const items = ordered();
      list.innerHTML = items.map((m, i) => `<li class="gmm__item" data-id="${m.id}">
        <span class="gmm__thumb">${m.thumb ? `<img src="${esc(m.thumb)}" alt="">` : `<span>${isVideo(m) ? 'Video' : 'Datei'}</span>`}${isVideo(m) ? '<span class="gmm__badge">Video</span>' : ''}</span>
        <span class="gmm__name">${i === 0 && !isVideo(m) && slots[0]?.k === 'image' && state[slots[0].f] === m ? '<strong>Hauptbild</strong> · ' : ''}${esc(m.display || m.name || ('#' + m.id))}</span>
        <span class="gmm__tools">
          <button type="button" class="gmm__icon" data-gmm-up ${i === 0 ? 'disabled' : ''} aria-label="${esc((m.display || '') + ' nach vorn')}">↑</button>
          <button type="button" class="gmm__icon" data-gmm-down ${i === items.length - 1 ? 'disabled' : ''} aria-label="${esc((m.display || '') + ' nach hinten')}">↓</button>
          <button type="button" class="gmm__icon gmm__icon--x" data-gmm-remove aria-label="${esc((m.display || '') + ' entfernen')}">✕</button>
        </span></li>`).join('');
      list.hidden = !items.length;
    }

    /** Liste neu auf die Plätze verteilen: Bilder dürfen überall hin, Videos nur in Plätze „Bild oder Video“ */
    function distribute(items) {
      const next = {}, rest = [...items];
      for (const s of slots) {
        const k = rest.findIndex(m => s.k === 'visual' || !isVideo(m));
        if (k >= 0) next[s.f] = rest.splice(k, 1)[0];
      }
      return { next, overflow: rest };
    }

    async function persist(items, msg) {
      const { next, overflow } = distribute(items);
      const f = {};
      slots.forEach(s => { f[s.f] = next[s.f] ? next[s.f].id : ''; });
      say(status, 'Speichere …', 'busy');
      try {
        await post(cfg.endpoint, cfg.csrf, { f, partial: 1 });
        Object.keys(state).forEach(k => delete state[k]);
        Object.assign(state, next);
        render();
        say(status, (msg || 'Gespeichert.') + (overflow.length ? ` ${overflow.length} Datei(en) nicht übernommen – alle ${slots.length} Plätze sind belegt.` : ''), overflow.length ? 'warn' : 'ok');
        box.querySelector('[data-gmm-reload]').hidden = false;
      } catch (e) { say(status, e.message, 'error'); }
    }

    function add(m) {
      if (!m?.id) return;
      if (ordered().some(x => x.id === m.id)) { say(status, 'Diese Datei ist schon dabei.', 'warn'); return; }
      const free = slots.filter(s => !state[s.f] && (s.k === 'visual' || !isVideo(m)));
      if (!free.length) { say(status, isVideo(m) ? 'Kein freier Platz für ein Video – bitte zuerst eine Datei entfernen.' : 'Alle Plätze sind belegt – bitte zuerst eine Datei entfernen.', 'error'); return; }
      persist([...ordered(), m], `„${m.display || m.name || 'Datei'}“ hinzugefügt und gespeichert.`);
    }

    // Uploads nacheinander übernehmen (jede fertige Datei sofort)
    let chain = Promise.resolve();
    const onDone = m => { chain = chain.then(() => add(m)); };
    box._gmm.upload = files => upload(queue, files, { alt: cfg.alt, onDone, status, csrf: cfg.csrf });

    box.addEventListener('click', async e => {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.matches('[data-gmm-choose]')) {
        const i = d.createElement('input');
        i.type = 'file'; i.multiple = true; i.accept = VISUAL.join(',');
        i.onchange = () => box._gmm.upload([...i.files]);
        i.click();
      } else if (b.matches('[data-gmm-library]')) {
        const m = await window.CMSMedia?.pick?.('visual');
        if (m) add(m);
      } else if (b.matches('[data-gmm-refresh]')) {
        location.reload();
      } else {
        const li = b.closest('.gmm__item');
        if (!li) return;
        const items = ordered(), i = items.findIndex(m => String(m.id) === li.dataset.id);
        if (b.matches('[data-gmm-remove]')) { items.splice(i, 1); persist(items, 'Entfernt (die Datei bleibt in der Mediathek).'); }
        if (b.matches('[data-gmm-up]') && i > 0) {
          [items[i - 1], items[i]] = [items[i], items[i - 1]];
          if (slots[0]?.k === 'image' && isVideo(items[0])) { say(status, 'Das Hauptbild muss ein Bild sein – ein Video kann nicht an erster Stelle stehen.', 'error'); return; }
          persist(items, 'Reihenfolge gespeichert.');
        }
        if (b.matches('[data-gmm-down]') && i < items.length - 1) {
          [items[i + 1], items[i]] = [items[i], items[i + 1]];
          if (slots[0]?.k === 'image' && isVideo(items[0])) { say(status, 'Das Hauptbild muss ein Bild sein – ein Video kann nicht an erster Stelle stehen.', 'error'); return; }
          persist(items, 'Reihenfolge gespeichert.');
        }
      }
    });
    render();
  }

  // ================================================================== Neues Werk aus Foto (Block „Werke“ im Editor)
  async function newWorks(box, files) {
    const cfg = cfgOf(box);
    const status = box.querySelector('[data-gnew-status]'), queue = box.querySelector('[data-gnew-queue]');
    upload(queue, files, {
      accept: 'image', status, csrf: cfg.csrf, alt: f => 'Werkabbildung: ' + title(f.name),
      onDone: async m => {
        try {
          const res = await post(cfg.endpoint, cfg.csrf, { f: { titel: title(m.name || m.display || ''), bild: m.id, verfuegbarkeit: 'verfuegbar', preis_anzeige: 'anfrage' }, status: 'draft' });
          say(status, `Werk „${res.title}“ als Entwurf angelegt – ergänzen Sie die Angaben in der Seitenleiste.`, 'ok');
          // Seitenleiste „Eintrag bearbeiten“ des Kerns öffnen (gleicher Weg wie der Stift in Datenlisten)
          const a = d.createElement('a');
          a.href = '#'; a.hidden = true; a.dataset.entryEdit = cfg.base + res.id;
          box.append(a); a.click(); a.remove();
        } catch (e) { say(status, 'Werk konnte nicht angelegt werden: ' + e.message, 'error'); }
      },
    });
  }

  // ================================================================== Ziehen & Ablegen, Knöpfe (delegiert)
  const zone = e => e.target.closest?.('[data-gmm],[data-gnew]');
  const hasFiles = e => [...(e.dataTransfer?.types || [])].includes('Files');
  d.addEventListener('dragover', e => {
    const z = zone(e);
    if (!z || !hasFiles(e)) return;
    e.preventDefault(); e.stopPropagation();
    e.dataTransfer.dropEffect = 'copy';
    z.classList.add('is-over');
  }, true);
  d.addEventListener('dragleave', e => { const z = zone(e); if (z && !z.contains(e.relatedTarget)) z.classList.remove('is-over'); }, true);
  d.addEventListener('drop', e => {
    const z = zone(e);
    if (!z || !hasFiles(e)) return;
    e.preventDefault(); e.stopPropagation();
    z.classList.remove('is-over');
    const files = [...e.dataTransfer.files];
    if (z.matches('[data-gmm]')) { initManager(z); z._gmm.upload(files); } else newWorks(z, files);
  }, true);
  d.addEventListener('click', e => {
    const b = e.target.closest?.('[data-gnew-choose]');
    if (!b) return;
    const box = b.closest('[data-gnew]');
    const i = d.createElement('input');
    i.type = 'file'; i.multiple = true; i.accept = IMAGE.join(',');
    i.onchange = () => newWorks(box, [...i.files]);
    i.click();
  });
  const boot = () => d.querySelectorAll('[data-gmm]').forEach(box => {
    initManager(box);
  });
  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', boot); else boot();
})();
