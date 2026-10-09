/*
 * „Design“ (Core\DesignTool) – Style-Editor als Werkzeug der Werkzeugleiste auf der Website, geladen erst beim ersten Öffnen
 * (CMSAdmin.tools, resources/js/_tools.js), dargestellt als Seitenleiste in voller Höhe (panel size 'drawer': die Website wird
 * schmaler, _cq.js lässt ihre Haltepunkte der neuen Breite folgen; Telefone: Blatt unten).
 *
 * Inhalt: Vorlagen als Kacheln (vier Farbpunkte + Name), je Gruppe ein aufklappbarer Abschnitt mit Reglern je Token-Typ
 * (color hell + dunkel, range, choice/font, bool), Kontrastwarnungen. Jede Änderung → Server (Admin\DesignController::liveApply,
 * Design::live) → sofort auf der aktuellen Seite (120 ms gebündelt, die jüngste Antwort gewinnt):
 *  - Klassen am <html>: die vorigen Design-Klassen (data-design-classes) weg, die neuen dazu (auch abgeleitete des Kits)
 *  - Variablen hell + dunkel per CSSOM (document.adoptedStyleSheets – CSP-konform, kein <style>, kein style-Attribut)
 *  - Schriften: fehlende <link> ergänzen; Varianten-Stylesheets (link[data-design-opt]) austauschen – im Bearbeiten-Modus
 *    über _cq.js umgeschrieben wie alle Stylesheets der Seite
 * Tokens mit 'markup' (Kopf-/Fußvariante …): die Vorschau behält den gespeicherten Wert, Hinweis „Vollständig nach dem Speichern“;
 * nach dem Speichern lädt die Seite neu (im Bearbeiten-Modus nur ohne ungespeicherte Änderungen).
 * „Standard“ = Werte des Kits, „Abbrechen“ (und Schließen) = gespeicherter Stand wieder live, „Speichern“ = für die ganze Website.
 */
import { convertSheet, dropSheet } from './_cq.js';

const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
/** Text des Werkzeugs (Core\DesignTool → texts) */
const tx = (ctx, k, p) => ctx.t(k, p);
const hex = v => {
  let s = String(v ?? '').trim().toUpperCase();
  if (s && s[0] !== '#') s = '#' + s;
  if (/^#[0-9A-F]{3}$/.test(s)) s = '#' + [...s.slice(1)].map(c => c + c).join('');
  return /^#[0-9A-F]{6}$/.test(s) ? s : null;
};

let S = null;          // Antwort beim Laden: schema, values, defaults, markup, saved (Live-Stand der gespeicherten Werte)
let values = {};       // aktuelle (ungespeicherte) Werte
let saved = {};        // gespeicherte Werte
let base = null;       // Live-Stand der gespeicherten Werte (für „Abbrechen“)
let tokens = {};       // name → Token
let timer = 0, seq = 0, sheet = null, styleEl = null, optAnchor = null, busy = false;
let C = null;          // ctx des Werkzeugs (Texte, Seitenleiste)

const isDirty = () => Object.keys(saved).some(k => String(values[k]) !== String(saved[k]));

// ------------------------------------------------------------------ Auf der Seite anwenden
function setCss(css) {
  if ('adoptedStyleSheets' in d && 'replaceSync' in CSSStyleSheet.prototype) {
    sheet ??= new CSSStyleSheet();
    sheet.replaceSync(css);
    if (!d.adoptedStyleSheets.includes(sheet)) d.adoptedStyleSheets = [...d.adoptedStyleSheets, sheet];
    return;
  }
  // Ältere Browser: angemeldet erlaubt die CSP Inline-Stile (Editor); Besucher bekommen dieses Modul nie
  styleEl ??= d.head.appendChild(d.createElement('style'));
  styleEl.textContent = css;
}

function apply(res) {
  const html = d.documentElement;
  (html.dataset.designClasses || '').split(/\s+/).filter(Boolean).forEach(c => html.classList.remove(c));
  (res.classes || '').split(/\s+/).filter(Boolean).forEach(c => html.classList.add(c));
  html.dataset.designClasses = res.classes || '';
  setCss(res.css || '');
  // Schriften: fehlende Stylesheets ergänzen (bleiben bis zum Neuladen – schadet nicht)
  for (const href of res.fonts || []) {
    if ([...d.querySelectorAll('link[rel~="stylesheet"]')].some(l => l.getAttribute('href') === href)) continue;
    const l = d.createElement('link');
    l.rel = 'stylesheet'; l.href = href; l.dataset.designFont = '';
    d.head.append(l);
  }
  // Varianten-Stylesheets austauschen (Reihenfolge: nach dem letzten vorhandenen bzw. an der gemerkten Stelle)
  const want = res.opt || [];
  d.querySelectorAll('link[data-design-opt]').forEach(l => { if (!want.includes(l.getAttribute('href'))) dropSheet(l); });
  for (const href of want) {
    const have = [...d.querySelectorAll('link[data-design-opt]')];
    if (have.some(l => l.getAttribute('href') === href)) continue;
    const l = d.createElement('link');
    l.rel = 'stylesheet'; l.href = href; l.dataset.designOpt = '';
    const after = have.at(-1) || (optAnchor?.isConnected ? optAnchor : null);
    if (after) after.after(l); else d.head.append(l);
    convertSheet(l);
  }
  checks(res.checks || []);
}

/** Beim ersten Öffnen: Klassen und Varianten-Stylesheets der gespeicherten Werte kennzeichnen (die tauscht apply() später aus) */
function adopt(res) {
  const html = d.documentElement;
  if (html.dataset.designClasses === undefined) html.dataset.designClasses = res.classes || '';
  const links = [...d.querySelectorAll('link[rel~="stylesheet"][href]')];
  links.forEach(l => { if ((res.opt || []).includes(l.getAttribute('href'))) l.dataset.designOpt = ''; });
  // Stelle für Varianten, wenn bisher keine geladen ist: nach dem Haupt-Stylesheet des Kits
  optAnchor = links.find(l => /\/assets\/kits\/[^/]+\/css\/site\.css/.test(l.getAttribute('href'))) || null;
}

// ------------------------------------------------------------------ Server
async function preview(ctx) {
  const my = ++seq;
  try {
    const res = await ctx.fetch(ctx.tool.data.endpoint, { json: { v: values } });
    if (my === seq) apply(res);
  } catch (e) {
    if (my === seq) ctx.toast(e.message, 'error');
  }
}
function schedule(ctx) {
  clearTimeout(timer);
  timer = setTimeout(() => preview(ctx), 120);
  marks(ctx);
}

// ------------------------------------------------------------------ Oberfläche
function control(t, ctx) {
  const T = ctx.t, n = t.name, id = 'dl-' + n, v = values[n];
  const help = t.help ? `<small>${esc(t.help)}</small>` : '';
  const mark = (S.markup || []).includes(n) ? `<span class="dl-markup" data-dl-markup="${esc(n)}" title="${esc(T('markupHelp'))}" hidden>${esc(T('markup'))}</span>` : '';
  if (t.type === 'color') {
    const sw = (key, mode) => `<span class="dl-color"><input type="color" data-token="${esc(key)}" value="${esc(String(values[key] || '#000000').toLowerCase())}" aria-label="${esc(t.label)} (${esc(T(mode))})"${mode === 'dark' ? ` title="${esc(T('darkTitle'))}"` : ''}>`
      + `<input type="text" data-hex="${esc(key)}" value="${esc(values[key] || '')}" maxlength="7" spellcheck="false" aria-label="${esc(t.label)} (${esc(T(mode))}) – Hex">${esc(T(mode))}</span>`;
    return `<div class="dl-f" role="group" aria-labelledby="${id}"><span class="dl-l" id="${id}">${esc(t.label)}</span><div class="dl-colors">${sw(n, 'light')}${t.dark ? sw(n + '@dark', 'dark') : ''}</div>${help}</div>`;
  }
  if (t.type === 'range') {
    return `<div class="dl-f"><label for="${id}">${esc(t.label)}</label><span class="dl-range"><input type="range" id="${id}" data-token="${esc(n)}" min="${esc(t.min ?? 0)}" max="${esc(t.max ?? 100)}" step="${esc(t.step ?? 1)}" value="${esc(v)}"><output for="${id}" data-out="${esc(n)}">${esc(fmtNum(v, t, ctx))}</output></span>${help}</div>`;
  }
  if (t.type === 'bool') {
    return `<div class="dl-f"><label class="dl-check"><input type="checkbox" data-token="${esc(n)}"${v ? ' checked' : ''}> <span>${esc(t.label)}</span></label>${mark}${help}</div>`;
  }
  if (t.type === 'choice' || t.type === 'font') {
    const opts = t.type === 'font'
      ? Object.entries(S.schema.fonts || {}).filter(([k, f]) => !f.dup || String(v) === k).map(([k, f]) => [k, f.label])
      : Object.entries(t.options || {});
    return `<div class="dl-f"><label for="${id}">${esc(t.label)}</label><select id="${id}" data-token="${esc(n)}">${opts.map(([k, l]) => `<option value="${esc(k)}"${String(v) === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>${mark}${help}</div>`;
  }
  return '';
}
const fmtNum = (v, t, ctx) => { try { return Number(v).toLocaleString(ctx.lang || 'de', { maximumFractionDigits: 3 }) + (t.unit || ''); } catch { return v + (t.unit || ''); } };

function render(ctx) {
  const T = ctx.t, P = ctx.panel.body, sc = S.schema;
  const presets = Object.entries(sc.presets || {});
  const colorsOf = p => {
    const want = ['accent', 'highlight', 'ink', 'background'].map(k => p.values[k]).filter(Boolean);
    const all = Object.entries(p.values).filter(([k, x]) => !k.includes('@') && tokens[k]?.type === 'color').map(([, x]) => x);
    return (want.length >= 3 ? want : [...want, ...all.filter(x => !want.includes(x))]).slice(0, 4);
  };
  P.innerHTML = `<form class="dl" data-dl novalidate>
    <div class="dl-main">
      ${presets.length ? `<details class="dl-group" open><summary>${esc(T('presets'))}</summary><div class="dl-group__body">
        <p class="dl-help">${esc(T('presetsHelp'))}</p>
        <div class="dl-presets">${presets.map(([k, p]) => `<button type="button" class="dl-preset" data-preset="${esc(k)}" aria-pressed="false"${p.description ? ` title="${esc(p.description)}"` : ''}>
          <span class="dl-dots" aria-hidden="true">${colorsOf(p).map(c => `<i class="dl-dot" data-c="${esc(hex(c) || '')}"></i>`).join('')}</span>
          <span class="dl-preset__l">${esc(p.label)}</span></button>`).join('')}</div></div></details>` : ''}
      ${sc.groups.map(g => `<details class="dl-group"><summary>${esc(g.label)}</summary><div class="dl-group__body">${g.tokens.map(t => control(t, ctx)).join('')}</div></details>`).join('')}
      <div class="dl-checks" data-dl-checks role="status" aria-live="polite" hidden></div>
      ${ctx.tool.data.admin ? `<a class="dl-admin" href="${esc(ctx.tool.data.admin)}">${esc(T('admin'))}</a>` : ''}
    </div>
    <div class="dl-foot">
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-dl-defaults title="${esc(T('defaultsTitle'))}">${esc(T('defaults'))}</button>
      <span class="dl-foot__r">
        <button type="button" class="adm-btn adm-btn--small" data-dl-cancel>${esc(T('cancel'))}</button>
        <button type="submit" class="adm-btn adm-btn--small adm-btn--primary" data-dl-save>${esc(T('save'))}</button>
      </span>
      <small>${esc(T('all'))}</small>
    </div>
  </form>`;
  // Farbpunkte per CSSOM (CSP: keine style-Attribute)
  P.querySelectorAll('.dl-dot[data-c]').forEach(el => { if (el.dataset.c) el.style.setProperty('background', el.dataset.c); });
  bind(ctx);
  marks(ctx);
}

/** Regler auf die aktuellen Werte setzen (nach Vorlage, Standard, Abbrechen) */
function sync(ctx) {
  const P = ctx.panel.body;
  P.querySelectorAll('[data-token]').forEach(el => {
    const v = values[el.dataset.token];
    if (el.type === 'checkbox') el.checked = !!v;
    else if (el.type === 'color') el.value = String(v || '#000000').toLowerCase();
    else el.value = String(v ?? '');
    if (el.type === 'range') { const o = P.querySelector(`[data-out="${CSS.escape(el.dataset.token)}"]`); if (o) o.textContent = fmtNum(v, tokens[el.dataset.token] || {}, ctx); }
  });
  P.querySelectorAll('[data-hex]').forEach(el => { el.value = values[el.dataset.hex] || ''; });
}

/** Hinweise „Vollständig nach dem Speichern“ nur bei geänderten Markup-Tokens; Vorlage markieren */
function marks(ctx) {
  const P = ctx.panel.body;
  P.querySelectorAll('[data-dl-markup]').forEach(el => { const n = el.dataset.dlMarkup; el.hidden = String(values[n]) === String(saved[n]); });
}

function checks(list) {
  const box = C?.panel.body.querySelector('[data-dl-checks]');
  if (!box) return;
  const T = C.t;
  if (!list.length) { box.hidden = true; box.innerHTML = ''; return; }
  const num = x => String(x).replace('.', ',');
  box.innerHTML = `<p>${esc(T('contrast'))}</p><ul>${list.map(c => `<li>${esc(T('contrastLine', { label: tokens[c.token]?.label || c.token, mode: T(c.mode === 'dark' ? 'dark' : 'light'), ratio: num(c.ratio), min: num(c.min) }))}</li>`).join('')}</ul>`;
  box.hidden = false;
}

function bind(ctx) {
  const P = ctx.panel.body, form = P.querySelector('[data-dl]');
  const onInput = e => {
    const el = e.target;
    if (el.dataset.hex !== undefined) {
      const c = hex(el.value);
      if (!c) return;
      values[el.dataset.hex] = c;
      const pick = P.querySelector(`input[type=color][data-token="${CSS.escape(el.dataset.hex)}"]`);
      if (pick) pick.value = c.toLowerCase();
    } else if (el.dataset.token !== undefined) {
      const n = el.dataset.token, t = tokens[n.replace(/@dark$/, '')] || {};
      if (el.type === 'checkbox') values[n] = el.checked;
      else if (el.type === 'range') {
        values[n] = Number(el.value);
        const o = P.querySelector(`[data-out="${CSS.escape(n)}"]`);
        if (o) o.textContent = fmtNum(el.value, t, ctx);
      } else if (el.type === 'color') {
        values[n] = el.value.toUpperCase();
        const h = P.querySelector(`[data-hex="${CSS.escape(n)}"]`);
        if (h) h.value = values[n];
      } else values[n] = el.value;
    } else return;
    P.querySelectorAll('.dl-preset[aria-pressed=true]').forEach(b => b.setAttribute('aria-pressed', 'false'));
    schedule(ctx);
  };
  form.addEventListener('input', onInput);
  form.addEventListener('change', e => { if (e.target.matches('select,input[type=checkbox]')) onInput(e); });
  form.addEventListener('click', e => {
    const p = e.target.closest('[data-preset]');
    if (!p) return;
    const pr = S.schema.presets[p.dataset.preset];
    values = { ...values, ...pr.values };
    sync(ctx);
    P.querySelectorAll('.dl-preset').forEach(b => b.setAttribute('aria-pressed', b === p ? 'true' : 'false'));
    ctx.announce(tx(ctx, 'presetApplied', { label: pr.label }));
    schedule(ctx);
  });
  P.querySelector('[data-dl-defaults]').addEventListener('click', () => { values = { ...S.defaults }; sync(ctx); schedule(ctx); });
  P.querySelector('[data-dl-cancel]').addEventListener('click', () => { revert(ctx); ctx.close(); });
  form.addEventListener('submit', e => { e.preventDefault(); save(ctx); });
}

/** Gespeicherten Stand wieder live zeigen (ohne Server) */
function revert(ctx, say = false) {
  clearTimeout(timer); seq++;
  const was = isDirty();
  values = { ...saved };
  if (base) apply(base);
  sync(ctx); marks(ctx);
  ctx.panel.body.querySelectorAll('.dl-preset').forEach(b => b.setAttribute('aria-pressed', 'false'));
  if (say && was) ctx.toast(tx(ctx, 'reverted'), 'info');
}

async function save(ctx) {
  if (busy) return;
  const btn = ctx.panel.body.querySelector('[data-dl-save]');
  busy = true; clearTimeout(timer); const my = ++seq;
  btn.setAttribute('aria-busy', 'true'); btn.textContent = tx(ctx, 'saving');
  const before = { ...saved };
  try {
    const res = await ctx.fetch(ctx.tool.data.endpoint, { json: { v: values, save: 1 } });
    saved = { ...res.values }; values = { ...res.values }; base = res;
    if (my === seq) apply(res);
    sync(ctx); marks(ctx);
    if (res.fontsFailed?.length) ctx.toast(tx(ctx, 'fontsFailed', { fonts: res.fontsFailed.join(', ') }), 'error');
    // Markup-Varianten geändert: Seite neu laden (Meldung erscheint danach) – nicht, solange der Editor Ungespeichertes hat
    const markupChanged = (S.markup || []).some(n => String(before[n]) !== String(saved[n]));
    if (markupChanged && !window.CMSAdmin?.bar?.dirty) {
      window.CMSAdmin?.toastNext?.(tx(ctx, 'saved'));
      location.reload();
      return;
    }
    ctx.toast(tx(ctx, 'saved') + (markupChanged ? ' ' + tx(ctx, 'reload') : ''), 'ok', 6000);
  } catch (e) {
    ctx.toast(e.message, 'error');
  } finally {
    busy = false;
    btn.removeAttribute('aria-busy'); btn.textContent = tx(ctx, 'save');
  }
}

/** Seitenleiste „Block“ des Seiten-Editors offen? Beide zugleich passen nicht nebeneinander */
function blockDrawerOpen(ctx) {
  return !!ctx.panel.el.getRootNode().querySelector?.('#cms-drawer:not([hidden])');
}

export default {
  async mount(ctx) {
    C = ctx;
    const P = ctx.panel.body;
    if (blockDrawerOpen(ctx)) { P.innerHTML = `<p class="dl-err" role="alert">${esc(tx(ctx, 'drawerBusy'))}</p>`; return; }
    P.innerHTML = `<p class="dl-wait" role="status">${esc(tx(ctx, 'loading'))}</p>`;
    try {
      S = await ctx.fetch(ctx.tool.data.endpoint);
    } catch (e) {
      P.innerHTML = `<p class="dl-err" role="alert">${esc(tx(ctx, 'loadErr'))} ${esc(e.message || '')}</p>`;
      return;
    }
    tokens = {};
    S.schema.groups.forEach(g => g.tokens.forEach(t => { tokens[t.name] = t; }));
    saved = { ...S.values }; values = { ...S.values }; base = S.saved;
    adopt(base);
    render(ctx);
    checks(base.checks || []);
  },
  show(ctx) {
    if (!S) return this.mount(ctx);
    if (blockDrawerOpen(ctx)) { ctx.toast(tx(ctx, 'drawerBusy'), 'info'); ctx.close(); }
  },
  // Schließen (×, Esc, anderes Werkzeug): Ungespeichertes nicht stehen lassen – gespeicherter Stand wieder live
  hide(ctx) {
    if (S && isDirty()) revert(ctx, true);
  },
};
