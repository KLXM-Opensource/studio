/*
 * Blockauswahl „+ Block einfügen“ des Editors (editor.js → BlockPicker): Blocktypen nach Gruppen, drei Ansichten
 *   list   kompakte Liste (Symbol + Name, Beschreibung als Tooltip)
 *   tiles  Kacheln mit Vorschaugrafik und Kurzbeschreibung, Sprungleiste der Gruppen
 *   split  Liste links, rechts große Vorschau, Beschreibung, Varianten, Favorit und „Einfügen“
 * Oben: kopierter Block, Favoriten, zuletzt verwendet, häufig verwendet (Website). Die Ansicht, Favoriten und die letzten
 * fünf Blöcke merkt sich das Konto (Core\Blocks\Picker, POST cfg.endpoints.picker).
 * Tastatur: Tippen filtert (Name, Beschreibung, Typ, Gruppe), ↑/↓ (in Kacheln auch ←/→ bei leerem Suchfeld) wählen,
 * Enter fügt ein, Esc schließt. Fokus bleibt im Suchfeld (Combobox mit aria-activedescendant).
 * Vorschauen (cfg.blocks[typ].preview.url) sind Dateien (<img>), fehlt eine, erscheint das Symbol des Blocks.
 * „Vorschau gestalten“ (nur mit Recht media.upload, cfg.grafik): Grafik-Editor im eingebetteten Fenster (Verwaltungsseite
 * /admin/grafik/editor?format=preview&block=…&variant=…&embed=1, eigene CSP der Verwaltung); nach dem Speichern meldet er
 * die neue Adresse per postMessage (gleiche Herkunft) – die Auswahl zeigt sie sofort.
 */
const ORDER = ['Kopf', 'Inhalt', 'Medien', 'Layout', '*', 'Daten', 'Formulare', 'Navigation', 'Website'];
const rank = g => { const i = ORDER.indexOf(g); return i < 0 ? ORDER.indexOf('*') : i; };

export function createBlockPicker({ cfg, host, blockIcon, clipboard, onInsert, savePrefs }) {
  const t = CMSAdmin.t, esc = CMSAdmin.esc, ico = n => CMSAdmin.ico?.(n) || '';
  const VIEWS = [['list', 'list', t('Liste')], ['tiles', 'squares-four', t('Kacheln mit Vorschau')], ['split', 'sidebar-simple', t('Liste mit großer Vorschau')]];
  const prefs = { view: 'list', fav: [], recent: [], ...(cfg.picker || {}) };
  if (!VIEWS.some(([v]) => v === prefs.view)) prefs.view = 'list';

  const el = document.createElement('div');
  el.className = 'cms-addpop'; el.hidden = true;
  el.setAttribute('role', 'dialog');
  el.innerHTML = `<div class="cms-addpop__head">
      <input type="search" class="cms-addpop__q" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="cms-addpop-list" placeholder="${esc(t('Blocktyp suchen …'))}" aria-label="${esc(t('Blocktyp suchen'))}" autocomplete="off">
      <div class="cms-addpop__views" role="group" aria-label="${esc(t('Ansicht'))}">${VIEWS.map(([v, i, l]) => `<button type="button" class="cms-addpop__view" data-view="${v}" aria-pressed="false" title="${esc(l)}" aria-label="${esc(l)}">${ico(i)}</button>`).join('')}</div>
    </div>
    <nav class="cms-addpop__cats" aria-label="${esc(t('Gruppen'))}"></nav>
    <div class="cms-addpop__body">
      <div class="cms-addpop__list" id="cms-addpop-list" role="listbox" aria-label="${esc(t('Blocktypen'))}"></div>
      <section class="cms-addpop__detail" aria-label="${esc(t('Vorschau'))}"></section>
    </div>
    <p class="cms-addpop__none" role="status" hidden>${esc(t('Nichts gefunden.'))}</p>`;
  host.append(el);
  const $ = s => el.querySelector(s), $$ = s => [...el.querySelectorAll(s)];
  const q = $('.cms-addpop__q'), list = $('.cms-addpop__list'), none = $('.cms-addpop__none'), cats = $('.cms-addpop__cats'), detail = $('.cms-addpop__detail');

  const types = Object.entries(cfg.blocks).filter(([, d]) => d.insertable !== false && d.listed !== false);   // listed: false → nur über Vorlagen
  const ok = new Set(types.map(([t]) => t));
  const presets = (cfg.presets || []).filter(p => cfg.blocks[p.type]);   // Vorlagen der Erweiterungen (Extension::blockPresets)
  const groupOf = d => d.group || 'Inhalt';
  const groups = [...new Set(types.map(([, d]) => groupOf(d)))].sort((x, y) => rank(x) - rank(y));

  let opener = null, ctx = null, column = null, only = null, sel = null, variant = null, seq = 0;

  // ---------------------------------------------------------------- Aufbau
  const pv = (type, cls = '') => {
    const p = cfg.blocks[type]?.preview;
    return p?.url ? `<span class="cms-addpop__pv${cls}" aria-hidden="true"><img alt="" data-src="${esc(p.url)}" width="160" height="100" loading="lazy" decoding="async"></span>`
      : `<span class="cms-addpop__pv cms-addpop__pv--ico${cls}" aria-hidden="true">${blockIcon(cfg.blocks[type] || {})}</span>`;
  };
  const item = (type, { kind = '', label, desc = '', extra = '', icon = '' } = {}) => {
    const d = cfg.blocks[type] || {};
    label ??= t(d.label || type);
    desc = desc || d.description || '';
    const words = [label, desc, type, t(groupOf(d))].join(' ').toLowerCase();
    return `<button type="button" class="cms-addpop__item${kind ? ' cms-addpop__item--' + kind : ''}" role="option" id="cms-addpop-o${++seq}" tabindex="-1" aria-selected="false" data-type="${esc(type)}" data-q="${esc(words)}"${extra}${desc ? ` title="${esc(desc)}"` : ''}>`
      + pv(type) + `<span class="cms-addpop__ico" aria-hidden="true">${icon || blockIcon(d)}</span>`
      + `<span class="cms-addpop__txt"><span class="cms-addpop__label">${esc(label)}</span>${desc ? `<small class="cms-addpop__desc">${esc(desc)}</small>` : ''}</span></button>`;
  };
  const section = (key, title, body, cls = '') => body ? `<div class="cms-addpop__sec${cls}" role="group" aria-labelledby="cms-addpop-g${++seq}" data-sec="${esc(key)}"><p class="cms-addpop__group" id="cms-addpop-g${seq}">${esc(title)}</p><div class="cms-addpop__grid">${body}</div></div>` : '';

  function build() {
    seq = 0;
    const fav = prefs.fav.filter(t => ok.has(t));
    const recent = prefs.recent.filter(t => ok.has(t) && !fav.includes(t));
    // „Häufig verwendet“: meiste Vorkommen auf dieser Website, sonst übliche Grundblöcke
    const used = Object.keys(cfg.usage || {}).filter(t => ok.has(t));
    const often = (used.length >= 3 ? used : [...used, ...['richtext', 'text', 'heading', 'media_text', 'cards', 'image', 'hero'].filter(t => ok.has(t) && !used.includes(t))])
      .filter(t => !fav.includes(t) && !recent.includes(t)).slice(0, 6);
    const c = column == null ? clipboard() : null;
    let h = c ? section('paste', t('Zwischenablage'), item(c.type, { kind: 'paste pin', label: t('Kopierten Block einfügen: {label}', { label: t(cfg.blocks[c.type].label) }), extra: ' data-paste="1"' }), ' cms-addpop__sec--pin') : '';
    h += section('fav', t('Favoriten'), fav.map(t => item(t, { kind: 'pin' })).join(''), ' cms-addpop__sec--pin');
    h += section('recent', t('Zuletzt verwendet'), recent.map(t => item(t, { kind: 'pin' })).join(''), ' cms-addpop__sec--pin');
    h += section('often', t('Häufig verwendet'), often.map(t => item(t, { kind: 'pin' })).join(''), ' cms-addpop__sec--pin');
    h += groups.map(g => section('g:' + g, t(g), types.filter(([, d]) => groupOf(d) === g).map(([t]) => item(t)).join(''))).join('');
    if (presets.length) {
      const pg = [...new Set(presets.map(p => p.group || ''))];
      h += pg.map(g => section('p:' + g, g || t('Vorlagen'), presets.map((p, n) => (p.group || '') !== g ? '' : item(p.type, {
        kind: 'preset', label: p.label, desc: p.hint || '', extra: ` data-preset="${n}"`, icon: p.icon ? ico(p.icon) || '' : '',
      })).join(''))).join('');
    }
    list.innerHTML = h;
    cats.innerHTML = $$('.cms-addpop__sec:not(.cms-addpop__sec--pin)').map(s => `<button type="button" class="cms-addpop__cat" data-cat="${esc(s.dataset.sec)}">${esc(s.querySelector('.cms-addpop__group').textContent)}</button>`).join('');
  }

  // ---------------------------------------------------------------- Zustand
  const items = () => $$('.cms-addpop__item').filter(b => !b.hidden && !b.closest('.cms-addpop__sec').hidden);
  const splitOn = () => prefs.view === 'split' && detail.offsetParent !== null;
  const loadImgs = scope => (scope.querySelectorAll ? [...scope.querySelectorAll('img[data-src]')] : []).forEach(i => { i.src = i.dataset.src; i.removeAttribute('data-src'); });
  const setView = (v, save = true) => {
    prefs.view = v;
    el.dataset.view = v;
    $$('.cms-addpop__view').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.view === v)));
    if (v === 'tiles') loadImgs(list);
    if (save) savePrefs({ view: v });
    place();
    sel?.scrollIntoView({ block: 'nearest' });
    showDetail();
  };
  const mark = (btn, scroll = true) => {
    sel = btn || null;
    $$('.cms-addpop__item[aria-selected=true]').forEach(b => b.setAttribute('aria-selected', 'false'));
    if (sel) { sel.setAttribute('aria-selected', 'true'); q.setAttribute('aria-activedescendant', sel.id); if (scroll) sel.scrollIntoView({ block: 'nearest' }); }
    else q.removeAttribute('aria-activedescendant');
    variant = null;
    showDetail();
  };
  const presetOf = b => b?.dataset.preset != null ? presets[+b.dataset.preset] : null;

  // Rechte Spalte (Ansicht „split“): große Vorschau, Name, Gruppe, Beschreibung, Varianten, Favorit, Einfügen
  function showDetail() {
    if (prefs.view !== 'split') return;
    if (!sel) { detail.innerHTML = `<p class="cms-addpop__hint">${esc(t('Blocktyp wählen, um eine Vorschau zu sehen.'))}</p>`; return; }
    const type = sel.dataset.type, d = cfg.blocks[type] || {}, preset = presetOf(sel), paste = !!sel.dataset.paste;
    const vs = !paste && !preset && column == null && d.variants && typeof d.variants === 'object' ? Object.entries(d.variants) : [];
    const pvUrl = (variant && d.preview?.variants?.[variant]) || d.preview?.url;
    const isFav = prefs.fav.includes(type);
    // Fokus im neu gezeichneten Bereich halten (sonst fiele er auf die Seite – Esc würde dann den Bearbeiten-Modus verlassen)
    const act = el.getRootNode().activeElement, keep = act && detail.contains(act) ? (act.dataset.variant ? `.cms-addpop__var[data-variant="${CSS.escape(act.dataset.variant)}"]` : '.' + act.classList[0]) : null;
    detail.innerHTML = `<div class="cms-addpop__big" aria-hidden="true">${pvUrl ? `<img src="${esc(pvUrl)}" alt="" width="320" height="200">` : blockIcon(d)}</div>
      <p class="cms-addpop__dgroup">${esc(preset ? (preset.group || t('Vorlagen')) : t(groupOf(d)))}</p>
      <h3 class="cms-addpop__dtitle">${esc(sel.querySelector('.cms-addpop__label').textContent)}</h3>
      ${sel.querySelector('.cms-addpop__desc') ? `<p class="cms-addpop__dtext">${esc(sel.querySelector('.cms-addpop__desc').textContent)}</p>` : ''}
      ${vs.length > 1 ? `<div class="cms-addpop__vars" role="radiogroup" aria-label="${esc(t('Variante'))}">${vs.map(([k, l], i) => `<button type="button" role="radio" class="cms-addpop__var" data-variant="${esc(k)}" aria-checked="${String(variant ? variant === k : i === 0)}">${esc(t(String(l)))}</button>`).join('')}</div>` : ''}
      <div class="cms-addpop__dact">
        <button type="button" class="cms-addpop__ins">${esc(t('Einfügen'))}</button>
        ${paste ? '' : `<button type="button" class="cms-addpop__star" aria-pressed="${isFav}" title="${esc(t('Als Favorit oben anzeigen'))}">${ico('star')}<span>${esc(isFav ? t('Favorit') : t('Merken'))}</span></button>`}
        ${cfg.grafik?.page && !paste && !preset ? `<button type="button" class="cms-addpop__design" title="${esc(t('Vorschaugrafik dieses Blocks für diese Website zeichnen'))}">${ico('pencil-simple')}<span>${esc(t('Vorschau gestalten'))}</span></button>` : ''}
      </div>`;
    if (keep) (detail.querySelector(keep) || q).focus({ preventScroll: true });
  }

  // ---------------------------------------------------------------- Vorschau gestalten (Grafik-Editor, eingebettet)
  function design(type, v, btn) {
    const dlg = document.createElement('dialog');
    dlg.className = 'cms-addpop__ge';
    dlg.setAttribute('aria-label', t('Vorschau gestalten'));
    const u = new URL(cfg.grafik.page, location.href);
    u.searchParams.set('format', 'preview'); u.searchParams.set('block', type); u.searchParams.set('embed', '1');
    if (v) u.searchParams.set('variant', v);
    dlg.innerHTML = `<iframe title="${esc(t('Grafik-Editor'))}" src="${esc(u.pathname + u.search)}"></iframe>`;
    el.append(dlg);
    const done = () => { removeEventListener('message', onMsg); if (dlg.open) dlg.close(); dlg.remove(); (detail.querySelector('.cms-addpop__design') || btn)?.focus?.({ preventScroll: true }); };
    const onMsg = ev => {
      if (ev.origin !== location.origin || ev.source !== dlg.querySelector('iframe')?.contentWindow || ev.data?.source !== 'klxm-grafik') return;
      if (ev.data.type === 'close') { done(); return; }
      if (ev.data.type === 'saved' && ev.data.url && ev.data.active && ev.data.block) {
        const [bt, bv] = String(ev.data.block).split('--');
        const d = cfg.blocks[bt];
        if (!d) return;
        if (bv) d.preview = { url: d.preview?.url || ev.data.url, variants: { ...(d.preview?.variants || {}), [bv]: ev.data.url } };
        else d.preview = { url: ev.data.url, variants: {} };
        const keepType = sel?.dataset.type, keepVar = variant;
        build(); filter();
        if (prefs.view === 'tiles') loadImgs(list);
        const again = $$('.cms-addpop__item').find(b => b.dataset.type === keepType && !b.dataset.paste && b.dataset.preset == null);
        if (again) { mark(again, false); variant = keepVar; showDetail(); }
      }
    };
    addEventListener('message', onMsg);
    dlg.addEventListener('cancel', e => { e.preventDefault(); done(); });
    dlg.showModal();
    dlg.querySelector('iframe').focus();
  }

  // ---------------------------------------------------------------- Filtern, Platzieren
  const filter = () => {
    const v = q.value.trim().toLowerCase();
    const words = v.split(/\s+/).filter(Boolean);
    $$('.cms-addpop__sec').forEach(s => {
      const pin = s.classList.contains('cms-addpop__sec--pin'), paste = s.dataset.sec === 'paste';
      let any = false;
      s.querySelectorAll('.cms-addpop__item').forEach(b => {
        b.hidden = (pin && (paste ? !!v : (!!v || !!only))) || (!pin && only && !only.has(b.dataset.type)) || !words.every(w => b.dataset.q.includes(w));
        if (!b.hidden) any = true;
      });
      s.hidden = !any;
    });
    $$('.cms-addpop__cat').forEach(c => { c.hidden = !!list.querySelector(`.cms-addpop__sec[data-sec="${CSS.escape(c.dataset.cat)}"]`)?.hidden; });
    const vis = items();
    none.hidden = vis.length > 0;
    // Treffer im Namen vor Treffern in der Beschreibung: „zitat“ wählt „Zitat“, nicht „Fließtext (… Zitaten …)“
    const lab = b => b.querySelector('.cms-addpop__label').textContent.toLowerCase();
    mark(v ? vis.find(b => lab(b).startsWith(v)) || vis.find(b => lab(b).includes(v)) || vis[0] : vis[0]);
    place();
  };
  const place = () => {
    if (el.hidden || !opener) return;
    // Nicht unter die feste Werkzeugleiste oben (.cms-bar-host); passt die Auswahl weder unter noch über den Knopf, wird sie niedriger
    const bar = document.querySelector('body>.cms-bar-host')?.getBoundingClientRect(), min = Math.max(8, bar && bar.top < 40 ? bar.bottom + 8 : 8);
    el.style.maxHeight = '';
    el.style.maxHeight = Math.min(el.offsetHeight, innerHeight - min - 8) + 'px';
    const r = opener.getBoundingClientRect(), h = el.offsetHeight, w = el.offsetWidth;
    const top = r.bottom + 8 + h <= innerHeight - 8 ? r.bottom + 8 : r.top - 8 - h >= min ? r.top - 8 - h : Math.max(min, Math.min(r.bottom + 8, innerHeight - 8 - h));
    el.style.top = top + 'px';
    el.style.left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, innerWidth - w - 8)) + 'px';
  };
  const close = (back = true) => {
    if (el.hidden) return;
    el.hidden = true;
    if (back) opener?.focus({ preventScroll: true });
    opener = null; ctx = null; only = null; column = null; sel = null;
  };

  // ---------------------------------------------------------------- Einfügen
  const choose = b => {
    if (!b) return;
    const type = b.dataset.type, preset = presetOf(b), c = ctx, col = column, v = variant;
    close(false);
    if (!b.dataset.paste) {
      prefs.recent = [type, ...prefs.recent.filter(t => t !== type)].slice(0, 5);
      savePrefs({ recent: prefs.recent });
    }
    onInsert({ type, paste: !!b.dataset.paste, preset, variant: v }, c, col);
  };
  const toggleFav = type => {
    prefs.fav = prefs.fav.includes(type) ? prefs.fav.filter(t => t !== type) : [...prefs.fav, type].slice(-24);
    savePrefs({ fav: prefs.fav });
    showDetail();
  };

  // ---------------------------------------------------------------- Ereignisse
  list.addEventListener('click', e => {
    const b = e.target.closest('.cms-addpop__item');
    if (!b) return;
    if (splitOn() && b !== sel) { mark(b, false); q.focus({ preventScroll: true }); return; }   // Liste mit Vorschau: erster Klick zeigt, zweiter fügt ein
    choose(b);
  });
  list.addEventListener('dblclick', e => { const b = e.target.closest('.cms-addpop__item'); if (b && splitOn()) choose(b); });
  detail.addEventListener('click', e => {
    if (e.target.closest('.cms-addpop__ins')) choose(sel);
    else if (e.target.closest('.cms-addpop__star') && sel) toggleFav(sel.dataset.type);
    else if (e.target.closest('.cms-addpop__design') && sel) design(sel.dataset.type, variant, e.target.closest('.cms-addpop__design'));
    else if (e.target.closest('.cms-addpop__var')) {
      const k = e.target.closest('.cms-addpop__var').dataset.variant;
      variant = k === Object.keys(cfg.blocks[sel.dataset.type]?.variants || {})[0] ? null : k;   // erste Variante = Standard des Blocks
      showDetail();
    }
  });
  el.querySelector('.cms-addpop__views').addEventListener('click', e => { const b = e.target.closest('[data-view]'); if (b) { setView(b.dataset.view); q.focus({ preventScroll: true }); } });
  cats.addEventListener('click', e => {
    const c = e.target.closest('[data-cat]'); if (!c) return;
    const s = list.querySelector(`.cms-addpop__sec[data-sec="${CSS.escape(c.dataset.cat)}"]`);
    if (!s) return;
    list.scrollTo({ top: s.offsetTop - list.offsetTop, behavior: 'smooth' });
    mark([...s.querySelectorAll('.cms-addpop__item')].find(b => !b.hidden), false);
    q.focus({ preventScroll: true });
  });
  q.addEventListener('input', filter);
  // Kacheln: nächste Kachel in Pfeilrichtung (über Gruppen hinweg), sonst in der Reihenfolge
  const step = (dir, all, cur) => {
    if (prefs.view !== 'tiles' || !cur || dir === 'ArrowLeft' || dir === 'ArrowRight') {
      const i = all.indexOf(cur), d = dir === 'ArrowDown' || dir === 'ArrowRight' ? 1 : -1;
      return all[(i + d + all.length) % all.length];
    }
    const r = cur.getBoundingClientRect(), cx = r.left + r.width / 2, down = dir === 'ArrowDown';
    let best = null, bd = Infinity;
    for (const b of all) {
      if (b === cur) continue;
      const o = b.getBoundingClientRect();
      if (down ? o.top < r.bottom - 2 : o.bottom > r.top + 2) continue;
      const score = Math.abs(down ? o.top - r.bottom : r.top - o.bottom) * 4 + Math.abs(o.left + o.width / 2 - cx);
      if (score < bd) { bd = score; best = b; }
    }
    return best || cur;
  };
  el.addEventListener('keydown', e => {
    e.stopPropagation();
    const all = items();
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.target !== q && !e.target.closest('.cms-addpop__list')) return;   // Knöpfe der Ansicht/Vorschau: Standardverhalten
    const arrows = ['ArrowDown', 'ArrowUp'].concat(prefs.view === 'tiles' && q.value === '' ? ['ArrowLeft', 'ArrowRight'] : []);
    if (arrows.includes(e.key)) {
      e.preventDefault();
      const n = step(e.key, all, sel && all.includes(sel) ? sel : null) || all[0];
      if (n) mark(n);
    } else if (e.key === 'Enter') { e.preventDefault(); choose(sel && all.includes(sel) ? sel : all[0]); }
  });
  // Fokus verlässt die Auswahl (Tab, Klick daneben): schließen
  el.addEventListener('focusout', e => { if (!el.hidden && e.relatedTarget && !el.contains(e.relatedTarget) && e.relatedTarget !== opener) close(false); });
  document.addEventListener('pointerdown', e => { if (!el.hidden && !e.composedPath().includes(el) && !e.composedPath().includes(opener)) close(false); }, true);
  addEventListener('resize', place);
  addEventListener('scroll', place, { passive: true });

  return {
    /** opts.column: Spalte eines Layouts – nur verschachtelbare Blöcke (cfg.blocks[typ].nestable) */
    open(btn, context, opts = {}) {
      if (!el.hidden && opener === btn) { close(); return; }
      opener = btn; ctx = context;
      column = opts.column ?? null;
      only = column != null ? new Set(types.filter(([, d]) => d.nestable).map(([t]) => t)) : null;
      el.setAttribute('aria-label', column != null ? t('Block in diese Spalte einfügen') : t('Block einfügen'));
      build();
      q.value = '';
      el.hidden = false;
      setView(prefs.view, false);
      filter();
      list.scrollTop = 0;
      q.focus({ preventScroll: true });
    },
    close,
  };
}
