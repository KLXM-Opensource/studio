import { t } from './_i18n.js';
import { drill } from './_drill.js';
import { layerBox, inPath, topInset } from './_shadow.js';
import { ico } from './_icons.js';
import { ask } from './_bar.js';   // gestaltete Rückfrage statt window.confirm()
import { captionsPanel, initCaptionsQueue } from './_captions.js';   // Untertitel & Transkripte (Video/Audio)
import { adjustDialog, applyFx, fxLabel } from './_imagefx.js';   // Bild anpassen (Core\ImageFx)
import { fitDialog, fitLabel } from './_imagefit.js';   // Bild im Rahmen: füllen, einpassen, Originalformat (Core\ImageFit)
import { editImage, editToolbar } from './_imageedit.js';   // Bild bearbeiten (Core\ImageEdit): Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren
/*
 * Mediathek im Finder-Stil (KLXM Studio)
 *  - Seitenleiste: Mediathek, Sammlungen (Dateien per Drag & Drop hineinziehen), Tags
 *  - Symbol- und Listenansicht, Mehrfachauswahl (⌘/Strg, ⇧), Tastatur, Quick Look (Leertaste), Kontextmenü
 *  - Informationen rechts: Alt-Text (Pflicht), Titel, Tags, Sammlungen, Fokuspunkt, Zuschnitte, Verwendung – speichert automatisch
 *  - Upload per Drag & Drop, mehrere Dateien, in 1-MB-Stücken; Alt-Text verbindlich
 *  - Zuschneiden je Bildformat mit Zoom und Verschieben – auch direkt auf der Seite im Bearbeiten-Modus
 *  - Bild bearbeiten (Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren; zerstörungsfrei): Werkzeugleiste in „Alle Details“
 *  - Bild anpassen (Effekte, Sättigung, Helligkeit, Kontrast, Schärfe; zerstörungsfrei): global in „Alle Details“ bzw. rechts,
 *    im Bearbeiten-Modus je Einbindung im Block (window.CMSEditor.fx, resources/js/editor.js)
 *  - Auswahldialog für Bild-/Datei-Felder: window.CMSMedia.pick(kind)
 */
(() => {
const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const FINDERS = [];   // geöffnete Mediatheken (Seite + Auswahldialoge) – für Einfügen aus der Zwischenablage
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

// Übersetzungen von Titel/Alt-Text je weiterer Inhaltssprache (meta.languages)
const transHtml = (m, meta, isImg) => Object.entries(meta?.languages || {}).map(([code, label]) => {
  const tr = (m.i18n || {})[code] || {}, miss = isImg && !m.decorative && !tr.alt;
  return `<details class="fx-trans"${miss ? ' open' : ''}><summary>${esc(label)}${miss ? ` <span class="fx-trans-miss">${esc(t('Alt-Text fehlt'))}</span>` : ''}</summary>
    <label>${esc(t('Titel'))}<input name="i18n.${code}.title" value="${esc(tr.title || '')}" placeholder="${esc(m.title || m.display)}" maxlength="180"></label>
    <label>${esc(isImg ? t('Alt-Text') : t('Beschreibung'))}<textarea name="i18n.${code}.alt" rows="2" maxlength="250" placeholder="${esc(m.alt)}">${esc(tr.alt || '')}</textarea></label></details>`;
}).join('');
const readTrans = form => {
  const o = {};
  form.querySelectorAll('[name^="i18n."]').forEach(el => { const [, l, k] = el.name.split('.'); (o[l] ||= {})[k] = el.value; });
  return o;
};
const csrf = () => $('#adm-csrf')?.value || '';
const BASE = ($('[data-media-base]')?.dataset.mediaBase || '') + '/admin';
const CHUNK = 1024 * 1024;
const MAX_MB = +($('[data-media-max]')?.dataset.mediaMax || 50);
// SVG: nimmt der Server nur bereinigt an (Core\Svg) – ist die Funktion aus, kommt eine klare Meldung zurück
const ACCEPT = { image: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'], pdf: ['application/pdf'], video: ['video/mp4'], audio: ['audio/mpeg', 'audio/mp4', 'audio/x-m4a'] };
const ALL_TYPES = [...ACCEPT.image, ...ACCEPT.pdf, ...ACCEPT.video, ...ACCEPT.audio];
const ftype = f => f.type || (/\.svg$/i.test(f.name) ? 'image/svg+xml' : '');
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const store = {
  // Schlüssel klxm-studio-media-*; mycms-media-* (historisch) wird noch gelesen und beim Schreiben entfernt
  get(k, def) { try { const v = localStorage.getItem('klxm-studio-media-' + k) ?? localStorage.getItem('mycms-media-' + k); return v === null ? def : JSON.parse(v); } catch { return def; } },
  set(k, v) { try { localStorage.setItem('klxm-studio-media-' + k, JSON.stringify(v)); localStorage.removeItem('mycms-media-' + k); } catch {} },
};
const TAG_COLORS = ['#FF5F57', '#FF9F0A', '#FFD60A', '#32D74B', '#0A84FF', '#BF5AF2', '#8E8E93'];
const tagColor = t => { let h = 0; for (const c of t) h = (h * 31 + c.charCodeAt(0)) >>> 0; return TAG_COLORS[h % TAG_COLORS.length]; };
const fmtDate = s => s ? new Date(s.replace(' ', 'T')).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';

// Geteilte Medien: aktueller Pool (leer = Mediathek der Website) – wird an jede Anfrage gehängt
let POOL = '';   // gewählte geteilte Mediathek – gilt nur für die Mediathek selbst (Finder), siehe local()
/*
 * Alles außerhalb der Mediathek (Bildfelder im Editor, Werkzeuge auf der Seite, Einstellungen, KI) arbeitet mit Dateien DIESER
 * Website (IDs der Felder sind eigene IDs, auch für Verweise auf geteilte Medien). Sonst landeten Aufrufe nach einer Pool-Auswahl
 * in der Mediathek beim falschen Bild (404 bzw. „Nur Bilder lassen sich anpassen“).
 */
const local = async fn => { const prev = POOL; POOL = ''; try { return await fn(); } finally { POOL = prev; } };
/** Hinweis: Datei wird verwendet – Löschen gesperrt (Fundstellen als eigene Zeilen) */
const usedMsg = (name, used) => t('„{name}“ wird noch verwendet', { name }) + '\n'
  + used.slice(0, 6).map(u => '• ' + u.label).join('\n') + (used.length > 6 ? '\n' + t('und {n} weitere', { n: used.length - 6 }) : '')
  + '\n' + t('Bitte zuerst dort entfernen – oder „Ersetzen“ nutzen, dann bleiben alle Verwendungen erhalten.');

async function http(url, opt = {}) {
  if (POOL) {
    if (opt.json !== undefined) opt = { ...opt, json: { ...opt.json, pool: POOL } };
    else if (opt.body instanceof FormData) opt.body.append('pool', POOL);
    else url += (url.includes('?') ? '&' : '?') + 'pool=' + encodeURIComponent(POOL);
  }
  const r = await fetch(url, { credentials: 'same-origin', ...opt, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf(), ...(opt.json !== undefined ? { 'Content-Type': 'application/json' } : {}) }, body: opt.json !== undefined ? JSON.stringify(opt.json) : opt.body });
  const data = await r.json().catch(() => ({}));
  if (!r.ok || data.ok === false) throw Object.assign(new Error(data.error || 'Fehler ' + r.status), { data, status: r.status });   // data.usages bei gesperrtem Löschen
  return data;
}
const api = {
  list: p => http(BASE + '/api/media?' + new URLSearchParams(Object.fromEntries(Object.entries(p || {}).filter(([, v]) => v !== '' && v != null)))),
  detail: id => http(`${BASE}/api/media/${id}`),
  save: (id, body) => http(`${BASE}/api/media/${id}`, { method: 'POST', json: body }),
  del: id => http(`${BASE}/api/media/${id}/delete`, { method: 'POST', json: {} }),
  crop: (id, ratio, rect) => http(`${BASE}/api/media/${id}/crop`, { method: 'POST', json: { ratio, rect } }),
  adjust: (id, adjust) => http(`${BASE}/api/media/${id}/adjust`, { method: 'POST', json: { adjust } }),
  fit: (id, fit) => http(`${BASE}/api/media/${id}/fit`, { method: 'POST', json: { fit } }),
  edit: (id, edit) => http(`${BASE}/api/media/${id}/edit`, { method: 'POST', json: { edit } }),
  bulk: body => http(BASE + '/api/media-bulk', { method: 'POST', json: body }),
  collection: name => http(BASE + '/api/collections', { method: 'POST', json: { name } }),
  renameCollection: (id, name) => http(`${BASE}/api/collections/${id}`, { method: 'POST', json: { name } }),
  deleteCollection: id => http(`${BASE}/api/collections/${id}/delete`, { method: 'POST', json: {} }),
  use: id => http(BASE + '/api/media-use', { method: 'POST', json: { id } }),
  share: (ids, target) => http(BASE + '/api/media-share', { method: 'POST', json: { ids, target } }),
};

// Dialoge, Menüs und Meldungen: auf der Website in der Shadow-DOM-Ebene (_shadow.js), in der Verwaltung im body
const box = () => layerBox();
const inBox = (id, html) => {
  let el = box().querySelector('#' + id);
  if (!el) { box().insertAdjacentHTML('beforeend', html); el = box().querySelector('#' + id); }
  return el;
};

// Dekoratives Video (media.decorative): keine Untertitel nötig, auf der Website aria-hidden und ohne Bedienelemente
const DECO_VIDEO_HINT = 'Hintergrund- oder Stimmungsvideo ohne Informationsgehalt – braucht keine Untertitel und wird für Screenreader ausgeblendet.';
const decoVideo = (m, cls) => m.kind === 'video' ? `<label class="${cls}"><input type="checkbox" name="decorative" aria-describedby="deco-h-${m.id}-${cls}" ${m.decorative ? 'checked' : ''}> <span>${esc(t('Dekorativ (ohne Aussage)'))}</span></label>
  <p class="${cls === 'f-check' ? 'f-help' : 'fx-i-hint'}" id="deco-h-${m.id}-${cls}">${esc(t(DECO_VIDEO_HINT))}</p>` : '';
const decoCaptionHint = (root, on) => $$('.cap-miss', root).forEach(p => { p.hidden = on; });

// Helfer für Untertitel & Transkripte (_captions.js): gleiche Anfragen (Pool, CSRF), Dialog-Ebene, Meldungen
const CAP_HELPERS = { http: (...a) => http(...a), base: BASE, box: () => box(), toast: s => toast(s) };

/*
 * Erweiterungen der Mediathek (z. B. extensions/video_tools): window.CMSMedia.extend({ name, … }) mit optionalen Haken
 *   loaded(finder, data)            nach jedem Laden der Liste (data = Antwort von /admin/api/media)
 *   badge(m, finder) → html          zusätzlich in der Vorschau einer Datei (Symbol- und Listenansicht; m.ext.{name} aus Extension::mediaJson)
 *   panel(finder, m, ui)             Abschnitt in „Informationen“ rechts (eine Datei)
 *   multi(finder, items, el, ui)     Informationen rechts bei Mehrfachauswahl
 *   summary(finder, el, ui)          Informationen rechts ohne Auswahl (z. B. Sammelaktion für einen Prüf-Filter)
 *   menu(finder, ids, one) → [[label, fn, danger]]   Einträge im Kontextmenü
 *   quickLook(finder, m, dialog)     nach dem Aufbau der Quick-Look-Ansicht
 *   sources(finder) → [{ label, icon, run(finder) }]   Einträge im Knopf „Importieren aus …“ neben „Hochladen“ (Mediathek und
 *                                    Auswahldialog, nur mit Schreibrecht) – run() öffnet z. B. einen eigenen Dialog und ruft danach
 *                                    finder.load(id) auf; Ziel-Pool finder.pool, Sammlungen finder.meta.collections, Ort finder.src
 * ui = { t, ico, ask, esc, http, toast, box, inBox, base } – gleiche Anfragen (CSRF, Pool) und Dialog-Ebene wie die Mediathek.
 */
const PLUGINS = [];
const UI = { t, ico, ask, esc, http: (...a) => http(...a), toast: s => toast(s), box: () => box(), inBox: (id, html) => inBox(id, html), base: BASE };
const hook = (name, ...args) => PLUGINS.flatMap(p => {
  if (typeof p[name] !== 'function') return [];
  try { const r = p[name](...args); return r == null ? [] : [r]; } catch (e) { console.error('[CMSMedia.' + (p.name || '?') + '] ' + name, e); return []; }
});
function extend(plugin) {
  if (!plugin || PLUGINS.includes(plugin)) return;
  PLUGINS.push(plugin);
  // bereits geöffnete Mediatheken neu zeichnen (Erweiterungs-Skripte laden nach admin.js)
  FINDERS.forEach(f => { if (f.meta && f.root.isConnected) { plugin.loaded?.(f, f.meta); f.renderSide(); f.render(); f.renderSources(); } });
}

function toast(text) {
  const t = inBox('mu-toast', '<div id="mu-toast" class="mu-toast" role="status" hidden></div>');
  t.textContent = text; t.hidden = false;
  clearTimeout(t._h); t._h = setTimeout(() => (t.hidden = true), 2600);
}

// ============================================================ Upload in Stücken
async function uploadFile(file, meta, onProgress) {
  const uploadId = (crypto.randomUUID?.() || String(Date.now()) + Math.random().toString(16).slice(2)).replace(/[^a-zA-Z0-9-]/g, '');
  const total = Math.max(1, Math.ceil(file.size / CHUNK));
  for (let i = 0; i < total; i++) {
    const fd = new FormData();
    fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK), 'chunk');
    fd.append('upload_id', uploadId); fd.append('index', i); fd.append('total', total); fd.append('size', file.size);
    fd.append('_csrf', csrf());
    for (let tries = 0; ; tries++) {
      try { await http(BASE + '/media/chunk', { method: 'POST', body: fd }); break; }
      catch (e) { if (tries >= 2) throw e; await new Promise(r => setTimeout(r, 700 * (tries + 1))); }   // Netzfehler: erneut versuchen
    }
    onProgress?.((i + 1) / total);
  }
  return (await http(BASE + '/media/finalize', { method: 'POST', json: { upload_id: uploadId, name: file.name, ...meta } })).file;
}

function pickFiles(accept, multiple = true) {
  return new Promise(resolve => {
    const i = d.createElement('input');
    i.type = 'file'; i.accept = accept.join(','); i.multiple = multiple;
    i.onchange = () => resolve([...i.files]);
    i.click();
  });
}

// ============================================================ Upload-Warteschlange mit Pflicht-Alt-Text
class Uploader {
  /** opts: { accept: 'image'|null, collection(), tags(), onDone(media), onChange() } */
  constructor(root, opts = {}) {
    this.root = root; this.opts = opts; this.items = [];
    root.classList.add('mu');
    root.innerHTML = `<ul class="mu-queue" aria-live="polite"></ul>
      <div class="mu-actions" hidden><p class="mu-hint"></p><button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-mu-start>Hochladen</button></div>`;
    $('[data-mu-start]', root).addEventListener('click', () => this.start());
  }
  types() { return this.opts.accept === 'image' ? ACCEPT.image : this.opts.accept === 'visual' ? [...ACCEPT.image, ...ACCEPT.video] : ALL_TYPES; }
  choose() { pickFiles(this.types()).then(f => f.length && this.add(f)); }
  add(files) {
    for (const file of files) {
      const item = { file, key: Math.random().toString(36).slice(2), alt: '', decorative: false, title: '', status: 'wait', progress: 0, error: '' };
      if (!this.types().includes(ftype(file))) item.error = 'Dateityp nicht erlaubt';
      else if (file.size > MAX_MB * 1048576) item.error = `Größer als ${MAX_MB} MB`;
      item.isImage = ftype(file).startsWith('image/');
      if (item.isImage && !item.error) item.preview = URL.createObjectURL(file);
      this.items.push(item);
    }
    this.render();
    this.opts.onChange?.(this.items.length);
    $('.mu-item [data-alt]:not([disabled])', this.root)?.focus();
  }
  // KI-Vorschlag (_ai.js) zählt erst, wenn er geprüft ist (bearbeitet oder „Geprüft ✓“)
  valid(i) { return !i.error && (!i.isImage || i.decorative || (i.alt.trim().length >= 3 && !i.aiPending)); }
  render() {
    $('.mu-queue', this.root).innerHTML = this.items.map(i => `
      <li class="mu-item is-${i.status}${i.error ? ' is-error' : ''}" data-key="${i.key}">
        <span class="mu-thumb">${i.preview ? `<img src="${i.preview}" alt="">` : `<span>${esc((i.file.name.split('.').pop() || '').toUpperCase())}</span>`}</span>
        <span class="mu-main">
          <span class="mu-name">${esc(i.file.name)} <small>${(i.file.size / 1048576).toFixed(1).replace('.', ',')} MB</small></span>
          ${i.error && i.status === 'wait' ? `<span class="mu-err" role="alert">${esc(i.error)}</span>` : i.status === 'wait' && i.isImage ? `
            <label class="mu-alt"><span>Alt-Text <b aria-hidden="true">*</b></span>
              <input type="text" data-alt value="${esc(i.alt)}" maxlength="250" placeholder="Was ist auf dem Bild zu sehen?" ${i.decorative ? 'disabled' : ''} aria-required="${!i.decorative}"></label>
            <label class="mu-deco"><input type="checkbox" data-deco ${i.decorative ? 'checked' : ''}> dekorativ (ohne Aussage)</label>${window.CMSAi?.uploadSlot?.(i) || '' /* KI: Alt-Text vorschlagen */}`
          : i.status === 'wait' ? `<label class="mu-alt"><span>Titel (optional)</span><input type="text" data-title value="${esc(i.title)}" maxlength="180" placeholder="z. B. Anamnesebogen"></label>${i.file.type.startsWith('video/') ? `
            <label class="mu-deco" title="${esc(t(DECO_VIDEO_HINT))}"><input type="checkbox" data-deco ${i.decorative ? 'checked' : ''}> ${esc(t('dekorativ (ohne Aussage)'))}</label>` : ''}` : ''}
          ${i.status !== 'wait' ? `<span class="mu-bar"><span style="width:${Math.round(i.progress * 100)}%"></span></span>` : ''}
          ${i.status === 'done' ? `<span class="mu-ok">✓ ${esc(i.note || 'Hochgeladen')}</span>` : ''}
          ${i.status === 'failed' ? `<span class="mu-err" role="alert">${esc(i.error)}</span>` : ''}
        </span>
        ${i.status === 'wait' || i.status === 'failed' ? `<button type="button" class="mu-x" data-remove aria-label="${esc(i.file.name)} aus der Liste entfernen">✕</button>` : ''}
      </li>`).join('');
    $$('.mu-item', this.root).forEach(li => {
      const i = this.items.find(x => x.key === li.dataset.key);
      $('[data-alt]', li)?.addEventListener('input', e => { i.alt = e.target.value; this.updateActions(); });
      $('[data-alt]', li)?.addEventListener('keydown', e => { if (e.key === 'Enter' && !$('[data-mu-start]', this.root).disabled) this.start(); });
      $('[data-title]', li)?.addEventListener('input', e => { i.title = e.target.value; });
      $('[data-deco]', li)?.addEventListener('change', e => { i.decorative = e.target.checked; const a = $('[data-alt]', li); if (a) a.disabled = i.decorative; this.updateActions(); });
      $('[data-remove]', li)?.addEventListener('click', () => { this.items = this.items.filter(x => x !== i); this.render(); this.opts.onChange?.(this.items.length); });
      window.CMSAi?.uploadBind?.(this, i, li);   // KI-Assistent (_ai.js)
    });
    this.updateActions();
  }
  updateActions() {
    const waiting = this.items.filter(i => i.status === 'wait' && !i.error);
    const btn = $('[data-mu-start]', this.root), hint = $('.mu-hint', this.root);
    $('.mu-actions', this.root).hidden = !waiting.length;
    const missing = waiting.filter(i => !this.valid(i)).length;
    btn.disabled = !!missing;
    btn.textContent = waiting.length > 1 ? `${waiting.length} Dateien hochladen` : 'Hochladen';
    const pending = waiting.filter(i => i.aiPending).length;   // KI-Vorschläge, die noch niemand geprüft hat
    hint.textContent = pending ? t('KI-Vorschlag bei {n} Bild(ern) bitte prüfen und bestätigen.', { n: pending }) : missing ? `Alt-Text fehlt bei ${missing} ${missing === 1 ? 'Bild' : 'Bildern'} (mind. 3 Zeichen) – oder „dekorativ“ wählen.` : '';
    hint.classList.toggle('is-warn', !!missing);
  }
  async start() {
    const queue = this.items.filter(i => i.status === 'wait' && this.valid(i));
    const collection = this.opts.collection?.() || 0, tags = this.opts.tags?.() || '';
    for (const i of queue) {
      i.status = 'up'; this.render();
      try {
        const media = await uploadFile(i.file, { alt: i.alt, decorative: i.decorative ? 1 : 0, title: i.title, tags, collection },
          p => { i.progress = p; const bar = $(`[data-key="${i.key}"] .mu-bar span`, this.root); if (bar) bar.style.width = Math.round(p * 100) + '%'; });
        i.status = 'done'; i.progress = 1;
        if (media?.note) { i.note = media.note; toast(media.note); }   // SVG: bereinigt und optimiert (Größe vorher → nachher)
        this.opts.onDone?.(media);
      } catch (e) { i.status = 'failed'; i.error = e.message; }
      this.render();
    }
    setTimeout(() => { this.items = this.items.filter(i => i.status !== 'done'); this.render(); this.opts.onChange?.(this.items.length); }, queue.some(i => i.note) ? 6000 : 2000);
  }
}

// ============================================================ Zuschneiden (Zoom + Verschieben je Bildformat)
/**
 * Öffnet den Zuschnitt-Dialog. m = Medium (JSON) oder ID, ratio = '16:9' …
 * onSaved(ratio, response) wird nach jedem gespeicherten Format aufgerufen.
 */
async function crop(m, ratio, onSaved, opts = {}) {
  if (typeof m !== 'object') m = await api.detail(m);
  const meta = await api.list({ kind: 'none' });
  const ratios = meta.ratios;
  if (!ratios[ratio]) ratio = Object.keys(ratios)[0];
  const dlg = inBox('media-crop', '<dialog id="media-crop" class="cr" aria-labelledby="cr-title"></dialog>');
  const pending = {};                          // ratio → rect|null (noch nicht gespeichert)
  const current = r => (r in pending ? pending[r] : m.crops?.[r]) || null;
  dlg.innerHTML = `
    <div class="cr-head">
      <h2 id="cr-title">Zuschneiden <span>${esc(m.display)}</span></h2>
      <div class="cr-ratios" role="tablist" aria-label="Bildformat">${Object.entries(ratios).map(([r, l]) => `<button type="button" role="tab" data-ratio="${r}" title="${esc(l)}"><span class="cr-shape" style="aspect-ratio:${r.replace(':', '/')}"></span>${r}${opts.only === r ? ' <em>hier</em>' : ''}</button>`).join('')}</div>
    </div>
    <div class="cr-view" tabindex="0" aria-label="Bildausschnitt: ziehen zum Verschieben, Mausrad oder Plus/Minus zum Zoomen, Pfeiltasten zum Verschieben">
      <img alt="" draggable="false" src="${esc(m.url)}">
      <div class="cr-frame"><i></i><i></i><i></i><i></i></div>
    </div>
    <div class="cr-bar">
      <button type="button" class="cr-z" data-z="-1" aria-label="Verkleinern">−</button>
      <input type="range" min="0" max="1000" value="0" aria-label="Zoom" data-zoom>
      <button type="button" class="cr-z" data-z="1" aria-label="Vergrößern">+</button>
      <span class="cr-info" aria-live="polite"></span>
      <span class="cr-spacer"></span>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-reset>Automatisch (Fokuspunkt)</button>
    </div>
    <div class="cr-foot">
      <p class="cr-help">Ziehen = verschieben · Mausrad/Trackpad = zoomen · Doppelklick = zurücksetzen</p>
      <button type="button" class="adm-btn adm-btn--ghost" data-cancel>Abbrechen</button>
      <button type="button" class="adm-btn adm-btn--primary" data-save>Übernehmen</button>
    </div>`;
  const view = $('.cr-view', dlg), img = $('img', view), frame = $('.cr-frame', dlg), slider = $('[data-zoom]', dlg), info = $('.cr-info', dlg);
  const W = m.width, H = m.height;
  let st = null;                                // { ar, Fw, Fh, fx, fy, k, kMin, kMax, ix, iy }

  const layout = () => {
    const [a, b] = ratio.split(':').map(Number), ar = a / b;
    const Vw = view.clientWidth, Vh = view.clientHeight, pad = 36;
    let Fw = Vw - pad * 2, Fh = Fw / ar;
    if (Fh > Vh - pad * 2) { Fh = Vh - pad * 2; Fw = Fh * ar; }
    const fx = (Vw - Fw) / 2, fy = (Vh - Fh) / 2;
    const kMin = Math.max(Fw / W, Fh / H), kMax = Math.max(kMin * 1.001, Fw / Math.min(W, 240));
    st = { ar, Fw, Fh, fx, fy, kMin, kMax, k: kMin, ix: 0, iy: 0 };
    frame.style.cssText = `left:${fx}px;top:${fy}px;width:${Fw}px;height:${Fh}px`;
    const c = current(ratio);
    if (c) { st.k = Math.min(kMax, Math.max(kMin, Fw / (c.w * W))); st.ix = fx - c.x * W * st.k; st.iy = fy - c.y * H * st.k; }
    else center();
    apply();
  };
  const center = () => {
    st.k = st.kMin;
    st.ix = st.fx + st.Fw / 2 - (m.focus.x / 100) * W * st.k;
    st.iy = st.fy + st.Fh / 2 - (m.focus.y / 100) * H * st.k;
  };
  const clamp = () => {
    st.k = Math.min(st.kMax, Math.max(st.kMin, st.k));
    st.ix = Math.min(st.fx, Math.max(st.fx + st.Fw - W * st.k, st.ix));
    st.iy = Math.min(st.fy, Math.max(st.fy + st.Fh - H * st.k, st.iy));
  };
  const rect = () => ({ x: (st.fx - st.ix) / (W * st.k), y: (st.fy - st.iy) / (H * st.k), w: st.Fw / (W * st.k), h: st.Fh / (H * st.k) });
  const apply = () => {
    clamp();
    img.style.cssText = `width:${W * st.k}px;height:${H * st.k}px;transform:translate(${st.ix}px,${st.iy}px)`;
    slider.value = Math.round(Math.log(st.k / st.kMin) / Math.log(st.kMax / st.kMin || 2) * 1000) || 0;
    const r = rect(), pw = Math.round(r.w * W), ph = Math.round(r.h * H);
    info.textContent = `${pw} × ${ph} px` + (pw < 1200 ? ' · Auflösung knapp für große Bildschirme' : ' ✓');
    info.classList.toggle('is-warn', pw < 1200);
    $$('[data-ratio]', dlg).forEach(b => {
      b.setAttribute('aria-selected', b.dataset.ratio === ratio);
      b.classList.toggle('is-set', !!current(b.dataset.ratio));
    });
  };
  const touched = () => { pending[ratio] = rect(); };
  const zoomAt = (k, px = st.fx + st.Fw / 2, py = st.fy + st.Fh / 2) => {
    k = Math.min(st.kMax, Math.max(st.kMin, k));
    st.ix = px - (px - st.ix) * k / st.k; st.iy = py - (py - st.iy) * k / st.k; st.k = k;
    apply(); touched();
  };

  // Ziehen (Maus, Stift, Touch) und Pinch-Zoom
  const pts = new Map(); let pinch = null;
  view.addEventListener('pointerdown', e => { view.setPointerCapture(e.pointerId); pts.set(e.pointerId, [e.clientX, e.clientY]); view.classList.add('is-drag'); });
  view.addEventListener('pointermove', e => {
    if (!pts.has(e.pointerId)) return;
    const [ox, oy] = pts.get(e.pointerId); pts.set(e.pointerId, [e.clientX, e.clientY]);
    if (pts.size === 2) {
      const [a, b] = [...pts.values()], dist = Math.hypot(a[0] - b[0], a[1] - b[1]);
      const r = view.getBoundingClientRect();
      if (pinch) zoomAt(st.k * dist / pinch, (a[0] + b[0]) / 2 - r.left, (a[1] + b[1]) / 2 - r.top);
      pinch = dist; return;
    }
    st.ix += e.clientX - ox; st.iy += e.clientY - oy; apply(); touched();
  });
  const up = e => { pts.delete(e.pointerId); pinch = null; if (!pts.size) view.classList.remove('is-drag'); };
  view.addEventListener('pointerup', up); view.addEventListener('pointercancel', up);
  view.addEventListener('wheel', e => {
    e.preventDefault();
    const r = view.getBoundingClientRect();
    zoomAt(st.k * Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0025)), e.clientX - r.left, e.clientY - r.top);
  }, { passive: false });
  view.addEventListener('dblclick', () => { center(); apply(); touched(); });
  view.addEventListener('keydown', e => {
    const step = e.shiftKey ? 50 : 10;
    const map = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
    if (map[e.key]) { e.preventDefault(); st.ix += map[e.key][0]; st.iy += map[e.key][1]; apply(); touched(); }
    else if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomAt(st.k * 1.15); }
    else if (e.key === '-') { e.preventDefault(); zoomAt(st.k / 1.15); }
    else if (e.key === '0') { center(); apply(); touched(); }
  });
  slider.addEventListener('input', () => zoomAt(st.kMin * Math.pow(st.kMax / st.kMin, slider.value / 1000)));
  $$('[data-z]', dlg).forEach(b => b.addEventListener('click', () => zoomAt(st.k * (b.dataset.z > 0 ? 1.25 : 0.8))));
  $('[data-reset]', dlg).addEventListener('click', () => { pending[ratio] = null; layout(); });
  $$('[data-ratio]', dlg).forEach(b => b.addEventListener('click', () => { ratio = b.dataset.ratio; layout(); }));
  $('[data-cancel]', dlg).addEventListener('click', () => dlg.close());
  $('[data-save]', dlg).addEventListener('click', async e => {
    const btn = e.currentTarget; btn.disabled = true;
    try {
      for (const [r, rc] of Object.entries(pending)) {
        const res = await api.crop(m.id, r, rc);
        m = res.item;
        onSaved?.(r, res);
      }
      dlg.close();
      if (Object.keys(pending).length) toast('Zuschnitt gespeichert');
    } catch (ex) { alert(ex.message); btn.disabled = false; }
  });
  const ro = new ResizeObserver(() => st && layout());
  dlg.addEventListener('close', () => ro.disconnect(), { once: true });
  dlg.showModal();
  ro.observe(view);
  (img.complete ? Promise.resolve() : new Promise(r => { img.onload = r; img.onerror = r; })).then(() => { layout(); view.focus(); });
}

/** Aktualisiert alle Bilder eines Mediums/Formats auf der Seite nach einem neuen Zuschnitt (ohne Neuladen) */
function refreshPictures(id, ratio, src) {
  $$(`img[data-media-id="${id}"][data-ratio="${ratio}"]`).forEach(im => {
    const pic = im.closest('picture');
    for (const fmt of ['avif', 'webp']) {
      let s = pic && $(`source[type="image/${fmt}"]`, pic);
      if (!src[fmt]) { s?.remove(); continue; }
      if (!s && pic) { s = d.createElement('source'); s.type = 'image/' + fmt; s.sizes = $('source', pic)?.sizes || '100vw'; pic.insertBefore(s, im); }
      if (s) s.srcset = src[fmt];
    }
    im.src = src.src; im.width = src.width; im.height = src.height;
    [...im.classList].filter(c => /^f[xy]\d+$/.test(c)).forEach(c => im.classList.remove(c));
  });
}

// ============================================================ Vorschaubilder für Videos (Core\VideoThumbs, nur mit ffmpeg)
/*
 * Platzhalter <span class="fx-vthumb" data-vthumb="/admin/api/media/{id}/thumb">: sobald sichtbar, fragt die Verwaltung das
 * Vorschaubild an (der Server erzeugt es beim ersten Mal). Höchstens 2 Anfragen gleichzeitig; „belegt“ → später erneut;
 * Fehler → Platzhalter bleibt. Ergebnisse gelten für alle Mediatheken der Seite (VTHUMBS, nach Medien-ID).
 */
const VTHUMBS = new Map();   // Adresse → {thumb, large} | false
const VTELS = new Set();
const VTPEND = new Set();     // wartende Platzhalter (auch in Schatten-Wurzeln der Seitenleisten)
const vtQueue = [];
let vtActive = 0, vtObserver = null;
function vtApply(url, res) {
  VTHUMBS.set(url, res || false);
  VTPEND.delete(url);
  // Datensätze aller Mediatheken aktualisieren (Quick Look, Informationen, erneutes Zeichnen)
  if (res) FINDERS.forEach(f => (f.items || []).forEach(m => { if (m.thumb_gen === url) { m.thumb = res.thumb; m.large = res.large; m.thumb_gen = null; } }));
  for (const el of VTELS) { if (!el.isConnected) VTELS.delete(el); else if (el.dataset.vthumb === url) { vtShow(el); VTELS.delete(el); } }
}
function vtShow(el) {
  const res = VTHUMBS.get(el.dataset.vthumb);
  if (res === undefined) return false;
  el.classList.remove('is-busy');
  if (res && !el.querySelector('img')) {
    const img = new Image();
    img.alt = ''; img.draggable = false; img.decoding = 'async';
    // Raster/Liste: nach dem Laden wie jedes Vorschaubild (natürliches Seitenverhältnis in der festen Kachel)
    const tile = el.closest('.fx-thumb, .fx-mini, .fx-stack');
    img.onload = () => { if (tile && el.isConnected) el.replaceWith(img); else el.classList.add('is-loaded'); };
    img.onerror = () => { img.remove(); el.classList.add('is-failed'); };
    img.src = res.thumb;
    if (!tile) el.append(img);
  } else if (!res) el.classList.add('is-failed');
  vtObserver?.unobserve(el);
  return true;
}
function vtPump() {
  while (vtActive < 2 && vtQueue.length) {
    const job = vtQueue.shift();
    vtActive++;
    const ac = new AbortController(), to = setTimeout(() => ac.abort(), 25000);   // nie endlos „lädt …“
    fetch(job.url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: ac.signal })
      .finally(() => clearTimeout(to))
      .then(r => r.json()).catch(() => ({ ok: false }))
      .then(r => {
        vtActive--;
        if (r.busy && job.tries < 10) { job.tries++; setTimeout(() => { vtQueue.push(job); vtPump(); }, 1200 + job.tries * 400); }
        else vtApply(job.url, r.ok && r.thumb ? { thumb: r.thumb, large: r.large } : null);
        vtPump();
      });
  }
}
function vtRequest(el) {
  const url = el.dataset.vthumb;
  if (vtShow(el)) return;
  el.classList.add('is-busy');
  if (VTPEND.has(url)) return;   // schon angefragt – Ergebnis gilt für alle Platzhalter dieser Adresse
  VTPEND.add(url);
  vtQueue.push({ url, tries: 0 }); vtPump();
}
/** Platzhalter in scope beobachten (Raster, Liste, Auswahl, Felder) */
function lazyThumbs(scope) {
  const els = [...(scope || d).querySelectorAll('[data-vthumb]')].filter(el => !vtShow(el));
  if (!els.length) return;
  els.forEach(el => VTELS.add(el));
  if (!('IntersectionObserver' in window)) { els.forEach(vtRequest); return; }
  vtObserver ||= new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { vtObserver.unobserve(e.target); vtRequest(e.target); } }), { rootMargin: '200px' });
  els.forEach(el => vtObserver.observe(el));
}
// Kennzeichen „Video“ auf der Kachel (Standbild allein sieht wie ein Foto aus)
const VBADGE = '<span class="fx-vbadge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l10.5-6.5z"/></svg></span>';
const vthumbHtml = (m, cls = '') => `<span class="fx-vthumb${cls ? ' ' + cls : ''}" data-vthumb="${esc(m.thumb_gen)}">${SVG.video}</span>`;

// ============================================================ Finder
// Symbole aus dem Sprite (Phosphor duotone, Core\Icons)
const SVG = {
  grid: ico('squares-four'), list: ico('list-bullets'), all: ico('folder'), image: ico('image'), pdf: ico('file-pdf'),
  video: ico('video-camera'), warn: ico('warning'), col: ico('folders'), search: ico('magnifying-glass'),
  up: ico('upload-simple'), side: ico('sidebar-simple'), info: ico('info'),
};

class Finder {
  /** opts: { mode: 'library'|'pick', kind: 'image'|null, onPick(media) } */
  constructor(root, opts = {}) {
    this.root = root; this.opts = opts; this.mode = opts.mode || 'library';
    this.pool = ''; POOL = '';
    // Pool-Datei gewählt → Verweis auf dieser Website anlegen und diesen zurückgeben
    const pick = opts.onPick;
    if (pick) this.opts = { ...opts, onPick: async m => pick(this.pool ? (await api.use(m.id)).item : m) };
    this.src = { type: opts.kind === 'image' || opts.kind === 'visual' ? 'kind' : 'all', value: opts.kind === 'image' || opts.kind === 'visual' ? opts.kind : '' };
    this.q = ''; this.items = []; this.sel = new Set(); this.anchor = null; this.active = null; this.meta = null;
    this.view = store.get('view', 'grid'); this.size = store.get('size', 132); this.sort = store.get('sort', { key: 'created_at', dir: -1 });
    this.build(); this.load();
  }

  // ---------------------------------------------------------- Aufbau
  build() {
    const r = this.root;
    r.classList.add('fx', 'fx--' + this.mode);
    r.innerHTML = `
      <aside class="fx-side" aria-label="Orte"></aside>
      <section class="fx-main">
        <header class="fx-bar">
          <button type="button" class="fx-tbtn fx-sidetoggle" data-sidetoggle aria-label="Seitenleiste ein-/ausblenden">${SVG.side}</button>
          <div class="fx-title"><strong data-title>Alle Medien</strong><small data-count></small></div>
          <div class="fx-seg" role="group" aria-label="Darstellung">
            <button type="button" data-view="grid" aria-label="Symbole" title="Symbole">${SVG.grid}</button>
            <button type="button" data-view="list" aria-label="Liste" title="Liste">${SVG.list}</button>
          </div>
          <input type="range" class="fx-size" min="88" max="220" step="4" value="${this.size}" aria-label="Symbolgröße" data-size>
          <label class="fx-search">${SVG.search}<input type="search" placeholder="Suchen" aria-label="Medien durchsuchen" data-q></label>
          <button type="button" class="fx-tbtn fx-infotoggle" data-infotoggle aria-label="Informationen ein-/ausblenden">${SVG.info}</button>
          <span class="fx-sources" data-sources hidden></span>
          <button type="button" class="adm-btn adm-btn--primary adm-btn--small fx-upbtn" data-upload>${SVG.up}<span>Hochladen</span></button>
        </header>
        <div class="fx-listhead" aria-hidden="true"></div>
        <div class="fx-items" role="listbox" aria-multiselectable="true" aria-label="Dateien" tabindex="0"></div>
        <footer class="fx-status" aria-live="polite"></footer>
        <div class="fx-dropmsg" aria-hidden="true"><span>${SVG.up} Loslassen zum Hochladen</span></div>
      </section>
      <aside class="fx-info" aria-label="Informationen"><button type="button" class="fx-info__close" data-info-close aria-label="Informationen schließen" title="Schließen">✕</button><div class="fx-info__body"></div></aside>
      <section class="fx-uploads" hidden aria-label="Hochladen">
        <header><strong>Hochladen</strong><span data-updest></span><button type="button" class="mu-x" data-upclose aria-label="Schließen">✕</button></header>
        <div data-uploader></div>
      </section>`;
    this.$items = $('.fx-items', r); this.$info = $('.fx-info__body', r); this.$side = $('.fx-side', r);
    this.uploader = new Uploader($('[data-uploader]', r), {
      accept: this.opts.kind === 'image' || this.opts.kind === 'visual' ? this.opts.kind : null,
      collection: () => (this.src.type === 'collection' ? +this.src.value : 0),
      tags: () => (this.src.type === 'tag' ? this.src.value : ''),
      onDone: m => { this.load(m.id); if (this.mode === 'pick' && this.uploader.items.filter(i => i.status === 'wait' || i.status === 'up').length === 0) this.opts.onPick?.(m); },
      onChange: n => { $('.fx-uploads', r).hidden = !n; this.updateUploadDest(); },
    });
    this.bind();
    this.applyView();
  }

  /** Knopf „Importieren aus …“: Quellen der Erweiterungen (Hook sources) – nur mit Schreibrecht im aktuellen Ort */
  renderSources() {
    const el = $('[data-sources]', this.root);
    if (!el) return;
    this.sources = this.ro ? [] : hook('sources', this).flat().filter(s => s && s.label && typeof s.run === 'function');
    const key = this.sources.map(s => s.label).join('\n');
    if (el.dataset.key === key) return;   // unverändert: Knopf behalten (Fokus bleibt nach dem Neuladen erhalten)
    el.dataset.key = key;
    el.hidden = !this.sources.length;
    el.innerHTML = this.sources.length ? `<button type="button" class="adm-btn adm-btn--small fx-srcbtn" data-srcmenu aria-haspopup="menu">${ico('download-simple')}<span>${esc(t('Importieren aus …'))}</span></button>` : '';
  }

  updateUploadDest() {
    const el = $('[data-updest]', this.root);
    const c = this.src.type === 'collection' ? this.meta?.collections.find(c => String(c.id) === String(this.src.value)) : null;
    el.textContent = c ? `→ Sammlung „${c.name}“` : this.src.type === 'tag' ? `→ mit Tag „${this.src.value}“` : '';
  }

  bind() {
    const r = this.root;
    $('[data-upload]', r).addEventListener('click', () => this.uploader.choose());
    // Importieren aus externen Quellen (Erweiterungen, Hook sources): Menü unter dem Knopf, Fokus danach zurück
    $('[data-sources]', r).addEventListener('click', e => {
      const b = e.target.closest('[data-srcmenu]');
      if (!b || !this.sources?.length) return;
      const rc = b.getBoundingClientRect();
      this.menu(rc.left, rc.bottom + 4, this.sources.map(s => [s.label, () => { try { s.run(this, b); } catch (ex) { console.error(ex); toast(ex.message); } }]));
    });
    $('[data-upclose]', r).addEventListener('click', () => { this.uploader.items = this.uploader.items.filter(i => i.status === 'up'); this.uploader.render(); $('.fx-uploads', r).hidden = !this.uploader.items.length; });
    // Mediathek: Orte/Sammlungen stehen in der Seitenleiste (Drill-down) – schmal öffnet der Knopf die Schublade (_drawer.js)
    const inDrawer = () => !!this.$side.closest('.adm-side');
    $('[data-sidetoggle]', r).addEventListener('click', () => inDrawer() ? document.dispatchEvent(new CustomEvent('adm:drawer', { detail: 'open' })) : r.classList.toggle('is-side-open'));
    // Informationen ein-/ausblenden: Zustand sichtbar (aria-pressed) und gemerkt; ✕ im Panel schließt (schmal: Auswahl aufheben)
    const infoBtn = $('[data-infotoggle]', r);
    const setInfo = hidden => {
      r.classList.toggle('is-info-hidden', hidden);
      infoBtn?.setAttribute('aria-pressed', String(!hidden));
      try { localStorage.setItem('fx-info-hidden', hidden ? '1' : ''); } catch { /* privates Fenster */ }
    };
    try { if (localStorage.getItem('fx-info-hidden') === '1' && this.mode === 'library') r.classList.add('is-info-hidden'); } catch { /* */ }
    infoBtn?.setAttribute('aria-pressed', String(!r.classList.contains('is-info-hidden')));
    infoBtn?.addEventListener('click', () => setInfo(!r.classList.contains('is-info-hidden')));
    $('[data-info-close]', r)?.addEventListener('click', () => {
      if (matchMedia('(max-width:1180px)').matches) { this.sel.clear(); this.syncSel(); this.$items.focus(); }
      else setInfo(true);
    });
    // Höhe der Mediathek nach dem tatsächlichen Abstand oben (Hinweise, Leisten) – kein doppeltes Scrollen
    if (this.mode === 'library') {
      const top = () => r.style.setProperty('--fx-top', Math.max(0, Math.round(r.getBoundingClientRect().top + scrollY)) + 'px');
      top(); addEventListener('resize', top);
    }
    $$('[data-view]', r).forEach(b => b.addEventListener('click', () => { this.view = b.dataset.view; store.set('view', this.view); this.applyView(); this.render(); }));
    $('[data-size]', r).addEventListener('input', e => { this.size = +e.target.value; store.set('size', this.size); this.applyView(); });
    let t;
    $('[data-q]', r).addEventListener('input', e => { clearTimeout(t); t = setTimeout(() => { this.q = e.target.value; this.load(); }, 200); });

    // Seitenleiste
    this.$side.addEventListener('click', async e => {
      const sc = e.target.closest('[data-scope]');
      if (sc) { this.pool = POOL = sc.dataset.scope; this.src = { type: this.opts.kind === 'image' || this.opts.kind === 'visual' ? 'kind' : 'all', value: this.opts.kind === 'image' || this.opts.kind === 'visual' ? this.opts.kind : '' }; this.sel.clear(); this.load(); return; }
      const b = e.target.closest('[data-src]');
      if (b) { this.src = { type: b.dataset.src, value: b.dataset.value || '' }; this.sel.clear(); this.root.classList.remove('is-side-open'); if (inDrawer()) document.dispatchEvent(new CustomEvent('adm:drawer', { detail: 'close' })); this.load(); return; }
      if (e.target.closest('[data-newcol]')) {
        const n = prompt('Name der neuen Sammlung:');
        if (n?.trim()) { const res = await api.collection(n.trim()); this.src = { type: 'collection', value: String(res.id) }; this.load(); }
      }
      const menu = e.target.closest('[data-colmenu]');
      if (menu) this.collectionMenu(menu);
    });
    // Dateien auf eine Sammlung ziehen
    this.$side.addEventListener('dragover', e => {
      const t = e.target.closest('[data-src="collection"]');
      if (t && e.dataTransfer.types.includes('application/x-klxm-studio-media')) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; t.classList.add('is-drop'); }
    });
    this.$side.addEventListener('dragleave', e => e.target.closest('[data-src]')?.classList.remove('is-drop'));
    this.$side.addEventListener('drop', async e => {
      const t = e.target.closest('[data-src="collection"]'); if (!t) return;
      t.classList.remove('is-drop');
      const ids = JSON.parse(e.dataTransfer.getData('application/x-klxm-studio-media') || '[]');
      if (!ids.length) return;
      e.preventDefault(); e.stopPropagation();
      await api.bulk({ ids, action: 'collect', collection: +t.dataset.value });
      toast(`${ids.length} ${ids.length === 1 ? 'Datei' : 'Dateien'} → „${t.dataset.name}“`);
      this.load();
    });

    // Auswahl mit Maus
    this.$items.addEventListener('click', e => {
      const it = e.target.closest('[data-id]');
      if (!it) { if (e.target === this.$items) { this.sel.clear(); this.syncSel(); } return; }
      const id = +it.dataset.id;
      if (e.shiftKey && this.anchor != null) this.selectRange(this.anchor, id);
      else if (e.metaKey || e.ctrlKey) { this.sel.has(id) ? this.sel.delete(id) : this.sel.add(id); this.anchor = id; }
      else { this.sel = new Set([id]); this.anchor = id; }
      this.active = id; this.syncSel();
    });
    this.$items.addEventListener('dblclick', e => {
      const it = e.target.closest('[data-id]'); if (!it) return;
      const m = this.byId(+it.dataset.id);
      this.mode === 'pick' ? this.opts.onPick?.(m) : this.edit(m.id);
    });
    this.$items.addEventListener('contextmenu', e => {
      const it = e.target.closest('[data-id]'); if (!it) return;
      e.preventDefault();
      const id = +it.dataset.id;
      if (!this.sel.has(id)) { this.sel = new Set([id]); this.anchor = this.active = id; this.syncSel(); }
      this.contextMenu(e.clientX, e.clientY);
    });
    this.$items.addEventListener('keydown', e => this.keys(e));
    // Dateien aus der Mediathek ziehen (auf Sammlungen)
    this.$items.addEventListener('dragstart', e => {
      const it = e.target.closest('[data-id]'); if (!it) return;
      const id = +it.dataset.id;
      const ids = this.sel.has(id) ? [...this.sel] : [id];
      e.dataTransfer.setData('application/x-klxm-studio-media', JSON.stringify(ids));
      e.dataTransfer.effectAllowed = 'copy';
      if (ids.length > 1) { const g = d.createElement('div'); g.className = 'fx-dragghost'; g.textContent = `${ids.length} Dateien`; box().append(g); e.dataTransfer.setDragImage(g, 20, 20); setTimeout(() => g.remove()); }
    });
    // Dateien vom Computer hineinziehen
    let depth = 0;
    const main = $('.fx-main', r);
    const isFiles = e => e.dataTransfer?.types.includes('Files');
    r.addEventListener('dragenter', e => { if (isFiles(e)) { depth++; r.classList.add('is-dropping'); } });
    r.addEventListener('dragleave', e => { if (isFiles(e) && --depth <= 0) { depth = 0; r.classList.remove('is-dropping'); } });
    r.addEventListener('dragover', e => { if (isFiles(e)) e.preventDefault(); });
    r.addEventListener('drop', e => { if (!isFiles(e)) return; e.preventDefault(); if (this.ro) { r.classList.remove('is-dropping'); depth = 0; toast('Hier dürfen Sie keine Dateien hochladen.'); return; } depth = 0; r.classList.remove('is-dropping'); this.uploader.add(e.dataTransfer.files); });
    main.addEventListener('scroll', () => this.closeMenu(), { passive: true });
    FINDERS.push(this);
  }

  /** Aus der Zwischenablage einfügen (⌘/Strg+V): Bildschirmfotos, kopierte Bilder und Dateien */
  pasteFiles(list) {
    if (this.ro) { toast('Hier dürfen Sie keine Dateien hochladen.'); return; }
    const n = new Date(), z = v => String(v).padStart(2, '0');
    const stamp = `${n.getFullYear()}-${z(n.getMonth() + 1)}-${z(n.getDate())} ${z(n.getHours())}.${z(n.getMinutes())}.${z(n.getSeconds())}`;
    const files = [...list].map((f, i) => {
      // Bildschirmfotos heißen im Browser meist „image.png“ – sprechender Name, damit man sie wiederfindet
      if (!f.name || /^image\.(png|jpe?g|gif|webp)$/i.test(f.name)) {
        const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        return new File([f], `Eingefügt ${stamp}${list.length > 1 ? ' ' + (i + 1) : ''}.${ext}`, { type: f.type });
      }
      return f;
    });
    this.uploader.add(files);
    toast(files.length === 1 ? '1 Datei aus der Zwischenablage – bitte Alt-Text ergänzen' : `${files.length} Dateien aus der Zwischenablage`);
  }

  applyView() {
    this.root.dataset.view = this.view;
    this.root.style.setProperty('--fx-size', this.size + 'px');
    $$('[data-view]', this.root).forEach(b => b.setAttribute('aria-pressed', b.dataset.view === this.view));
    $('[data-size]', this.root).hidden = this.view !== 'grid';
  }

  // ---------------------------------------------------------- Daten
  async load(selectId) {
    const p = { q: this.q };
    if (this.src.type === 'kind') p.kind = this.src.value;
    if (this.src.type === 'noalt') p.noalt = '1';
    // Prüf-Filter (Seitenleiste „Prüfen“): noalt, missing:en, notitle, nocaptions, notranscript
    if (this.src.type === 'check') { const [k, l] = this.src.value.split(':'); if (k === 'missing') p.missing_lang = l; else if (k === 'x') p.check = l; else p[k] = '1'; }
    if (this.src.type === 'collection') p.collection = this.src.value;
    if (this.src.type === 'tag') p.tag = this.src.value;
    if (this.opts.kind === 'image') p.kind = 'image';
    // 'visual' (Felder für Bild oder Video): nur Bilder/Videos – gewählte Unterart (Bilder bzw. Videos) bleibt erhalten
    if (this.opts.kind === 'visual' && !['image', 'video', 'visual'].includes(p.kind)) p.kind = 'visual';
    const seq = this.loadSeq = (this.loadSeq || 0) + 1;
    const data = await api.list(p);
    if (seq !== this.loadSeq) return;   // inzwischen neu geladen (z. B. Prüf-Filter aus der Übersicht) – ältere Antwort verwerfen
    this.meta = data;
    this.ro = data.can_edit === false;   // z. B. Pool ohne Recht „Geteilte Medien pflegen“: nur ansehen und verwenden
    this.root.classList.toggle('is-ro', this.ro);
    this.items = this.sorted(data.items);
    const ids = new Set(this.items.map(m => m.id));
    this.sel = new Set([...this.sel].filter(id => ids.has(id)));
    if (selectId && ids.has(selectId)) { this.sel = new Set([selectId]); this.anchor = this.active = selectId; }
    hook('loaded', this, data);
    this.renderSide(); this.render(); this.updateUploadDest(); this.renderSources();
  }
  sorted(items) {
    const { key, dir } = this.sort;
    const val = m => key === 'name' ? m.display.toLowerCase() : key === 'size' ? m.bytes : key === 'type' ? m.type : (m[key] || '');
    return [...items].sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * dir);
  }
  byId(id) { return this.items.find(m => m.id === id); }

  // ---------------------------------------------------------- Seitenleiste
  renderSide() {
    const { counts, collections, tags } = this.meta;
    const is = (t, v = '') => this.src.type === t && String(this.src.value) === String(v);
    const row = (t, v, icon, label, n, extra = '') => `<li><button type="button" class="fx-src${is(t, v) ? ' is-active' : ''}" data-src="${t}" data-value="${esc(v)}" ${extra}${is(t, v) ? ' aria-current="true"' : ''}>${icon}<span>${esc(label)}</span>${n != null ? `<small>${n}</small>` : ''}</button></li>`;
    const img = this.opts.kind === 'image';
    const pools = this.meta.pools || [];
    const cur = pools.find(p => p.key === this.pool);
    this.$side.innerHTML = `
      ${pools.length ? `<div class="fx-scope" role="group" aria-label="Mediathek wählen">
        <button type="button" data-scope="" aria-pressed="${!this.pool}">Diese Website</button>
        ${pools.map(p => `<button type="button" data-scope="${esc(p.key)}" aria-pressed="${this.pool === p.key}" title="Geteilte Medien">⇄ ${esc(p.label)}</button>`).join('')}
      </div>${cur ? `<p class="fx-scope__note">${this.ro ? 'Geteilt – nur verwenden. Pflegen dürfen Personen mit dem Recht „Geteilte Medien pflegen“.' : 'Geteilt – Änderungen wirken auf allen Websites, die diesen Pool nutzen.'}</p>` : ''}` : ''}
      <h3>${cur ? esc(cur.label) : 'Mediathek'}</h3>
      <ul>
        ${this.opts.kind === 'visual' ? row('kind', 'visual', SVG.all, t('Bilder und Videos'), (counts.image || 0) + (counts.video || 0)) + row('kind', 'image', SVG.image, 'Bilder', counts.image) + row('kind', 'video', SVG.video || SVG.all, 'Videos', counts.video) : img ? row('kind', 'image', SVG.image, 'Bilder', counts.image) : row('all', '', SVG.all, 'Alle Medien', counts.alle)
          + row('kind', 'image', SVG.image, 'Bilder', counts.image) + row('kind', 'pdf', SVG.pdf, 'PDF-Dokumente', counts.pdf)
          + (counts.video ? row('kind', 'video', SVG.video, 'Videos', counts.video) : '')
          + (counts.audio ? row('kind', 'audio', ico('music-notes'), t('Audio'), counts.audio) : '')}
      </ul>
      ${this.checksHtml(row)}
      <h3>Sammlungen <button type="button" class="fx-add" data-newcol aria-label="Neue Sammlung" title="Neue Sammlung">+</button></h3>
      <ul>${collections.map(c => `<li class="fx-colrow">${row('collection', c.id, SVG.col, c.name, c.count, `data-name="${esc(c.name)}"`).slice(4, -5)}<button type="button" class="fx-more" data-colmenu="${c.id}" aria-label="Sammlung „${esc(c.name)}“ bearbeiten">${ico('dots-three')}</button></li>`).join('')
        || '<li class="fx-empty">Noch keine – mit + anlegen, dann Dateien hineinziehen.</li>'}</ul>
      ${Object.keys(tags).length ? `<h3>Tags</h3><ul>${Object.entries(tags).map(([t, n]) => row('tag', t, `<span class="fx-dot" style="background:${tagColor(t)}"></span>`, t, n)).join('')}</ul>` : ''}`;
  }
  /**
   * Gruppe „Prüfen“ (Kontext Website bzw. Pool): zeigt nur Prüfungen mit Treffern. Ist alles in Ordnung,
   * bleibt eine eingeklappte Zeile „Prüfen ✓“ – aufgeklappt stehen dann alle Prüfungen (mit 0) zur Kontrolle da.
   */
  checksHtml(row) {
    const c = this.meta.counts || {}, img = this.opts.kind === 'image', ai = this.meta.ai || {};
    const list = [];
    const chk = (v, icon, label, n, show = true) => { if (show) list.push({ v, n: n || 0, html: row('check', v, icon, label, n || 0, n ? 'data-warn' : 'data-zero') }); };
    chk('noalt', SVG.warn, t('Ohne Alt-Text'), c.noalt);
    Object.entries(this.meta.languages || {}).forEach(([code, label]) => chk('missing:' + code, ico('translate'), t('Alt-Text fehlt in {lang}', { lang: label }), c['missing_' + code]));
    chk('notitle', ico('text-t'), t('Ohne Titel'), c.notitle);
    chk('nocaptions', ico('article'), t('Videos ohne Untertitel'), c.nocaptions, !img && ai.captions !== false);
    chk('notranscript', ico('music-notes'), t('Audio ohne Transkript'), c.notranscript, !img && ai.captions !== false && c.audio > 0);
    // Prüf-Filter aktiver Erweiterungen (Extension::mediaChecks, Anzahl in counts.x_{key})
    (this.meta.ext_checks || []).forEach(x => chk('x:' + x.key, ico(x.icon || 'warning'), x.label, c['x_' + x.key], !(img && x.kind && x.kind !== 'image')));
    const total = list.reduce((a, i) => a + i.n, 0);
    const active = this.src.type === 'check' || this.src.type === 'noalt';
    // Mit Treffern: nur Prüfungen mit Treffern (plus die gerade gewählte); ohne Treffer: alle, aber eingeklappt
    const items = (total ? list.filter(i => i.n || (active && this.src.value === i.v)) : list).map(i => i.html).join('');
    const link = (href, label) => `<li><a class="fx-src fx-src--ai" href="${esc(href)}">${ico('sparkle')}<span>${esc(label)}</span></a></li>`;
    const links = total && this.mode === 'library' ? (ai.alt && (c.noalt || Object.keys(this.meta.languages || {}).some(l => c['missing_' + l])) ? link(ai.alt, t('Mit KI ergänzen')) : '') + (ai.subtitles && !img && (c.nocaptions || c.notranscript) ? link(ai.subtitles, t('Untertitel mit KI')) : '') : '';
    const open = total > 0 || active;
    return `<details class="fx-checkgrp"${open ? ' open' : ''}><summary><span>${esc(t('Prüfen'))}</span>${total ? `<span class="fx-checkgrp__n">${total}</span>` : `<span class="fx-checkgrp__ok">${ico('check')}<span class="sr-only">${esc(t('alles in Ordnung'))}</span></span>`}</summary>`
      + `<ul class="fx-checks">${items}${links}</ul></details>`;
  }
  async collectionMenu(btn) {
    const id = btn.dataset.colmenu, c = this.meta.collections.find(x => String(x.id) === id);
    const r = btn.getBoundingClientRect();
    this.menu(r.left, r.bottom + 4, [
      ['Umbenennen …', async () => { const n = prompt('Neuer Name:', c.name); if (n?.trim()) { await api.renameCollection(id, n.trim()); this.load(); } }],
      ['Sammlung löschen', async () => { if (await ask({ title: `Sammlung „${c.name}“ löschen?`, body: 'Die Dateien bleiben erhalten.', ok: 'Sammlung löschen' })) { await api.deleteCollection(id); if (this.src.type === 'collection' && this.src.value === id) this.src = { type: 'all', value: '' }; this.load(); } }, true],
    ]);
  }

  // ---------------------------------------------------------- Dateien
  title() {
    const s = this.src, m = this.meta;
    if (s.type === 'kind') return { image: 'Bilder', pdf: 'PDF-Dokumente', video: 'Videos', audio: t('Audio') }[s.value];
    if (s.type === 'noalt') return 'Ohne Alt-Text';
    if (s.type === 'check') {
      const [k, l] = s.value.split(':');
      return k === 'missing' ? t('Alt-Text fehlt in {lang}', { lang: m.languages?.[l] || l })
        : k === 'x' ? (m.ext_checks || []).find(x => x.key === l)?.label || ''
        : { noalt: t('Ohne Alt-Text'), notitle: t('Ohne Titel'), nocaptions: t('Videos ohne Untertitel'), notranscript: t('Audio ohne Transkript') }[k] || '';
    }
    if (s.type === 'collection') return m.collections.find(c => String(c.id) === String(s.value))?.name || 'Sammlung';
    if (s.type === 'tag') return 'Tag: ' + s.value;
    return 'Alle Medien';
  }
  thumb(m) {
    if (m.thumb) return `<img src="${esc(m.thumb)}" alt="" loading="lazy" draggable="false"${m.svg ? ' class="is-svg"' : ''} style="object-position:${m.focus.x}% ${m.focus.y}%">`;
    if (m.kind === 'video' && m.thumb_gen) return vthumbHtml(m);   // Vorschaubild wird beim Sichtbarwerden erzeugt (ffmpeg)
    return `<span class="fx-doc fx-doc--${m.kind}"><b>${esc(m.type)}</b>${m.pages ? `<small>${m.pages} S.</small>` : ''}</span>`;
  }
  render() {
    $('[data-title]', this.root).textContent = this.title();
    $('[data-count]', this.root).textContent = `${this.items.length} ${this.items.length === 1 ? 'Objekt' : 'Objekte'}`;
    const head = $('.fx-listhead', this.root);
    const sortBtn = (k, l) => `<button type="button" data-sort="${k}" class="${this.sort.key === k ? 'is-sorted' + (this.sort.dir < 0 ? ' is-desc' : '') : ''}">${l}</button>`;
    head.innerHTML = this.view === 'list' ? sortBtn('name', 'Name') + sortBtn('type', 'Art') + sortBtn('size', 'Größe') + '<span>Tags</span>' + sortBtn('updated_at', 'Geändert') : '';
    head.removeAttribute('aria-hidden');
    head.onclick = e => { const b = e.target.closest('[data-sort]'); if (!b) return; const k = b.dataset.sort; this.sort = { key: k, dir: this.sort.key === k ? -this.sort.dir : (k === 'name' || k === 'type' ? 1 : -1) }; store.set('sort', this.sort); this.items = this.sorted(this.items); this.render(); };
    this.$items.innerHTML = this.items.map(m => {
      const warn = m.missing_alt ? `<span class="fx-warn" title="Alt-Text fehlt">${SVG.warn}<span class="adm-sr">Alt-Text fehlt</span></span>` : '';
      const dots = m.tags.slice(0, 4).map(t => `<span class="fx-dot" style="background:${tagColor(t)}" title="${esc(t)}"></span>`).join('');
      const ext = hook('badge', m, this).join('');   // Erweiterungen: z. B. Poster, Fortschrittsring
      return this.view === 'grid'
        ? `<div class="fx-item" role="option" id="fx-i-${m.id}" data-id="${m.id}" aria-selected="false" draggable="true">
            <span class="fx-thumb">${this.thumb(m)}${m.kind === 'video' ? VBADGE : ''}${warn}${ext}</span>
            <span class="fx-name"><span>${esc(m.display)}</span></span>
            <span class="fx-dots">${dots}</span></div>`
        : `<div class="fx-row" role="option" id="fx-i-${m.id}" data-id="${m.id}" aria-selected="false" draggable="true">
            <span class="fx-cname"><span class="fx-mini">${this.thumb(m)}${ext}</span><span class="fx-name"><span>${esc(m.display)}</span></span>${warn}</span>
            <span>${esc(m.type)}${m.pages ? ` · ${m.pages} S.` : ''}</span><span>${esc(m.size)}</span>
            <span class="fx-dots">${dots}${m.tags.length ? `<span class="fx-tagtext">${esc(m.tags.join(', '))}</span>` : ''}</span>
            <span>${fmtDate(m.updated_at || m.created_at)}</span></div>`;
    }).join('') || `<div class="fx-none">${this.q ? 'Keine Treffer.' : `Hier ist noch nichts.<br><small>Dateien hierher ziehen oder „Hochladen“ wählen.</small>`}</div>`;
    lazyThumbs(this.$items);
    this.syncSel();
  }
  syncSel() {
    $$('[data-id]', this.$items).forEach(el => {
      const on = this.sel.has(+el.dataset.id);
      el.setAttribute('aria-selected', on);
      el.classList.toggle('is-active', +el.dataset.id === this.active);
    });
    if (this.active && this.sel.has(this.active)) {
      this.$items.setAttribute('aria-activedescendant', 'fx-i-' + this.active);
      $('#fx-i-' + this.active, this.$items)?.scrollIntoView({ block: 'nearest' });
    } else this.$items.removeAttribute('aria-activedescendant');
    this.root.classList.toggle('has-sel', this.sel.size > 0);
    // Mediathek: einzeln ausgewählte Datei steht in der Adresse (#m123) – Favoriten, Neuladen, Link teilen
    if (this.mode === 'library') {
      const want = this.sel.size === 1 ? '#m' + [...this.sel][0] : '', cur = /^#m\d+$/.test(location.hash) ? location.hash : '';
      if (want !== cur && (want || cur)) {
        history.replaceState(null, '', location.pathname + location.search + (want || (cur ? '' : location.hash)));
        d.dispatchEvent(new Event('adm:location'));
      }
    }
    const n = this.sel.size, bytes = [...this.sel].reduce((s, id) => s + (this.byId(id)?.bytes || 0), 0);
    $('.fx-status', this.root).textContent = n ? `${n} von ${this.items.length} ausgewählt · ${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${this.items.length} ${this.items.length === 1 ? 'Objekt' : 'Objekte'}`;
    this.renderInfo();
  }
  selectRange(a, b) {
    const ids = this.items.map(m => m.id), i = ids.indexOf(a), j = ids.indexOf(b);
    this.sel = new Set(ids.slice(Math.min(i, j), Math.max(i, j) + 1));
  }
  cols() {
    const els = $$('[data-id]', this.$items); if (els.length < 2 || this.view === 'list') return 1;
    const top = els[0].offsetTop; let n = 0;
    for (const el of els) { if (el.offsetTop !== top) break; n++; }
    return n;
  }
  keys(e) {
    const ids = this.items.map(m => m.id); if (!ids.length) return;
    const mod = e.metaKey || e.ctrlKey;
    let i = ids.indexOf(this.active ?? ids[0]);
    const step = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: this.cols(), ArrowUp: -this.cols() }[e.key];
    if (step !== undefined) {
      e.preventDefault();
      if (this.active == null) i = -step > 0 ? ids.length : -1;
      const next = ids[Math.max(0, Math.min(ids.length - 1, i + step))];
      if (e.shiftKey) { this.selectRange(this.anchor ?? next, next); } else { this.sel = new Set([next]); this.anchor = next; }
      this.active = next; this.syncSel();
    } else if (e.key === ' ') { e.preventDefault(); if (this.active) this.quickLook(this.active); }
    else if (mod && e.key.toLowerCase() === 'a') { e.preventDefault(); this.sel = new Set(ids); this.syncSel(); }
    else if ((e.key === 'Backspace' || e.key === 'Delete') && this.sel.size && this.mode === 'library' && !this.ro) { e.preventDefault(); this.deleteSel(); }
    else if (e.key === 'Enter' && this.active) {
      e.preventDefault();
      this.mode === 'pick' ? this.opts.onPick?.(this.byId(this.active)) : this.edit(this.active);
    } else if (e.key === 'Escape' && this.sel.size) { this.sel.clear(); this.syncSel(); }
  }
  async deleteSel() {
    const ids = [...this.sel];
    const used = ids.length === 1 ? (await api.detail(ids[0])).usages : [];
    // Verwendete Dateien lassen sich nicht löschen (Server prüft ebenso, Media::deleteBlocked)
    if (used.length) { await ask({ title: usedMsg(this.byId(ids[0]).display, used), ok: t('Verstanden'), danger: false, cancel: false }); return; }
    const msg = ids.length === 1 ? `„${this.byId(ids[0]).display}“ endgültig löschen?` : `${ids.length} Dateien endgültig löschen?\n\nVerwendete Dateien bleiben erhalten.`;
    if (!(await ask({ title: msg, ok: 'Löschen' }))) return;
    if (ids.length === 1) {
      try { await api.del(ids[0]); this.sel.clear(); toast('Gelöscht'); this.load(); } catch (ex) { await this.deleteRefused(ex, this.byId(ids[0]).display); }
      return;
    }
    try {
      const res = await api.bulk({ ids, action: 'delete' });
      this.sel.clear(); toast(res?.kept ? res.message : 'Gelöscht'); this.load();
    } catch (ex) { toast(ex.message); }
  }
  /** Server lehnt ab (z. B. geteilte Datei auf einer anderen Website verwendet): Fundstellen wie beim Einzellöschen zeigen */
  async deleteRefused(ex, name) {
    if (ex?.data?.usages?.length) await ask({ title: usedMsg(name, ex.data.usages), ok: t('Verstanden'), danger: false, cancel: false });
    else toast(ex.message);
  }

  // ---------------------------------------------------------- Kontextmenü
  menu(x, y, entries) {
    this.closeMenu();
    const m = d.createElement('div');
    m.className = 'fx-menu'; m.setAttribute('role', 'menu');
    m.innerHTML = entries.map(([l, , danger], i) => l === '-' ? '<hr>' : `<button type="button" role="menuitem" data-i="${i}"${danger ? ' class="is-danger"' : ''}>${esc(l)}</button>`).join('');
    // Im Auswahldialog (modal) ins <dialog> selbst – außerhalb wäre das Menü inert
    (this.root.closest('dialog') || box()).append(m);
    const r = m.getBoundingClientRect();
    m.style.left = Math.min(x, innerWidth - r.width - 8) + 'px'; m.style.top = Math.min(y, innerHeight - r.height - 8) + 'px';
    m.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) { this.closeMenu(); entries[+b.dataset.i][1](); } });
    m.addEventListener('keydown', e => {
      const bs = $$('button', m), i = bs.indexOf(m.getRootNode().activeElement);   // auch im Shadow DOM
      if (e.key === 'ArrowDown') { e.preventDefault(); bs[(i + 1) % bs.length].focus(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); bs[(i - 1 + bs.length) % bs.length].focus(); }
      if (e.key === 'Escape') { this.closeMenu(); this.$items.focus(); }
    });
    $('button', m)?.focus();
    this._menu = m;
    setTimeout(() => d.addEventListener('pointerdown', this._off = ev => { if (!inPath(ev, m)) this.closeMenu(); }));
  }
  closeMenu() { this._menu?.remove(); this._menu = null; if (this._off) d.removeEventListener('pointerdown', this._off); }
  contextMenu(x, y) {
    const ids = [...this.sel], one = ids.length === 1 ? this.byId(ids[0]) : null;
    const e = [];
    if (this.mode === 'pick' && one) e.push(['Auswählen', () => this.opts.onPick?.(one)]);
    if (this.ro) {
      if (one) e.push(['Quick Look', () => this.quickLook(one.id)], ['Original öffnen', () => open(one.kind === 'pdf' ? one.viewer : one.url, '_blank', 'noopener')]);
      if (e.length) this.menu(x, y, e);
      return;
    }
    if (one) e.push(['Bearbeiten …', () => this.edit(one.id)], ['Quick Look', () => this.quickLook(one.id)]);
    if (one?.kind === 'image' && !one.svg) e.push(['Zuschneiden …', () => crop(one, Object.keys(this.meta.ratios)[0], () => this.load())]);
    if (one?.kind === 'image' && !one.editable) e.push([t('Bild bearbeiten …'), () => this.editImage(one)]);
    if (one) e.push(['Datei ersetzen …', () => this.replace(one)], ['Original öffnen', () => open(one.kind === 'pdf' ? one.viewer : one.url, '_blank', 'noopener')]);
    if (this.src.type === 'collection') e.push(['Aus Sammlung entfernen', async () => { await api.bulk({ ids, action: 'uncollect', collection: +this.src.value }); this.load(); }]);
    // Vorhandene Dateien der Website in einen geteilten Pool verschieben (Verwendungen bleiben erhalten)
    if (!this.pool && this.meta.can_share && ids.length) for (const p of (this.meta.pools || []).filter(p => p.edit)) {
      e.push([`In „${p.label}“ verschieben (geteilt) …`, async () => {
        if (!(await ask({ ok: 'Verschieben', danger: false, title: `${ids.length > 1 ? ids.length + ' Dateien' : '„' + (one?.display || '') + '“'} nach „${p.label}“ verschieben?\n\nDie Dateien stehen dann allen Websites zur Verfügung, die diesen Pool nutzen. Bisherige Verwendungen auf dieser Website bleiben erhalten; ändern dürfen sie danach nur Personen mit dem Recht „Geteilte Medien pflegen“.` }))) return;
        try { const r = await api.share(ids, p.key); toast(`${r.shared} ${r.shared === 1 ? 'Datei' : 'Dateien'} geteilt`); this.sel.clear(); this.load(); }
        catch (ex) { toast(ex.message); }
      }]);
    }
    const extra = hook('menu', this, ids, one).flat();   // Erweiterungen (z. B. Video-Werkzeuge)
    if (extra.length) e.push(['-'], ...extra);
    if (this.mode === 'library') e.push(['-'], [ids.length > 1 ? `${ids.length} Dateien löschen` : 'Löschen', () => this.deleteSel(), true]);
    this.menu(x, y, e);
  }

  // ---------------------------------------------------------- Quick Look
  quickLook(id) {
    const ql = inBox('fx-ql', '<dialog id="fx-ql" class="fx-ql" aria-label="Vorschau"></dialog>');
    const show = id => {
      const m = this.byId(id); if (!m) return;
      this.sel = new Set([id]); this.active = this.anchor = id; this.syncSel();
      const i = this.items.indexOf(m);
      ql.innerHTML = `<header><button type="button" data-qlclose aria-label="Schließen">✕</button><strong>${esc(m.display)}</strong>
          <span>${i + 1} / ${this.items.length}</span><a href="${esc(m.kind === 'pdf' ? m.viewer : m.url)}" target="_blank" rel="noopener">Öffnen ↗</a></header>
        <div class="fx-qlbody">${m.kind === 'image' ? `<img src="${esc(m.large || m.url)}" alt="${esc(m.alt)}">`
          : m.kind === 'pdf' ? `<iframe src="${esc(m.viewer)}?embed=1" title="${esc(m.display)}"></iframe>`
          : m.kind === 'video' ? `<video src="${esc(m.url)}"${m.large ? ` poster="${esc(m.large)}"` : ''} controls autoplay muted></video>`
          : m.kind === 'audio' ? `<audio src="${esc(m.url)}" controls></audio>` : ''}</div>
        <footer>${esc(m.type)} · ${esc(m.size)}${m.width ? ` · ${m.width} × ${m.height} px` : ''}${m.alt ? ` · „${esc(m.alt)}“` : ''}</footer>`;
      $('[data-qlclose]', ql).onclick = () => ql.close();
      hook('quickLook', this, m, ql);
    };
    ql.onkeydown = e => {
      const i = this.items.findIndex(m => m.id === this.active);
      if (e.key === ' ') { e.preventDefault(); ql.close(); }
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); show(this.items[Math.min(this.items.length - 1, i + 1)].id); }
      if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); show(this.items[Math.max(0, i - 1)].id); }
    };
    ql.onclick = e => { if (e.target === ql) ql.close(); };
    ql.onclose = () => this.$items.focus();
    show(id); ql.showModal();
  }

  // ---------------------------------------------------------- Informationen (rechts)
  async renderInfo() {
    const ids = [...this.sel], token = (this._infoToken = Symbol());
    if (!ids.length) {
      const c = this.meta?.counts || {};
      this.$info.innerHTML = `<div class="fx-i-empty">
        <div class="fx-i-big">${SVG.all}</div>
        <p><strong>${this.title()}</strong><br>${this.items.length} Objekte</p>
        ${c.noalt ? `<p class="fx-i-warn">${SVG.warn} ${c.noalt} ${c.noalt === 1 ? 'Bild' : 'Bilder'} ohne Alt-Text</p>` : ''}
        <p class="fx-i-hint">Dateien vom Computer einfach hierher ziehen oder mit ${isMac ? '⌘' : 'Strg'}+V einfügen – auch mehrere und große (bis ${MAX_MB} MB).<br>Auf Sammlungen links ziehen = einsortieren.</p>
        ${this.mode === 'library' ? `<p class="fx-i-hint">Bilder werden automatisch in 480–2400 px als ${esc($('[data-media-formats]')?.dataset.mediaFormats || 'WebP')} erzeugt. Alt-Texte sind beim Hochladen Pflicht (Barrierefreiheit).</p>` : ''}
        <dl class="fx-keys"><dt>Doppelklick / ↵</dt><dd>Bearbeiten</dd><dt>Leertaste</dt><dd>Quick Look</dd><dt>${isMac ? '⌘' : 'Strg'}-Klick / ⇧-Klick</dt><dd>Mehrere auswählen</dd><dt>${isMac ? '⌘' : 'Strg'} A</dt><dd>Alle auswählen</dd><dt>${isMac ? '⌘' : 'Strg'} V</dt><dd>Aus Zwischenablage einfügen</dd><dt>⌫ / Entf</dt><dd>Löschen</dd><dt>Rechtsklick</dt><dd>Weitere Aktionen</dd></dl></div>`;
      window.CMSAi?.mediaSummary?.(this);   // KI: Alt-Texte für Bilder ohne Alt-Text vorschlagen (_ai.js)
      if (this.mode === 'library') hook('summary', this, $('.fx-i-empty', this.$info), UI);
      return;
    }
    if (ids.length > 1) return this.renderMulti(ids);
    const m = await api.detail(ids[0]);
    if (token !== this._infoToken) return;           // inzwischen andere Auswahl
    this.renderSingle(m);
  }

  renderMulti(ids) {
    const items = ids.map(id => this.byId(id)).filter(Boolean);
    const cols = this.meta.collections;
    this.$info.innerHTML = `
      <div class="fx-stack">${items.slice(0, 3).map(m => `<span>${this.thumb(m)}</span>`).join('')}</div>
      <h2 class="fx-i-title">${items.length} Objekte</h2>
      <p class="fx-i-meta">${(items.reduce((s, m) => s + m.bytes, 0) / 1048576).toFixed(1).replace('.', ',')} MB</p>
      <section class="fx-i-sec"><h3>Tag hinzufügen</h3>
        <div class="fx-inline"><input data-mtag list="fx-taglist" placeholder="z. B. team" aria-label="Tag"><button type="button" class="adm-btn adm-btn--small" data-mtagadd>Hinzufügen</button></div>
        <datalist id="fx-taglist">${Object.keys(this.meta.tags).map(t => `<option value="${esc(t)}">`).join('')}</datalist>
        ${this.src.type === 'tag' ? `<button type="button" class="adm-link" data-muntag>Tag „${esc(this.src.value)}“ entfernen</button>` : ''}</section>
      ${cols.length ? `<section class="fx-i-sec"><h3>In Sammlung legen</h3><div class="fx-chips">${cols.map(c => `<button type="button" data-mcol="${c.id}">+ ${esc(c.name)}</button>`).join('')}</div>
        <p class="fx-i-hint">Tipp: Auswahl einfach links auf eine Sammlung ziehen.</p></section>` : ''}
      ${this.mode === 'library' ? `<div class="fx-i-actions">
        ${this.src.type === 'collection' ? '<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-muncol>Aus Sammlung entfernen</button>' : ''}
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" data-mdel>${items.length} Dateien löschen</button></div>` : ''}`;
    const i = this.$info;
    lazyThumbs($('.fx-stack', i));
    const addTag = async () => { const t = $('[data-mtag]', i).value.trim(); if (!t) return; await api.bulk({ ids, action: 'tag', tag: t }); toast(`Tag „${t}“ hinzugefügt`); this.load(); };
    $('[data-mtagadd]', i).onclick = addTag;
    $('[data-mtag]', i).onkeydown = e => { if (e.key === 'Enter') addTag(); };
    $('[data-muntag]', i)?.addEventListener('click', async () => { await api.bulk({ ids, action: 'untag', tag: this.src.value }); this.load(); });
    $$('[data-mcol]', i).forEach(b => b.onclick = async () => { await api.bulk({ ids, action: 'collect', collection: +b.dataset.mcol }); toast('In Sammlung gelegt'); this.load(); });
    $('[data-muncol]', i)?.addEventListener('click', async () => { await api.bulk({ ids, action: 'uncollect', collection: +this.src.value }); this.load(); });
    $('[data-mdel]', i)?.addEventListener('click', () => this.deleteSel());
    if (this.mode === 'library' && !this.ro) hook('multi', this, items, i, UI);
  }

  /** Schmale Vorschau rechts (wie Finder) – bearbeitet wird im großen Dialog */
  renderSingle(m) {
    const isImg = m.kind === 'image';
    this.$info.innerHTML = `
      ${this.mode === 'pick' ? `<button type="button" class="adm-btn adm-btn--primary fx-pickbtn" data-pick>Diese Datei verwenden</button>` : ''}
      <div class="fx-i-prev">${isImg ? `<img class="fx-i-img" src="${esc(m.large)}" alt="">`
        : m.kind === 'pdf' ? `<iframe src="${esc(m.viewer)}?embed=1" title="Vorschau: ${esc(m.display)}"></iframe>`
        : m.kind === 'video' ? `<video src="${esc(m.url)}"${m.large ? ` poster="${esc(m.large)}"` : ''} controls preload="metadata"></video>`
        : m.kind === 'audio' ? `<audio src="${esc(m.url)}" controls preload="metadata"></audio>` : ''}</div>
      <form class="fx-i-form" novalidate>
        <input class="fx-i-title" name="title" value="${esc(m.title)}" placeholder="${esc(m.display)}" aria-label="Titel / Anzeigename" maxlength="180" title="Titel – klicken zum Ändern">
        <p class="fx-i-meta">${esc(m.type)} · ${esc(m.size)}${m.width ? ` · ${m.width} × ${m.height} px` : ''}${m.pages ? ` · ${m.pages} Seiten` : ''}</p>
        ${isImg && m.adjust ? `<p class="fx-i-adj"><span class="ifx-badge" title="${esc(t('Bild angepasst (überall, wo es verwendet wird)'))}">${esc(t('Angepasst'))}</span> ${esc(m.adjust_label)}</p>` : ''}
        ${isImg ? `<section class="fx-i-sec"><h3><label for="fx-alt">Alt-Text <span class="req">*</span></label></h3>
          <textarea id="fx-alt" name="alt" rows="2" maxlength="250" ${m.decorative ? 'disabled' : ''} placeholder="Was ist zu sehen?">${esc(m.alt)}</textarea>
          <label class="fx-check"><input type="checkbox" name="decorative" ${m.decorative ? 'checked' : ''}> Dekorativ (ohne Aussage)</label></section>`
        : `<section class="fx-i-sec"><h3><label for="fx-alt">Beschreibung</label></h3><textarea id="fx-alt" name="alt" rows="2" maxlength="250" placeholder="optional">${esc(m.alt)}</textarea>${decoVideo(m, 'fx-check')}</section>`}
        ${this.meta?.languages && Object.keys(this.meta.languages).length ? `<section class="fx-i-sec"><h3>${esc(t('Übersetzungen'))}</h3>${transHtml(m, this.meta, isImg)}</section>` : ''}
        <section class="fx-i-sec"><h3><label for="fx-tagin">Tags</label></h3>
          <div class="fx-tags" data-tags><input id="fx-tagin" data-tagin list="fx-taglist1" placeholder="Tag + Enter" autocomplete="off"></div>
          <datalist id="fx-taglist1">${Object.keys(this.meta.tags).map(t => `<option value="${esc(t)}">`).join('')}</datalist></section>
        <p class="fx-i-state" aria-live="polite"></p>
      </form>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small fx-editbtn" data-edit>Alle Details, Fokus &amp; Zuschnitt …</button>
      ${isImg && this.mode === 'library' && !this.ro ? `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small fx-editbtn" data-adjust>${esc(t('Bild anpassen …'))}</button>` : ''}
      ${m.collections.length ? `<section class="fx-i-sec"><h3>Sammlungen</h3><p>${this.meta.collections.filter(c => m.collections.includes(c.id)).map(c => esc(c.name)).join(', ')}</p></section>` : ''}
      <section class="fx-i-sec"><h3>Verwendet auf</h3>${m.usages.length ? '<ul class="fx-usage">' + m.usages.map(u => `<li>${u.url ? `<a href="${esc(u.url)}" target="_blank" rel="noopener">${esc(u.label)}</a>` : esc(u.label)}</li>`).join('') + '</ul>' : '<p class="fx-i-hint">Noch nirgends.</p>'}</section>
      <dl class="fx-i-dl"><dt>Datei</dt><dd>${esc(m.name)}</dd><dt>Hinzugefügt</dt><dd>${fmtDate(m.created_at)}</dd>${m.updated_at && m.updated_at !== m.created_at ? `<dt>Geändert</dt><dd>${fmtDate(m.updated_at)}</dd>` : ''}</dl>`;
    $('[data-edit]', this.$info).onclick = () => this.edit(m.id);
    if (isImg) applyFx($('.fx-i-img', this.$info), m.adjust);
    $('[data-adjust]', this.$info)?.addEventListener('click', () => this.adjust(m));
    $('[data-pick]', this.$info)?.addEventListener('click', () => this.opts.onPick?.(this.byId(m.id) || m));
    if (this.ro) $$('.fx-i-form input, .fx-i-form textarea, .fx-i-form select, .fx-i-form button', this.$info).forEach(x => { x.disabled = true; });
    this.inlineEdit(m);
    window.CMSAi?.mediaPanel?.(this, m);   // KI: Alt-Text vorschlagen (_ai.js)
    if (this.meta?.ai?.captions !== false) captionsPanel(this, m, CAP_HELPERS);   // Untertitel & Transkript (_captions.js)
    // Erweiterungen (PHP, Extension::mediaPanel): fertige Abschnitte, vom Core escaped (Core\Slots::card)
    if (this.mode === 'library' && Array.isArray(m.panels) && m.panels.length) this.$info.insertAdjacentHTML('beforeend', m.panels.join(''));
    if (this.mode === 'library') hook('panel', this, m, UI);   // Erweiterungen (z. B. Video-Werkzeuge)
  }

  /** Titel, Alt-Text und Tags direkt in der Seitenleiste bearbeiten – speichert automatisch */
  inlineEdit(m) {
    const i = this.$info, form = $('.fx-i-form', i), state = $('.fx-i-state', i);
    const box = $('[data-tags]', i), tagIn = $('[data-tagin]', i);
    let tags = [...m.tags], timer;
    const drawTags = () => {
      $$('.fx-tag', box).forEach(t => t.remove());
      tagIn.insertAdjacentHTML('beforebegin', tags.map(t => `<span class="fx-tag"><span class="fx-dot" style="background:${tagColor(t)}"></span>${esc(t)}<button type="button" data-untag="${esc(t)}" aria-label="Tag ${esc(t)} entfernen">✕</button></span>`).join(''));
    };
    const save = async () => {
      clearTimeout(timer);
      const body = { title: form.title.value, alt: form.alt.value, decorative: form.decorative?.checked ? 1 : 0, credit: m.credit, tags: tags.join(','), focus: m.focus, i18n: readTrans(form) };
      state.className = 'fx-i-state'; state.textContent = 'Speichert …';
      try {
        const res = await api.save(m.id, body);
        m = { ...m, ...res.item };
        state.textContent = '✓ Gespeichert';
        const idx = this.items.findIndex(x => x.id === m.id);
        if (idx >= 0) this.items[idx] = res.item;
        this.patchItem(res.item);
      } catch (ex) { state.className = 'fx-i-state is-err'; state.textContent = ex.message; }
    };
    const later = () => { clearTimeout(timer); state.textContent = ''; timer = setTimeout(save, 800); };
    form.title.addEventListener('input', later);
    form.alt.addEventListener('input', later);
    [form.title, form.alt].forEach(f => f.addEventListener('blur', () => { if (timer) save(); }));
    form.querySelectorAll('[name^="i18n."]').forEach(f => { f.addEventListener('input', later); f.addEventListener('blur', () => { if (timer) save(); }); });
    form.title.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); form.title.blur(); } });
    form.decorative?.addEventListener('change', () => {
      if (m.kind === 'image') form.alt.disabled = form.decorative.checked;
      else decoCaptionHint(i, form.decorative.checked);   // Video: Hinweis „Noch keine Untertitel“ passend ein-/ausblenden
      save();
    });
    const addTag = v => {
      v = v.trim().toLowerCase().replace(/,/g, ''); tagIn.value = '';
      if (!v || tags.includes(v)) return;
      tags.push(v); drawTags(); save().then(() => { if (!(v in this.meta.tags)) this.load(); });
    };
    tagIn.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagIn.value); }
      else if (e.key === 'Backspace' && !tagIn.value && tags.length) { tags.pop(); drawTags(); save(); }
    });
    tagIn.addEventListener('change', () => { if (tagIn.value in this.meta.tags) addTag(tagIn.value); });
    box.addEventListener('click', e => {
      const b = e.target.closest('[data-untag]');
      if (b) { tags = tags.filter(t => t !== b.dataset.untag); drawTags(); save(); } else tagIn.focus();
    });
    form.addEventListener('submit', e => { e.preventDefault(); save(); });
    drawTags();
  }

  /** Großer Bearbeiten-Dialog: Vorschau mit Fokuspunkt + Zuschnitte links, Felder rechts */
  async edit(id) {
    const m = await api.detail(id);
    const isImg = m.kind === 'image', cols = this.meta.collections, ratios = this.meta.ratios;
    const dlg = inBox('media-detail', '<dialog id="media-detail" class="adm-dialog adm-dialog--wide md" aria-labelledby="md-title"></dialog>');
    let focus = { ...m.focus }, tags = [...m.tags], dirty = false;
    const cropTiles = () => Object.keys(ratios).map(r => {
      const c = m.crops?.[r];
      return `<button type="button" data-crop="${r}" class="${c ? 'is-set' : ''}" title="${esc(ratios[r])} – ${c ? 'eigener Zuschnitt' : 'automatisch nach Fokuspunkt'}. Klicken zum Zuschneiden">
        <span style="aspect-ratio:${r.replace(':', '/')}"><img src="${esc(c ? c.thumb : m.thumb)}" alt="" style="${c ? '' : `object-position:${focus.x}% ${focus.y}%`}"></span><small>${r}${c ? ' ✂' : ''}</small></button>`;
    }).join('');
    // Bild anpassen: Stand (Badge) + Knopf; Vorschau und Zuschnitt-Kacheln zeigen die Anpassung
    const adjBox = () => `${m.adjust ? `<span class="ifx-badge">${esc(t('Angepasst'))}</span> <span>${esc(fxLabel(m.adjust))}</span>` : `<span class="adm-muted">${esc(t('Original (ohne Anpassung)'))}</span>`}
      <button type="button" class="adm-btn adm-btn--small" data-adjust>${esc(t('Anpassen …'))}</button>`;
    // Bild im Rahmen: Standard des Bildes (leer = automatisch; SVG/transparenter Rand → einpassen)
    const fitBox = () => `${m.fit ? `<span class="ifx-badge">${esc(t('Eigener Standard'))}</span> <span>${esc(fitLabel(m.fit))}</span>`
        : `<span class="adm-muted">${esc(m.fit_auto ? t('Automatisch: {label}', { label: fitLabel(m.fit_auto) }) : t('Automatisch: Füllen (zuschneiden) – wie im Kit'))}</span>`}
      <button type="button" class="adm-btn adm-btn--small" data-fit>${esc(t('Rahmen …'))}</button>`;
    const fitSection = () => this.ro ? '' : `<h3 class="md-h3">${esc(t('Darstellung im Rahmen'))} <small>– ${esc(t('füllen, einpassen oder Originalformat'))}</small></h3>
            <div class="md-fit" data-fitbox>${fitBox()}</div>`;
    const tagHtml = () => tags.map(t => `<span class="fx-tag"><span class="fx-dot" style="background:${tagColor(t)}"></span>${esc(t)}<button type="button" data-untag="${esc(t)}" aria-label="Tag ${esc(t)} entfernen">✕</button></span>`).join('');
    dlg.innerHTML = `
      <div class="md-head"><h2 id="md-title">${esc(m.display)}</h2>
        <span class="md-meta">${esc(m.type)} · ${esc(m.size)}${m.width ? ` · ${m.width} × ${m.height} px` : ''}${m.pages ? ` · ${m.pages} Seiten` : ''}</span>
        <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-close>Schließen</button></div>
      <div class="md-grid">
        <div class="md-preview">
          ${isImg && m.svg ? `<div class="md-focus md-focus--svg"><img src="${esc(m.url)}" alt="" draggable="false"></div>
            <div class="md-iebar" data-iebar>${editToolbar(m, this.ro)}</div>
            <p class="f-help">${esc(t('SVG-Grafik: wird immer vollständig und in jeder Größe scharf gezeigt – Fokuspunkt und Zuschnitte entfallen.'))}${m.note ? ` ${esc(m.note)}.` : ''}</p>
            <h3 class="md-h3">${esc(t('Anpassen'))} <small>– ${esc(t('Effekte, Sättigung, Helligkeit, Kontrast, Schärfe'))}</small></h3>
            <div class="md-adjust" data-adjbox>${adjBox()}</div>${fitSection()}`
          : isImg ? `<div class="md-focus" data-focus title="Klicken: wichtigster Bildbereich (Fokuspunkt)"><img src="${esc(m.large)}" alt="" draggable="false"><span class="md-dot" style="left:${focus.x}%;top:${focus.y}%"></span></div>
            <div class="md-iebar" data-iebar>${editToolbar(m, this.ro)}</div>
            <p class="f-help">Fokuspunkt: ins Bild klicken (z. B. aufs Gesicht). Dieser Bereich bleibt in jedem Format sichtbar.</p>
            <h3 class="md-h3">Zuschnitte je Format <small>– klicken zum Zoomen und Zuschneiden</small></h3>
            <div class="md-crops" data-crops>${cropTiles()}</div>
            <h3 class="md-h3">${esc(t('Anpassen'))} <small>– ${esc(t('Effekte, Sättigung, Helligkeit, Kontrast, Schärfe'))}</small></h3>
            <div class="md-adjust" data-adjbox>${adjBox()}</div>${fitSection()}`
          : m.kind === 'pdf' ? `<iframe class="md-pdf" src="${esc(m.viewer)}?embed=1" title="Vorschau: ${esc(m.display)}"></iframe>`
          : m.kind === 'video' ? `<video class="md-video" src="${esc(m.url)}"${m.large ? ` poster="${esc(m.large)}"` : ''} controls preload="metadata"></video>
            <div class="md-iebar">${editToolbar(m, this.ro)}</div>`
          : m.kind === 'audio' ? `<audio class="md-video" src="${esc(m.url)}" controls preload="metadata"></audio>` : ''}
        </div>
        <form class="md-form" novalidate>
          <div class="f"><label for="md-t">Titel / Anzeigename</label><input id="md-t" name="title" value="${esc(m.title)}" placeholder="${esc(m.display)}" maxlength="180"></div>
          ${isImg ? `
          <div class="f"><label for="md-alt">Alt-Text <span class="req">*</span></label><textarea id="md-alt" name="alt" rows="3" maxlength="250" ${m.decorative ? 'disabled' : ''} placeholder="Was ist zu sehen? z. B. „Zwei Personen im Gespräch an einem Infostand“">${esc(m.alt)}</textarea>
            <p class="f-help">Wird vorgelesen, wenn jemand das Bild nicht sehen kann. Kurz und konkret, ohne „Bild von …“.</p></div>
          <label class="f-check"><input type="checkbox" name="decorative" ${m.decorative ? 'checked' : ''}> <span>Dekoratives Bild (trägt keine Information)</span></label>`
          : `<div class="f"><label for="md-alt">Beschreibung</label><input id="md-alt" name="alt" value="${esc(m.alt)}" maxlength="250"></div>${decoVideo(m, 'f-check')}`}
          ${this.meta?.languages && Object.keys(this.meta.languages).length ? `<fieldset class="md-trans"><legend>${esc(t('Übersetzungen'))}</legend>${transHtml(m, this.meta, isImg)}</fieldset>` : ''}
          <div class="f"><label for="md-tagin">Tags</label>
            <div class="fx-tags" data-tags>${tagHtml()}<input id="md-tagin" data-tagin list="md-taglist" placeholder="Tag eingeben, Enter" autocomplete="off"></div>
            <datalist id="md-taglist">${Object.keys(this.meta.tags).map(t => `<option value="${esc(t)}">`).join('')}</datalist></div>
          <fieldset class="md-cols"><legend>Sammlungen</legend>
            ${cols.map(c => `<label class="f-check"><input type="checkbox" name="collections" value="${c.id}" ${m.collections.includes(c.id) ? 'checked' : ''}> <span>${esc(c.name)}</span></label>`).join('') || '<p class="f-help" data-nocols>Noch keine Sammlungen.</p>'}
            <div class="fx-inline"><input data-newcol placeholder="Neue Sammlung …" aria-label="Neue Sammlung" maxlength="120"><button type="button" class="adm-btn adm-btn--small" data-addcol>Anlegen</button></div>
          </fieldset>
          <div class="f"><label for="md-c">Fotonachweis / Urheber</label><input id="md-c" name="credit" value="${esc(m.credit)}" maxlength="250" placeholder="optional"></div>
          <p class="md-err f-error" role="alert" hidden></p>
          <div class="md-actions">
            <button type="submit" class="adm-btn adm-btn--primary">Speichern</button>
            <button type="button" class="adm-btn adm-btn--ghost" data-replace>Datei ersetzen …</button>
            <a class="adm-btn adm-btn--ghost" href="${esc(m.kind === 'pdf' ? m.viewer : m.url)}" target="_blank" rel="noopener">Öffnen ↗</a>
            ${this.mode === 'library' ? '<button type="button" class="adm-btn adm-btn--ghost adm-btn--danger-text" data-del>Löschen</button>' : ''}
          </div>
          <div class="fx-i-progress" hidden><span></span></div>
          <dl class="md-info">
            <dt>Datei</dt><dd>${esc(m.name)}</dd>
            <dt>Hinzugefügt</dt><dd>${fmtDate(m.created_at)}${m.updated_at && m.updated_at !== m.created_at ? ` · geändert ${fmtDate(m.updated_at)}` : ''}</dd>
            <dt>Verwendet</dt><dd>${m.usages.length ? '<ul class="fx-usage">' + m.usages.map(u => `<li>${u.url ? `<a href="${esc(u.url)}" target="_blank" rel="noopener">${esc(u.label)}</a>` : esc(u.label)}</li>`).join('') + '</ul>' : 'noch nirgends'}</dd>
          </dl>
        </form>
      </div>`;
    const form = $('form', dlg), err = $('.md-err', dlg);
    const close = async () => { if (!dirty || await ask({ title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen') })) { dirty = false; dlg.close(); } };
    $('[data-close]', dlg).onclick = close;
    dlg.oncancel = e => { e.preventDefault(); close(); };
    dlg.onclose = () => this.$items.focus();
    form.addEventListener('input', e => { if (!e.target.matches('[data-tagin],[data-newcol]')) dirty = true; });
    form.decorative?.addEventListener('change', () => { if (isImg) form.alt.disabled = form.decorative.checked; });
    // Tags
    const tagBox = $('[data-tags]', dlg), tagIn = $('[data-tagin]', dlg);
    const redrawTags = () => { $$('.fx-tag', tagBox).forEach(t => t.remove()); tagIn.insertAdjacentHTML('beforebegin', tagHtml()); };
    const addTag = v => { v = v.trim().toLowerCase().replace(/,/g, ''); if (v && !tags.includes(v)) { tags.push(v); dirty = true; redrawTags(); } tagIn.value = ''; };
    tagIn.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagIn.value); }
      if (e.key === 'Backspace' && !tagIn.value && tags.length) { tags.pop(); dirty = true; redrawTags(); }
    });
    tagIn.addEventListener('change', () => { if (tagIn.value in this.meta.tags) addTag(tagIn.value); });
    tagIn.addEventListener('blur', () => { if (tagIn.value.trim()) addTag(tagIn.value); });
    tagBox.addEventListener('click', e => { const b = e.target.closest('[data-untag]'); if (b) { tags = tags.filter(t => t !== b.dataset.untag); dirty = true; redrawTags(); } else tagIn.focus(); });
    // Neue Sammlung
    $('[data-addcol]', dlg).onclick = async () => {
      const inp = $('[data-newcol]', dlg), name = inp.value.trim(); if (!name) return;
      const res = await api.collection(name);
      $('[data-nocols]', dlg)?.remove();
      inp.parentElement.insertAdjacentHTML('beforebegin', `<label class="f-check"><input type="checkbox" name="collections" value="${res.id}" checked> <span>${esc(name)}</span></label>`);
      this.meta.collections.push({ id: res.id, name, count: 0 });
      inp.value = ''; dirty = true;
    };
    // Fokuspunkt
    $('[data-focus]', dlg)?.addEventListener('click', e => {
      const r = $('img', e.currentTarget).getBoundingClientRect();
      focus = { x: Math.round(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * 100), y: Math.round(Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) * 100) };
      const dot = $('.md-dot', dlg); dot.style.left = focus.x + '%'; dot.style.top = focus.y + '%';
      $$('[data-crop]:not(.is-set) img', dlg).forEach(im => (im.style.objectPosition = `${focus.x}% ${focus.y}%`));
      dirty = true;
    });
    // Zuschneiden (speichert sofort je Format)
    const fxPreview = () => $$('.md-focus img, [data-crops] img', dlg).forEach(im => applyFx(im, m.adjust));
    $('[data-crops]', dlg)?.addEventListener('click', e => {
      const b = e.target.closest('[data-crop]'); if (!b) return;
      crop({ ...m, focus }, b.dataset.crop, (r, res) => { m.crops = res.item.crops; $('[data-crops]', dlg).innerHTML = cropTiles(); fxPreview(); this.load(m.id); });
    });
    // Bild bearbeiten (speichert sofort; neue Größen, eigene Zuschnitte je Format werden zurückgesetzt)
    $('[data-iebar]', dlg)?.addEventListener('click', async e => {
      const b = e.target.closest('[data-ie-tool]'); if (!b) return;
      const item = await this.editImage(m, b.dataset.ieTool);
      if (!item) { b.focus(); return; }
      $('.md-focus img', dlg).src = m.large;
      $('.md-meta', dlg).textContent = `${m.type} · ${m.size}${m.width ? ` · ${m.width} × ${m.height} px` : ''}`;
      $('[data-crops]', dlg).innerHTML = cropTiles(); fxPreview();
      $('[data-iebar]', dlg).innerHTML = editToolbar(m, this.ro);
      $(`[data-ie-tool="${b.dataset.ieTool}"]`, dlg)?.focus();
    });
    // Bild anpassen (speichert sofort, wie der Zuschnitt)
    $('[data-adjbox]', dlg)?.addEventListener('click', async e => {
      if (!e.target.closest('[data-adjust]')) return;
      if ((await this.adjust(m)) === undefined) return;
      $('[data-adjbox]', dlg).innerHTML = adjBox(); fxPreview();
      $('[data-adjust]', dlg)?.focus();
    });
    // Bild im Rahmen (Standard des Bildes, speichert sofort)
    $('[data-fitbox]', dlg)?.addEventListener('click', async e => {
      if (!e.target.closest('[data-fit]')) return;
      const v = await fitDialog({ base: BASE, src: m.large || m.url, thumb: m.thumb || m.url, name: m.display, scope: 'global', value: m.fit, auto: m.fit_auto, svg: m.svg,
        onApply: async val => { const r = await api.fit(m.id, val); Object.assign(m, { fit: r.item.fit, fit_label: r.item.fit_label, fit_auto: r.item.fit_auto }); } });
      if (v === undefined) return;
      $('[data-fitbox]', dlg).innerHTML = fitBox();
      $('[data-fit]', dlg)?.focus();
    });
    if (isImg) fxPreview();
    form.onsubmit = async e => {
      e.preventDefault(); err.hidden = true;
      const body = { title: form.title.value, alt: form.alt?.value || '', decorative: form.decorative?.checked ? 1 : 0, credit: form.credit.value, tags: tags.join(','), focus, collections: $$('input[name=collections]:checked', form).map(c => +c.value), i18n: readTrans(form) };
      try { await api.save(m.id, body); dirty = false; dlg.close(); toast('Gespeichert'); this.load(m.id); }
      catch (ex) { err.textContent = ex.message; err.hidden = false; form.alt?.focus(); }
    };
    $('[data-replace]', dlg).onclick = async () => { if (await this.replace(m, $('.fx-i-progress', dlg))) { dirty = false; dlg.close(); this.edit(m.id); } };
    $('[data-del]', dlg)?.addEventListener('click', async () => {
      if (m.usages.length) { await ask({ title: usedMsg(m.display, m.usages), ok: t('Verstanden'), danger: false, cancel: false }); return; }
      if (!(await ask({ title: `„${m.display}“ endgültig löschen?`, ok: 'Löschen' }))) return;
      try { await api.del(m.id); dirty = false; dlg.close(); this.sel.delete(m.id); toast('Gelöscht'); this.load(); }
      catch (ex) { await this.deleteRefused(ex, m.display); }
    });
    if (!dlg.open) dlg.showModal();
    (isImg && !m.alt && !m.decorative ? form.alt : form.title).focus();
  }

  /**
   * Bildeditor (Core\ImageEdit): Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren – speichert sofort.
   * Übernimmt das Ergebnis in m (Adressen, Maße, Zuschnitte) und lädt die Liste neu. Ergebnis: item oder undefined.
   */
  async editImage(m, tool = 'crop') {
    if (m.original_size === undefined || m.edit_engine === undefined) Object.assign(m, await api.detail(m.id));
    if (m.editable) { toast(m.editable); return undefined; }
    const item = await editImage(m, { tool, engine: m.edit_engine, save: edit => api.edit(m.id, edit) });
    if (!item) return undefined;
    for (const k of ['url', 'thumb', 'large', 'width', 'height', 'size', 'bytes', 'crops', 'edit', 'original', 'original_size', 'updated_at']) m[k] = item[k];
    toast(item.edit ? t('Bearbeitung gespeichert') : t('Bearbeitung entfernt – Original'));
    this.load(m.id);
    return item;
  }

  /** Dialog „Bild anpassen“ (global); speichert sofort. Ergebnis: neuer Wert oder undefined (Abbruch) */
  async adjust(m) {
    const v = await adjustDialog({
      src: m.large || m.url, thumb: m.thumb, name: m.display, scope: 'global', value: m.adjust,
      onApply: async val => { const res = await api.adjust(m.id, val); m.adjust = res.item.adjust; m.adjust_label = res.item.adjust_label; },
    });
    if (v === undefined) return undefined;
    toast(v ? t('Anpassung gespeichert') : t('Anpassung entfernt – Original'));
    const it = this.byId?.(m.id);
    if (it && it !== m) { it.adjust = m.adjust; it.adjust_label = m.adjust_label; }
    if (this.sel?.size === 1 && this.sel.has(m.id)) this.renderInfo();   // Informationen rechts: Badge, Vorschau
    return v;
  }

  patchItem(m) {
    const el = $(`[data-id="${m.id}"]`, this.$items);
    if (!el) return;
    $('.fx-name span', el).textContent = m.display;
    const w = $('.fx-warn', el); if (w && !m.missing_alt) w.remove();
    const dots = $('.fx-dots', el);
    if (dots) dots.innerHTML = m.tags.slice(0, 4).map(t => `<span class="fx-dot" style="background:${tagColor(t)}" title="${esc(t)}"></span>`).join('') + (this.view === 'list' && m.tags.length ? `<span class="fx-tagtext">${esc(m.tags.join(', '))}</span>` : '');
  }

  async replace(m, bar = null) {
    const types = m.kind === 'image' ? ACCEPT.image : m.kind === 'pdf' ? ACCEPT.pdf : m.kind === 'audio' ? ACCEPT.audio : ACCEPT.video;
    const [file] = await pickFiles(types, false);
    if (!file) return false;
    if (!m.usages) m = await api.detail(m.id);
    const hadCrops = Object.keys(m.crops || {}).length;
    if (!(await ask({ ok: 'Ersetzen', danger: false, title: `„${m.display}“ durch „${file.name}“ ersetzen?\n\nAlt-Text, Tags, Sammlungen und alle ${m.usages.length ? m.usages.length + ' ' : ''}Verwendungen bleiben erhalten.${hadCrops ? '\nEigene Zuschnitte werden zurückgesetzt.' : ''}` }))) return false;
    if (bar) bar.hidden = false;
    try {
      await uploadFile(file, { replace_id: m.id, reset_focus: 1 }, p => { if (bar) bar.firstElementChild.style.width = Math.round(p * 100) + '%'; });
      toast('Ersetzt – überall auf der Website aktualisiert');
      this.load(m.id);
      return true;
    } catch (ex) { alert(ex.message); if (bar) bar.hidden = true; return false; }
  }
}

// ============================================================ Auswahldialog für Felder
function pick(kind = 'image') {
  return new Promise(resolve => {
    const dlg = inBox('media-dialog', '<dialog id="media-dialog" class="fx-dialog" aria-label="Mediathek"></dialog>');
    let chosen = null;
    dlg.innerHTML = `<div class="fx-dhead"><h2>${kind === 'image' ? 'Bild auswählen' : kind === 'visual' ? t('Bild oder Video auswählen') : 'Datei auswählen'}</h2><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-close>Abbrechen</button></div><div class="fx-host"></div>`;
    new Finder($('.fx-host', dlg), { mode: 'pick', kind: kind === 'image' || kind === 'visual' ? kind : null, onPick: m => { chosen = m; dlg.close(); } });
    $('[data-close]', dlg).onclick = () => dlg.close();
    dlg.onclose = () => { resolve(chosen); dlg.innerHTML = ''; };
    dlg.showModal();
    $('.fx-items', dlg).focus();
  });
}

// ============================================================ Zuschneiden & Anpassen direkt auf der Seite (Bearbeiten-Modus)
/*
 * Knöpfe oben rechts am Bild (Shadow-DOM-Ebene): „Zuschneiden“ für Bilder mit Bildformat (data-ratio), „Anpassen“ für Bilder
 * in Blöcken. Anpassen gilt für diese Einbindung (data._fx des Blocks über window.CMSEditor.fx, resources/js/editor.js);
 * lässt sich das Bild keinem Bild-Feld des Blocks zuordnen (z. B. aus einer Datentabelle oder zentral gepflegt), wirkt die
 * Anpassung global wie in der Mediathek.
 */
function initInlineCrop() {
  if (!$('#cms-editor')) return;
  // Bilder auf der Seite: Dateien dieser Website (local(), nie im zuletzt gewählten Pool – sonst 404: …/api/media/192?pool=…)
  const bar = d.createElement('div');
  bar.className = 'cms-imgtools'; bar.hidden = true;
  bar.innerHTML = `<button type="button" class="cms-cropbtn" data-fx>${ico('sliders-horizontal')} ${esc(t('Anpassen'))}</button><button type="button" class="cms-cropbtn" data-fit>${ico('image')} ${esc(t('Rahmen'))}</button><button type="button" class="cms-cropbtn" data-crop>${ico('crop')} ${esc(t('Zuschneiden'))}</button>`;
  box().append(bar);   // Shadow-DOM-Ebene: Kit-Regeln für button wirken nicht
  const fxBtn = $('[data-fx]', bar), cropBtn = $('[data-crop]', bar), fitBtn = $('[data-fit]', bar);
  let target = null, hideT, leaving = false;
  const place = () => {
    const r = target.getBoundingClientRect();
    // Nicht über Werkzeugleiste oder klebenden Kit-Kopf legen: unter deren Unterkante rücken, sonst ausblenden
    let top = Math.max(r.top + 12, topInset() + 8), right = r.right - 12;
    // Stift „Eintrag bearbeiten“ (Datenlisten, oben rechts auf der Karte) und die Leiste des Blocks (editor.js BarPlace)
    // freilassen: links daneben, bei Platzmangel darunter
    const blockBar = target.closest('.cms-block')?.querySelector(':scope>.cms-block__bar');
    const busy = [...d.querySelectorAll('.cms-entry-pencil, .cms-target-edit'),   // auch „✎ Bearbeiten“ an Karten/Kacheln (Core\TargetEdit)
       ...(blockBar && +getComputedStyle(blockBar).opacity > 0 && !blockBar.classList.contains('is-yield') ? [blockBar] : [])]
      .map(p => p.getBoundingClientRect());
    for (let i = 0; i < 3; i++) {
      const pen = busy.find(p => p.width && p.left < right && p.right > right - bar.offsetWidth && p.top < top + bar.offsetHeight && p.bottom > top);
      if (!pen) break;
      if (pen.left - 8 - bar.offsetWidth >= r.left + 8) right = pen.left - 8;
      else top = pen.bottom + 8;
    }
    bar.style.visibility = top + bar.offsetHeight > r.bottom - 8 ? 'hidden' : '';
    bar.style.left = (right + scrollX - bar.offsetWidth) + 'px';
    bar.style.top = (top + scrollY) + 'px';
  };
  const show = im => {
    clearTimeout(hideT); leaving = false; target = im;
    cropBtn.hidden = !im.dataset.ratio;
    fxBtn.hidden = !im.closest('.cms-block__preview') || !window.CMSEditor?.fx;
    fitBtn.hidden = fxBtn.hidden || !window.CMSEditor?.fit;
    if (cropBtn.hidden && fxBtn.hidden) { bar.hidden = true; return; }
    fitBtn.setAttribute('aria-label', t('Darstellung im Rahmen: füllen, einpassen oder Originalformat'));
    cropBtn.setAttribute('aria-label', t('Bild zuschneiden ({ratio})', { ratio: im.dataset.ratio || '' }));
    fxBtn.setAttribute('aria-label', t('Bild anpassen (Effekte, Sättigung, Helligkeit, Kontrast, Schärfe)'));
    bar.hidden = false; place();
  };
  const hide = () => { bar.hidden = true; target = null; };
  // Bild unter dem Zeiger – auch wenn eine Ebene des Kits darüber liegt (ganze Karte klickbar: Link mit ::after über
  // dem Porträt, Verläufe, Beschriftungen). Dann trifft mouseover nie das <img>, „Rahmen“/„Anpassen“ blieben unerreichbar.
  const imgAt = e => {
    const own = e.target.closest?.('img[data-media-id]');
    if (own) return own;
    if (!e.target.closest?.('.cms-block__preview')) return null;
    return d.elementsFromPoint(e.clientX, e.clientY).find(x => x.matches?.('img[data-media-id]') && x.closest('.cms-block__preview')) || null;
  };
  let raf = 0;
  const track = e => {
    if (inPath(e, bar)) { clearTimeout(hideT); return; }
    const im = imgAt(e);
    if (im) { leaving = false; if (im !== target || bar.hidden) show(im); else clearTimeout(hideT); }
    else if (target) { clearTimeout(hideT); hideT = setTimeout(hide, 250); }
  };
  d.addEventListener('mouseover', track);
  // Innerhalb einer darüberliegenden Ebene gibt es kein neues mouseover – Bewegung verfolgen (einmal je Bild)
  d.addEventListener('mousemove', e => {
    if (raf || e.target.closest?.('img[data-media-id]') || !e.target.closest?.('.cms-block__preview')) return;
    raf = requestAnimationFrame(() => {
      raf = 0;
      const im = imgAt(e);
      if (im && (im !== target || bar.hidden)) show(im);
      else if (im) { leaving = false; clearTimeout(hideT); }
      else if (target && !bar.hidden && !leaving) { leaving = true; clearTimeout(hideT); hideT = setTimeout(() => { leaving = false; hide(); }, 250); }
    });
  }, { passive: true });
  // Tastatur: Knöpfe erscheinen, sobald der Fokus in einem Block mit Bild liegt (Tab führt dann in die Leiste)
  bar.addEventListener('focusout', e => { if (!bar.contains(e.relatedTarget)) hideT = setTimeout(hide, 250); });
  addEventListener('scroll', () => { if (target && !bar.hidden) place(); }, { passive: true });
  cropBtn.addEventListener('click', e => {
    e.preventDefault(); e.stopPropagation();
    if (!target) return;
    const id = +target.dataset.mediaId, ratio = target.dataset.ratio;
    bar.hidden = true;
    local(() => crop(id, ratio, (r, res) => refreshPictures(id, r, res.sources), { only: ratio }));
  });
  fxBtn.addEventListener('click', async e => {
    e.preventDefault(); e.stopPropagation();
    if (!target) return;
    const im = target, id = +im.dataset.mediaId;
    bar.hidden = true;
    await local(() => adjustHere(im, id));
  });
  const adjustHere = async (im, id) => {
    let m;
    try { m = await api.detail(id); } catch (ex) { toast(ex.message); return; }
    const place = window.CMSEditor?.fx?.target(im);
    if (!place) {
      // Keine Einbindung im Block zuordenbar → global (Mediathek)
      const v = await adjustDialog({ src: m.large || m.url, thumb: m.thumb, name: m.display, scope: 'global', value: m.adjust,
        note: t('Dieses Bild lässt sich hier keinem Bild-Feld des Blocks zuordnen – die Anpassung gilt deshalb für alle Verwendungen.'),
        onApply: async val => { await api.adjust(m.id, val); } });
      if (v !== undefined) { $$(`img[data-media-id="${id}"]`).forEach(x => applyFx(x, v)); toast(v ? t('Anpassung gespeichert') : t('Anpassung entfernt – Original')); }
      return;
    }
    const v = await adjustDialog({
      src: m.large || m.url, thumb: m.thumb, name: m.display, scope: 'place', value: place.value, global: m.adjust,
      note: place.paths.length > 1 ? t('Das Bild kommt in diesem Block mehrfach vor – die Einstellung gilt für alle diese Stellen.') : '',
      onApply: async val => { place.set(val); },
    });
    if (v !== undefined) toast(t('Übernommen – mit „Speichern“ sichern'));
  };
  // Bild im Rahmen je Einbindung (data._fit des Blocks über window.CMSEditor.fit, resources/js/editor.js)
  fitBtn.addEventListener('click', async e => {
    e.preventDefault(); e.stopPropagation();
    if (!target) return;
    const im = target;
    bar.hidden = true;
    const place = window.CMSEditor?.fit?.target(im);
    if (!place) { toast(t('Dieses Bild lässt sich hier keinem Bild-Feld des Blocks zuordnen – den Standard bitte in der Mediathek festlegen.')); return; }
    const v = await local(() => place.open());
    if (v !== undefined) toast(t('Übernommen – mit „Speichern“ sichern'));
  });
}

// Einfügen aus der Zwischenablage – nicht in Eingabefeldern (dort bleibt normales Einfügen von Text)
d.addEventListener('paste', e => {
  const files = e.clipboardData?.files;
  if (!files?.length) return;
  const t = e.composedPath?.()[0] || e.target;
  if (t instanceof Element && t.closest('input:not([type=search]),textarea,[contenteditable=""],[contenteditable=true]')) return;
  const f = [...FINDERS].reverse().find(x => x.root.isConnected && x.root.getClientRects().length);
  if (!f) return;
  e.preventDefault();
  f.pasteFiles(files);
});

// Nach außen immer im Kontext dieser Website (local) – die Mediathek selbst nutzt api/crop intern mit ihrem Pool
const localApi = new Proxy(api, { get: (o, k) => typeof o[k] === 'function' ? (...a) => local(() => o[k](...a)) : o[k] });
window.CMSMedia = { pick, crop: (...a) => local(() => crop(...a)), Finder, Uploader, api: localApi, extend, ui: UI, finders: FINDERS, lazyThumbs, vthumbHtml,
  adjust: o => local(() => adjustDialog(o)), fxLabel, fit: o => local(() => fitDialog({ base: BASE, ...o })), fitLabel };
const root = $('[data-media-library]');
if (root) {
  const f = new Finder(root, { mode: 'library' });
  // Hauptnavigation links wird zur Mediathek-Navigation (Orte, Sammlungen, Tags) – mehr Platz für die Dateien
  drill({ panel: f.$side, home: f.root, area: 'media', title: t('Medien'), label: t('Mediathek'), back: t('Zurück zu Medien') });
  const hm = location.hash.match(/^#m(\d+)$/);   // Sprung aus der Suche: Datei öffnen
  if (hm) f.load(+hm[1]).then(() => f.edit(+hm[1]));
  const hc = location.hash.match(/^#c(\d+)$/);   // Sprung aus „KLXM AI → Untertitel“: Datei auswählen, Untertitel rechts prüfen
  if (hc) f.load(+hc[1]);
  // Sprung aus der Übersicht („Was ist zu tun?“): Prüf-Filter öffnen – #check=noalt | missing:en | nocaptions | notranscript | notitle
  const hk = location.hash.match(/^#check=((?:noalt|notitle|nocaptions|notranscript)|missing:[a-z]{2,3}(?:-[a-z]{2})?)$/i);
  if (hk) { f.src = { type: 'check', value: hk[1] }; f.load(); }
}
initInlineCrop();
initCaptionsQueue(CAP_HELPERS);   // Bereich „KLXM AI → Untertitel“ (Knöpfe, Stand der Aufträge)
})();
