/*
 * KI-Assistent der Redaktion (Core\AI\Assist, Endpunkte /admin/api/ai/*, Anweisungen in app/AI/Prompts.php).
 *
 * Grundsatz: Die KI macht nur VORSCHLÄGE. Nichts wird ohne Prüfung gespeichert – Vorschläge landen erst nach
 * „Übernehmen“ im Feld bzw. Formular (gespeichert wird dann wie immer), Alt-Texte beim Hochladen müssen bestätigt werden.
 *
 *  - Text-Assistent: Knopf „✦ KI“ in jeder Formatierungsleiste (Rich-Text, auch schwebend auf der Website) und an Textfeldern
 *  - Übersetzen: Prüfansicht Quelle | Vorschlag (bearbeitbar) je Feld; vorhandene Übersetzungen nur nach Bestätigung ersetzen
 *  - SEO: Karte in Seiteneinstellungen und Einträgen, SEO-Übersicht (/admin/seo)
 *  - Medien: Alt-Text beim Hochladen und in der Info-Spalte, Sammel-Vorschläge für Bilder ohne Alt-Text
 *  - Support (Antwortvorschlag, Wissensartikel überarbeiten), Tabellen-Baukasten (Felder vorschlagen)
 *
 * Shadow DOM: Dialoge hängen in der gemeinsamen Ebene (_shadow.js → layerBox()); Ereignisse über composedPath.
 * Konfiguration: <script type="application/json" id="cms-ai"> (Core\AI\Assist::clientScript) – fehlt sie, bleibt alles aus.
 */
import { layerBox, IN_ADMIN } from './_shadow.js';
import { t } from './_i18n.js';
import { ask } from './_bar.js';   // gestaltete Rückfrage statt window.confirm()

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const csrf = () => $('#adm-csrf')?.value || '';
const SPARK = '<span class="kia-spark" aria-hidden="true">✦</span>';

let CFG;
/** Konfiguration (null = kein Assistent) */
export function cfg() {
  if (CFG === undefined) { try { CFG = JSON.parse($('#cms-ai')?.textContent || 'null'); } catch { CFG = null; } }
  return CFG;
}
const can = cap => !!cfg()?.[cap];

// ------------------------------------------------------------------ Server
async function api(path, body, signal, method = 'POST') {
  let r;
  try {
    r = await fetch(cfg().base + path, {
      method, credentials: 'same-origin', signal,
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf(), ...(method === 'POST' ? { 'Content-Type': 'application/json' } : {}) },
      body: method === 'POST' ? JSON.stringify(body || {}) : undefined,
    });
  } catch (e) {
    if (e.name === 'AbortError') throw e;
    throw new Error(t('Keine Verbindung zum Server. Bitte später erneut versuchen.'));
  }
  const data = await r.json().catch(() => null);
  if (data && 'quota' in data) setQuota(data.quota);
  if (!data) throw new Error(r.status === 419 ? t('Sitzung abgelaufen – bitte die Seite neu laden.') : r.status === 401 ? t('Sie sind nicht mehr angemeldet.') : t('Die KI-Anfrage ist fehlgeschlagen ({n}).', { n: r.status }));
  if (!r.ok || data.ok === false) throw new Error(data.error || t('Die KI-Anfrage ist fehlgeschlagen ({n}).', { n: r.status }));
  return data;
}
/** Prüf-Ebene (Core\Review): Übernahme wurde zur Freigabe eingereicht statt gespeichert */
const reviewHtml = rv => `<p class="kia-note" role="status">${esc(rv.message)} <a href="${esc(rv.url)}">${esc(t('Eingereicht ansehen →'))}</a></p>`;
function setQuota(left) {
  if (!cfg()) return;
  cfg().quota.left = left;
  $$('[data-kia-quota]', layerBox()).concat($$('[data-kia-quota]')).forEach(q => { q.textContent = quotaText(); });
}
const quotaText = () => { const q = cfg()?.quota; return q && q.left !== null && q.left !== undefined ? t('Heute noch {n} KI-Aufrufe', { n: q.left }) : ''; };

// ------------------------------------------------------------------ Bausteine: Dialog, Arbeitsanzeige, Diff
/** Modaler Dialog in der Ebene (Website: Shadow DOM, Verwaltung: body). Wird beim Schließen entfernt. */
function dialog({ title, wide = false, body = '', foot = '' }) {
  const dlg = d.createElement('dialog');
  const id = 'kia-' + Math.random().toString(36).slice(2, 8);
  dlg.className = 'adm-dialog kia-dlg' + (wide ? ' kia-dlg--wide' : ' adm-dialog--small');
  dlg.setAttribute('aria-labelledby', id + '-t');
  dlg.innerHTML = `<div class="adm-dialog__head kia-dlg__head"><h2 id="${id}-t">${SPARK} ${esc(title)}</h2>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-kia-close aria-label="${esc(t('Schließen'))}">✕</button></div>
    <div class="kia-dlg__body">${body}</div>
    <div class="kia-dlg__foot"><span class="kia-quota adm-muted" data-kia-quota>${esc(quotaText())}</span>${foot}</div>`;
  layerBox().append(dlg);
  const api2 = { dlg, body: $('.kia-dlg__body', dlg), foot: $('.kia-dlg__foot', dlg), close: () => dlg.close(), onClose: null };
  $('[data-kia-close]', dlg).addEventListener('click', () => dlg.close());
  dlg.addEventListener('close', () => { api2.onClose?.(); dlg.remove(); });
  dlg.showModal();
  return api2;
}

/** Arbeitsanzeige mit „Abbrechen“ in einem Container; gibt { signal, done() } zurück */
function busy(box, text = t('Die KI arbeitet …')) {
  const ac = new AbortController();
  const el = d.createElement('div');
  el.className = 'kia-busy';
  el.setAttribute('role', 'status');
  el.innerHTML = `<span class="kia-spinner" aria-hidden="true"></span><span data-kia-busytext>${esc(text)}</span>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost">${esc(t('Abbrechen'))}</button>`;
  $('button', el).addEventListener('click', () => ac.abort());
  box.append(el);
  return { signal: ac.signal, abort: () => ac.abort(), set: s => { $('[data-kia-busytext]', el).textContent = s; }, done: () => el.remove() };
}

function errorBox(box, msg) {
  const p = d.createElement('p');
  p.className = 'kia-error';
  p.setAttribute('role', 'alert');
  p.textContent = msg;
  box.append(p);
  return p;
}
const isAbort = e => e?.name === 'AbortError';
/** Übernahme für den Verlauf melden (nur Metadaten, ohne Inhalt) – Fehler egal */
function applied(kind, action = '', ref = contextRef()) {
  api('/applied', { kind, action, ...ref }).catch(() => {});
}

function warnList(list) {
  return list?.length ? `<ul class="kia-warn" role="note">${list.map(w => `<li>${esc(w)}</li>`).join('')}</ul>` : '';
}

/** Reiner Text aus HTML */
function plain(html) { const x = d.createElement('div'); x.innerHTML = String(html).replace(/<br\s*\/?>/gi, '\n').replace(/<\/(p|li|h[1-6]|ul|ol|div)>/gi, '$&\n'); return (x.textContent || '').replace(/ /g, ' ').replace(/\n{3,}/g, '\n\n').trim(); }

/** Wort-Diff (LCS) als HTML mit <del>/<ins> */
function diffHtml(a, b) {
  const norm = x => x.replace(/[ \t]*\n\s*/g, '\n').split(/(\s+)/).filter(Boolean).map(w => (/^\s+$/.test(w) ? (w.includes('\n') ? '\n' : ' ') : w));
  const A = norm(a), B = norm(b);
  if (A.length * B.length > 2500000) return `<ins>${esc(b)}</ins>`;
  const n = A.length, m = B.length, L = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
  for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) L[i][j] = A[i] === B[j] ? L[i + 1][j + 1] + 1 : Math.max(L[i + 1][j], L[i][j + 1]);
  let i = 0, j = 0, out = '';
  const push = (tag, s) => { if (!s.trim()) { if (tag !== 'del') out += s; return; } out += tag ? `<${tag}>${esc(s)}</${tag}>` : esc(s); };
  while (i < n && j < m) {
    if (A[i] === B[j]) { push('', A[i]); i++; j++; }
    else if (L[i + 1][j] >= L[i][j + 1]) { push('del', A[i]); i++; }
    else { push('ins', B[j]); j++; }
  }
  while (i < n) push('del', A[i++]);
  while (j < m) push('ins', B[j++]);
  return out.replace(/<\/del>(\s*)<del>/g, '$1').replace(/<\/ins>(\s*)<ins>/g, '$1');
}

/** Zeichenzähler-Anzeige für ein Eingabefeld */
function meter(input, max) {
  const m = d.createElement('span');
  m.className = 'kia-meter';
  m.setAttribute('aria-live', 'polite');
  const upd = () => { const n = input.value.length; m.textContent = `${n}/${max}`; m.classList.toggle('is-over', n > max); };
  input.addEventListener('input', upd); upd();
  return m;
}

/** Formularfeld setzen (auch Rich-Text-Felder mit verstecktem Eingabefeld) und Änderung melden */
function setField(el, value, html = false) {
  if (!el) return false;
  const rte = el.closest?.('.rte');
  if (rte && el.type === 'hidden') {
    const area = $('.rte-area', rte);
    area.innerHTML = value;
    area.dispatchEvent(new Event('input', { bubbles: true }));
  } else {
    el.value = html ? plain(value) : value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  flash(rte || el);
  return true;
}
function flash(el) { el?.classList?.remove('kia-flash'); void el?.offsetWidth; el?.classList?.add('kia-flash'); }
function fieldValue(el) { if (!el) return ''; const rte = el.closest?.('.rte'); return rte && el.type === 'hidden' ? $('.rte-area', rte).innerHTML : el.value; }
const byName = (root, name) => root?.querySelector(`[name="${CSS.escape(name)}"]:not([type=checkbox])`);

/** Sprache der Inhalte, die gerade bearbeitet werden */
function contentLang() {
  const c = cfg();
  const box = $('[data-kia-page],[data-kia-entry]');
  if (box) { try { return JSON.parse(box.dataset.kiaPage || box.dataset.kiaEntry).lang || c.lang; } catch {} }
  const q = new URLSearchParams(location.search).get('lang');
  if (q && c.langs[q]) return q;
  if (!IN_ADMIN) { const l = (d.documentElement.lang || '').slice(0, 2).toLowerCase(); if (c.langs[l]) return l; }
  return c.lang;
}

/** Zusammenhang für „Freier Auftrag“: Seite/Eintrag */
function contextRef() {
  try {
    const ed = $('#cms-editor-config'); if (ed) { const c = JSON.parse(ed.textContent); return { page: c.page?.id, entry: c.entry }; }
  } catch {}
  const p = $('[data-kia-page]'); if (p) return { page: JSON.parse(p.dataset.kiaPage).id };
  const e = $('[data-kia-entry]'); if (e) { const c = JSON.parse(e.dataset.kiaEntry); return { entry: { table: c.table, id: c.id } }; }
  return {};
}

// ================================================================== 1. Text-Assistent
const ACTIONS = () => [['improve', t('Verbessern')], ['shorten', t('Kürzen')], ['expand', t('Erweitern')], ['simple', t('Einfacher')], ['fix', t('Korrigieren')]];

/** Knopf für Formatierungsleisten (Rich.barHtml) */
export function aiBarHtml() {
  if (!can('text')) return '';
  return `<span class="rte-group kia-rtegroup"><button type="button" data-cmd="ai" class="kia-rtebtn" aria-haspopup="dialog" aria-label="${esc(t('KI-Assistent'))}" title="${esc(t('KI-Assistent: verbessern, kürzen, vereinfachen, schreiben …'))}">${SPARK}<span>${esc(t('KI'))}</span></button></span>`;
}

/** Aufruf aus Rich.exec(area, 'ai') – Rich-Text-Bereich (Seitenleiste, Formular oder direkt im Text) */
export function aiExec(area) {
  const mode = area.closest('.rte')?.dataset.mode || area.dataset.editMode || 'rich';
  const root = area.getRootNode();
  const sel = root !== d && typeof root.getSelection === 'function' ? root.getSelection() : getSelection();
  let range = null, selHtml = '';
  if (sel?.rangeCount && !sel.isCollapsed && area.contains(sel.anchorNode) && area.contains(sel.focusNode)) {
    range = sel.getRangeAt(0).cloneRange();
    const box = d.createElement('div'); box.append(range.cloneContents()); selHtml = box.innerHTML;
  }
  const blocky = s => /<(p|ul|ol|li|h[2-4]|blockquote)\b/i.test(s);
  const format = mode === 'inline' ? 'inline' : range ? (blocky(selHtml) ? 'rich' : 'inline') : 'rich';
  openText({
    format, selection: !!range,
    value: range ? selHtml : area.innerHTML.replace(/^<p><br><\/p>$/, ''),
    apply: html => {
      area.focus();
      if (range) {
        sel.removeAllRanges(); sel.addRange(range);
        range.deleteContents();
        const tpl = d.createElement('template'); tpl.innerHTML = html;
        range.insertNode(tpl.content);
      } else area.innerHTML = html || (mode === 'rich' ? '<p><br></p>' : '');
      area.dispatchEvent(new Event('input', { bubbles: true }));
      flash(area);
    },
  });
}

/** Textfeld (textarea): Auswahl oder ganzer Inhalt */
function aiTextarea(ta) {
  const s = ta.selectionStart, e = ta.selectionEnd, has = e > s;
  openText({
    format: 'plain', selection: has, value: has ? ta.value.slice(s, e) : ta.value,
    apply: text => {
      ta.value = has ? ta.value.slice(0, s) + text + ta.value.slice(e) : text;
      ta.dispatchEvent(new Event('input', { bubbles: true }));
      ta.focus(); flash(ta);
    },
  });
}

/** Dialog des Text-Assistenten */
function openText({ format, value, selection, apply, ctx, lang }) {
  const empty = !plain(format === 'plain' ? esc(value) : value);
  const tones = [['sachlich', t('sachlich')], ['freundlich', t('freundlich')], ['foermlich', t('förmlich')]];
  const D = dialog({
    title: t('KI-Assistent'), wide: true,
    body: `<p class="kia-scope">${esc(selection ? t('Bearbeitet wird der markierte Text.') : empty ? t('Das Feld ist leer – beschreiben Sie unter „Freier Auftrag“, was entstehen soll.') : t('Bearbeitet wird der ganze Inhalt des Feldes. Tipp: Text markieren, um nur einen Teil zu ändern.'))}</p>
      <div class="kia-tools" role="group" aria-label="${esc(t('Umschreiben'))}">
        ${ACTIONS().map(([a, l]) => `<button type="button" class="adm-btn adm-btn--small" data-act="${a}"${empty ? ' disabled' : ''}>${esc(l)}</button>`).join('')}
        <span class="kia-tone"><label class="adm-sr" for="kia-tone">${esc(t('Ton'))}</label><select id="kia-tone"${empty ? ' disabled' : ''}>${tones.map(([k, l]) => `<option value="${k}">${esc(l)}</option>`).join('')}</select>
        <button type="button" class="adm-btn adm-btn--small" data-act="tone"${empty ? ' disabled' : ''}>${esc(t('Ton ändern'))}</button></span>
      </div>
      <details class="kia-free"${empty ? ' open' : ''}><summary>${esc(t('Freier Auftrag'))}</summary>
        <label class="adm-sr" for="kia-instr">${esc(t('Auftrag an die KI'))}</label>
        <textarea id="kia-instr" rows="2" maxlength="1000" placeholder="${esc(t('Schreibe … z. B. „Schreibe eine kurze Einleitung zu unseren Sprechzeiten“ oder „Formuliere als Aufzählung“'))}"></textarea>
        <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-act="free">${esc(t('Schreiben'))}</button></details>
      <div class="kia-result" data-kia-result hidden></div>
      <div data-kia-status></div>
      <p class="kia-rule">${esc(t('Die KI erfindet keine Fakten: Fehlende Angaben erscheinen als [bitte ergänzen: …]. Bitte jeden Vorschlag prüfen.'))}</p>`,
  });
  const res = $('[data-kia-result]', D.body), status = $('[data-kia-status]', D.body);
  let last = null, current = null;
  const run = async (action, extra = {}) => {
    last = { action, extra };
    status.innerHTML = ''; res.hidden = true;
    $$('[data-act]', D.body).forEach(b => { b.disabled = true; });
    const B = busy(status);
    try {
      const out = await api('/text', { action, text: value, format, lang: lang || contentLang(), ...(ctx || contextRef()), ...extra }, B.signal);
      current = out.text;
      show(out);
    } catch (e) { if (!isAbort(e)) errorBox(status, e.message); }
    finally { B.done(); $$('[data-act]', D.body).forEach(b => { b.disabled = empty && b.dataset.act !== 'free'; }); }
  };
  const show = out => {
    const isHtml = format !== 'plain';
    const before = isHtml ? plain(value) : value, after = isHtml ? plain(out.text) : out.text;
    res.innerHTML = `<div class="kia-result__head"><strong>${esc(t('Vorschlag'))}</strong>
        <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span>
        <span class="fx-seg kia-seg" role="group" aria-label="${esc(t('Ansicht'))}">
          <button type="button" data-view="pv" aria-pressed="true">${esc(t('Vorschau'))}</button>
          <button type="button" data-view="diff" aria-pressed="false"${before ? '' : ' disabled'}>${esc(t('Änderungen'))}</button></span></div>
      ${warnList(out.warnings)}
      <div class="kia-preview${isHtml ? ' kia-preview--html' : ''}" data-pane="pv" tabindex="0" aria-label="${esc(t('Vorschlag'))}"></div>
      <div class="kia-preview kia-diff" data-pane="diff" tabindex="0" hidden aria-label="${esc(t('Änderungen gegenüber dem Original'))}"></div>
      <div class="kia-result__actions">
        <button type="button" class="adm-btn adm-btn--primary" data-kia-apply>${esc(t('Übernehmen'))}</button>
        <button type="button" class="adm-btn" data-kia-again>${esc(t('Erneut'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost" data-kia-reject>${esc(t('Verwerfen'))}</button>
        <small class="adm-muted">${esc(out.model || '')}${out.ms ? ' · ' + (out.ms / 1000).toFixed(1).replace('.', ',') + ' s' : ''}</small></div>`;
    const pv = $('[data-pane=pv]', res);
    if (isHtml) pv.innerHTML = out.text; else pv.textContent = out.text;
    $('[data-pane=diff]', res).innerHTML = diffHtml(before, after);
    res.hidden = false;
    $$('[data-view]', res).forEach(b => b.addEventListener('click', () => {
      $$('[data-view]', res).forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
      $$('[data-pane]', res).forEach(p => { p.hidden = p.dataset.pane !== b.dataset.view; });
    }));
    $('[data-kia-apply]', res).addEventListener('click', () => { D.close(); apply(current); applied('text', last.action, ctx || contextRef()); });
    $('[data-kia-again]', res).addEventListener('click', () => run(last.action, last.extra));
    $('[data-kia-reject]', res).addEventListener('click', () => { res.hidden = true; current = null; });
    $('[data-kia-apply]', res).focus();
  };
  D.body.addEventListener('click', e => {
    const b = e.target.closest('[data-act]'); if (!b || b.disabled) return;
    const a = b.dataset.act;
    if (a === 'free') {
      const instr = $('#kia-instr', D.body).value.trim();
      if (!instr) { $('#kia-instr', D.body).focus(); return; }
      run('free', { instruction: instr });
    } else run(a, a === 'tone' ? { tone: $('#kia-tone', D.body).value } : {});
  });
  (empty ? $('#kia-instr', D.body) : $('[data-act=improve]', D.body))?.focus();
}

/** „✦ KI“ an Textfeldern (textarea) in Formularen */
export function initAi(scope = d) {
  if (!cfg()) return;
  if (can('text')) {
    $$('textarea', scope).forEach(ta => {
      if (ta._kia || ta.disabled || ta.readOnly || ta.hidden) return;
      ta._kia = true;
      if (ta.matches('[data-rrule-raw],[data-kia-off],#meta_description,#kia-instr,[name^="i18n."]') || ta.closest('[data-schema],.kia-dlg,.rr-adv,form[action$="/admin/system"],.fx-i-form,.md-form,.mu')) return;
      const f = ta.closest('.f, .dt-in'); if (!f) return;
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'kia-fieldbtn';
      b.innerHTML = `${SPARK}<span>${esc(t('KI'))}</span>`;
      const label = f.querySelector('label')?.textContent?.trim() || '';
      b.setAttribute('aria-label', t('KI-Assistent für „{field}“', { field: label.replace(/\s*\*$/, '') }));
      b.addEventListener('click', () => aiTextarea(ta));
      ta.insertAdjacentElement('beforebegin', b);
      f.classList.add('kia-hasbtn');
    });
  }
  initPanels(scope);
}

// ================================================================== 2. Übersetzen (Prüfansicht)
/**
 * items: [{ key, label, source, current, html, form? }] · from, to (Codes) · onApply(accepted: {key: text})
 * Vorhandene Übersetzungen (current ≠ leer und ≠ Quelle) sind abgewählt und brauchen eine Bestätigung.
 */
export function translateReview({ items, from, to, onApply, applyLabel }) {
  const L = cfg().langs, name = c => L[c] || c.toUpperCase();
  const human = it => it.current && plain(it.current) !== '' && plain(it.current) !== plain(it.source);
  const D = dialog({
    title: t('Übersetzen: {from} → {to}', { from: name(from), to: name(to) }), wide: true,
    body: `<p class="adm-muted">${esc(t('Wählen Sie die Texte aus, die die KI übersetzen soll. Danach prüfen und bei Bedarf ändern – übernommen wird nur, was angehakt ist.'))}</p>
      <div class="kia-trlist" data-kia-list></div><div data-kia-status></div>`,
    foot: `<button type="button" class="adm-btn kia-btn" data-kia-go>${SPARK} ${esc(t('Vorschläge erzeugen'))}</button>
      <button type="button" class="adm-btn adm-btn--primary" data-kia-take disabled>${esc(applyLabel || t('Ausgewählte übernehmen'))}</button>`,
  });
  const list = $('[data-kia-list]', D.body), status = $('[data-kia-status]', D.body);
  list.innerHTML = items.map((it, i) => `
    <div class="kia-tr${human(it) ? ' is-human' : ''}" data-i="${i}">
      <label class="kia-tr__pick"><input type="checkbox" data-pick${human(it) ? '' : ' checked'}> <strong>${esc(it.label)}</strong>
        ${human(it) ? `<span class="kia-badge kia-badge--warn">${esc(t('Übersetzung vorhanden'))}</span>` : ''}</label>
      <div class="kia-tr__cols">
        <div class="kia-tr__src"><span class="kia-tr__lang">${esc(name(from))}</span><div class="kia-tr__text">${it.html ? it.source : esc(it.source)}</div></div>
        <div class="kia-tr__dst"><span class="kia-tr__lang">${esc(name(to))}</span>
          ${it.html ? `<div class="kia-tr__edit" contenteditable="true" role="textbox" aria-multiline="true" aria-label="${esc(t('Übersetzung: {field}', { field: it.label }))}" data-out></div>`
            : `<textarea rows="${Math.min(6, Math.max(2, Math.ceil(it.source.length / 70)))}" aria-label="${esc(t('Übersetzung: {field}', { field: it.label }))}" data-out></textarea>`}
          ${human(it) ? `<details class="kia-tr__cur"><summary>${esc(t('Bisherige Übersetzung'))}</summary><div>${it.html ? it.current : esc(it.current)}</div></details>` : ''}
          <div data-warn></div></div>
      </div></div>`).join('');
  const rows = () => $$('.kia-tr', list);
  const picked = () => rows().filter(r => $('[data-pick]', r).checked);
  list.addEventListener('change', e => {
    const cb = e.target.closest('[data-pick]'); if (!cb) return;
    const it = items[+cb.closest('.kia-tr').dataset.i];
    if (cb.checked && human(it)) {
      cb.checked = false;
      ask({ title: t('„{field}“ ist bereits übersetzt. Die vorhandene Übersetzung durch den Vorschlag ersetzen?', { field: it.label }), ok: t('Ersetzen'), danger: false })
        .then(ok => { cb.checked = ok; upd(); });
    }
    upd();
  });
  const outOf = r => { const o = $('[data-out]', r); return o.tagName === 'TEXTAREA' ? o.value : o.innerHTML; };
  const upd = () => {
    const n = picked().filter(r => r.dataset.done && outOf(r).trim()).length;
    const take = $('[data-kia-take]', D.foot);
    take.disabled = !n;
    take.textContent = (applyLabel || t('Ausgewählte übernehmen')) + (n ? ` (${n})` : '');
  };
  $('[data-kia-go]', D.foot).addEventListener('click', async e => {
    const go = e.currentTarget;
    const todo = picked().filter(r => !r.dataset.done);
    if (!todo.length) { status.innerHTML = `<p class="adm-muted">${esc(t('Bitte mindestens einen Text auswählen.'))}</p>`; return; }
    status.innerHTML = ''; go.disabled = true;
    const B = busy(status);
    try {
      for (let k = 0; k < todo.length; k += 6) {
        B.set(t('Übersetze {a} von {n} …', { a: Math.min(k + 6, todo.length), n: todo.length }));
        const chunk = todo.slice(k, k + 6);
        const out = await api('/translate', { from, to, items: chunk.map(r => { const it = items[+r.dataset.i]; return { key: String(r.dataset.i), text: it.source, html: !!it.html }; }) }, B.signal);
        chunk.forEach(r => {
          const res = out.items[r.dataset.i]; if (!res) return;
          const o = $('[data-out]', r);
          if (o.tagName === 'TEXTAREA') o.value = res.text; else o.innerHTML = res.text;
          $('[data-warn]', r).innerHTML = `<span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span>` + warnList(res.warnings);
          r.dataset.done = '1'; r.classList.add('is-done');
        });
        upd();
      }
      $('[data-kia-take]', D.foot).focus();
    } catch (err) { if (!isAbort(err)) errorBox(status, err.message); }
    finally { B.done(); go.disabled = false; }
  });
  $('[data-kia-take]', D.foot).addEventListener('click', async () => {
    const acc = {};
    picked().filter(r => r.dataset.done).forEach(r => { const v = outOf(r); if (v.trim()) acc[items[+r.dataset.i].key] = v; });
    if (!Object.keys(acc).length) return;
    const take = $('[data-kia-take]', D.foot);
    take.disabled = true;
    try { await onApply(acc); D.close(); }
    catch (err) { errorBox(status, err.message); take.disabled = false; }
  });
  list.addEventListener('input', upd);
  $('[data-kia-go]', D.foot).focus();
  return D;
}

/** Formular-Übersetzung (Einträge, zentrale Einstellungen): data-kia-translate-form = {from, to, fields: [{name, label, html, source}]} */
function translateForm(btn) {
  const c = JSON.parse(btn.dataset.kiaTranslateForm);
  const form = btn.closest('form') || byName(d, c.fields[0]?.name)?.form || d;
  const items = c.fields.map(f => ({ key: f.name, label: f.label, source: f.source, html: f.html, current: fieldValue(byName(form, f.name)) }));
  translateReview({
    items, from: c.from, to: c.to, applyLabel: t('In das Formular übernehmen'),
    onApply: acc => {
      let n = 0;
      for (const [name, v] of Object.entries(acc)) if (setField(byName(form, name), v, false)) n++;
      note(btn, t('{n} Übersetzungen ins Formular übernommen – bitte prüfen und speichern.', { n }));
      applied('translate', 'form');
    },
  });
}

function note(near, text, cls = '') {
  const box = near.closest('.kia-card')?.querySelector('[data-kia-out]') || near.parentElement;
  const p = d.createElement('p');
  p.className = 'kia-note ' + cls; p.setAttribute('role', 'status'); p.textContent = text;
  box.prepend(p);
  setTimeout(() => p.remove(), 12000);
}

// ================================================================== 3. SEO (Seiteneinstellungen, Einträge, Übersicht)
const LEVEL = { error: '✕', warn: '!', info: 'i', ok: '✓' };
function checksHtml(list) {
  return `<ul class="kia-checks">${list.map(c => `<li class="kia-chk kia-chk--${esc(c.level)}"><span class="kia-chk__i" aria-hidden="true">${LEVEL[c.level] || ''}</span><span class="adm-sr">${esc({ error: t('Fehler'), warn: t('Hinweis'), info: t('Info'), ok: t('In Ordnung') }[c.level] || '')}: </span>${esc(c.text)}</li>`).join('')}</ul>`;
}

/** Vorschlagszeilen mit Eingabefeld, Zähler und „Übernehmen“ ins Formularfeld */
function suggestRows(box, rows) {
  const wrap = d.createElement('div');
  wrap.className = 'kia-sugg';
  rows.forEach(r => {
    if (r.value === undefined || r.value === null) return;
    const row = d.createElement('div');
    row.className = 'kia-sugg__row';
    const id = 'kia-s-' + Math.random().toString(36).slice(2, 7);
    row.innerHTML = `<label for="${id}">${esc(r.label)}</label>
      <div class="kia-sugg__in">${r.multi ? `<textarea id="${id}" rows="4">${esc(r.value)}</textarea>` : `<input id="${id}" value="${esc(r.value)}">`}</div>
      ${r.current !== undefined ? `<p class="kia-sugg__cur">${esc(t('Bisher'))}: ${r.current ? esc(r.current) : `<em>${esc(t('leer'))}</em>`}</p>` : ''}
      ${r.target ? `<button type="button" class="adm-btn adm-btn--small">${esc(t('Übernehmen'))}</button>` : ''}`;
    const inp = $('input,textarea', row);
    if (r.max) $('.kia-sugg__in', row).append(meter(inp, r.max));
    if (r.readonly) inp.readOnly = true;
    $('button', row)?.addEventListener('click', async e => {
      const btn = e.currentTarget;
      if (r.confirm && !(await ask({ title: r.confirm, ok: t('Übernehmen'), danger: false }))) return;
      if (setField(r.target(), inp.value)) { btn.textContent = t('Übernommen ✓'); btn.disabled = true; applied(r.kind || 'seo', r.label); }
    });
    wrap.append(row);
  });
  box.append(wrap);
  return wrap;
}

function initPanels(scope) {
  // Seiteneinstellungen
  $$('[data-kia-page]', scope).forEach(card => {
    if (card._kia) return; card._kia = true;
    const c = JSON.parse(card.dataset.kiaPage), out = $('[data-kia-out]', card);
    const form = d.querySelector('form[action$="/admin/pages/' + c.id + '"]');
    $('[data-kia-check]', card)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; out.innerHTML = '';
      const B = busy(out, t('Seite wird geprüft …'));
      try { const r = await api('/seo-check/' + c.id, null, B.signal, 'GET'); out.innerHTML = `<h3 class="kia-h3">${esc(t('SEO-Check'))}</h3>` + checksHtml(r.checks); }
      catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
      finally { B.done(); b.disabled = false; }
    });
    $('[data-kia-seo]', card)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; out.innerHTML = '';
      const B = busy(out);
      try {
        const r = await api('/seo', { page: c.id }, B.signal);
        const s = r.suggest;
        out.innerHTML = `<h3 class="kia-h3">${esc(t('Vorschläge'))} <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></h3>${warnList([...(r.notes || []), ...(s.warnings || [])])}`;
        suggestRows(out, [
          { label: t('Titel für Suchmaschinen'), value: s.title, max: c.titleMax, current: r.current.meta_title || r.current.title, target: () => form?.querySelector('#meta_title') },
          { label: t('Beschreibung für Suchmaschinen'), value: s.description, max: c.descMax, multi: true, current: r.current.description, target: () => form?.querySelector('#meta_description') },
          ...(c.home ? [] : [{ label: t('Adresse (URL)'), value: s.slug, current: r.current.slug, target: () => form?.querySelector('#slug'),
            confirm: c.published && s.slug !== r.current.slug ? t('Die Seite ist online. Nach dem Speichern ist sie nur noch unter der neuen Adresse erreichbar – alte Links funktionieren nicht mehr. Trotzdem übernehmen?') : '' }]),
          { label: t('Fokus-Thema'), value: s.focus, readonly: true },
          { label: t('Suchbegriffe'), value: (s.keywords || []).join(', '), readonly: true },
        ]);
        out.insertAdjacentHTML('beforeend', `<p class="adm-muted kia-small">${esc(t('Übernommene Werte stehen im Formular links – erst „Speichern“ macht sie wirksam.'))}</p>`);
      } catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
      finally { B.done(); b.disabled = false; }
    });
    $('[data-kia-translate-page]', card)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; out.innerHTML = '';
      const B = busy(out, t('Texte werden gesammelt …'));
      try {
        const r = await api('/page-translate/' + c.id, null, B.signal, 'GET');
        B.done();
        if (!r.items.length) { out.innerHTML = `<p class="adm-muted">${esc(t('Keine übersetzbaren Texte gefunden.'))}</p>`; return; }
        if (r.skipped) out.innerHTML = `<p class="adm-muted">${esc(t('{n} Blöcke gibt es nur in der Standardsprache bzw. nur hier – sie bleiben unverändert.', { n: r.skipped }))}</p>`;
        translateReview({
          items: r.items, from: r.from, to: r.to, applyLabel: t('Übernehmen'),
          onApply: async acc => {
            const blocks = {}; let nForm = 0;
            for (const [k, v] of Object.entries(acc)) {
              const it = r.items.find(x => x.key === k);
              if (it?.form) { if (setField(form?.querySelector(`[name="${it.form}"]`), v)) nForm++; } else blocks[k] = v;
            }
            applied('translate', 'page', { page: c.id });
            let saved = 0, url = '';
            if (Object.keys(blocks).length) { const res = await api('/page-translate/' + c.id, { items: blocks }); saved = res.saved; url = res.url; if (res.review) { out.innerHTML = reviewHtml(res.review); return; } }
            out.innerHTML =`<p class="kia-note" role="status">${esc(t('{a} Seitenangaben ins Formular übernommen (bitte speichern), {b} Inhalte als Entwurf gespeichert.', { a: nForm, b: saved }))}
              ${url ? ` <a href="${esc(url)}">${esc(t('Entwurf ansehen und veröffentlichen →'))}</a>` : ''}</p>`;
          },
        });
      } catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
      finally { B.done(); b.disabled = false; }
    });
  });

  // Eintrag bearbeiten
  $$('[data-kia-entry]', scope).forEach(card => {
    if (card._kia) return; card._kia = true;
    const c = JSON.parse(card.dataset.kiaEntry), out = $('[data-kia-out]', card);
    const form = card.closest('form');
    const formText = skip => $$('textarea,input[type=text],input[type=hidden]', form).filter(i => /^f\[[^\]]+\]$/.test(i.name) && i.name !== skip && i.value.trim() && !/^\d+$/.test(i.value))
      .map(i => (i.type === 'hidden' ? plain(i.value) : i.value)).join('\n').slice(0, 20000);
    $('[data-kia-teaser]', card)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; out.innerHTML = '';
      const B = busy(out);
      try {
        const r = await api('/summary', { entry: { table: c.table, id: c.id }, text: formText(c.teaser.name), max: c.teaser.max, lang: c.lang }, B.signal);
        out.innerHTML = `<h3 class="kia-h3">${esc(c.teaser.label)} <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></h3>${warnList(r.warnings)}`;
        const cur = fieldValue(byName(form, c.teaser.name));
        suggestRows(out, [{ kind: 'summary', label: c.teaser.label, value: r.text, multi: true, max: c.teaser.max, current: cur, target: () => byName(form, c.teaser.name),
          confirm: cur.trim() ? t('„{field}“ ist schon ausgefüllt. Durch den Vorschlag ersetzen?', { field: c.teaser.label }) : '' }]);
      } catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
      finally { B.done(); b.disabled = false; }
    });
    $('[data-kia-seo]', card)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; out.innerHTML = '';
      const B = busy(out);
      try {
        const r = await api('/seo', { entry: { table: c.table, id: c.id }, text: formText('') }, B.signal);
        const s = r.suggest;
        out.innerHTML = `<h3 class="kia-h3">${esc(t('SEO-Vorschläge'))} <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></h3>${warnList([...(r.notes || []), ...(s.warnings || [])])}`;
        const cur = c.desc ? fieldValue(byName(form, c.desc.name)) : '';
        suggestRows(out, [
          { label: t('Titel (Vorschlag)'), value: s.title, max: r.titleMax || 60, readonly: true },
          c.desc ? { label: c.desc.label + ' – ' + t('Beschreibung für Suchmaschinen'), value: s.description, multi: true, max: 155, current: cur, target: () => byName(form, c.desc.name),
            confirm: cur.trim() ? t('„{field}“ ist schon ausgefüllt. Durch den Vorschlag ersetzen?', { field: c.desc.label }) : '' } : { label: t('Beschreibung für Suchmaschinen'), value: s.description, multi: true, max: 155, readonly: true },
          { label: t('Adresse'), value: s.slug, current: r.current.slug, target: () => form.querySelector('#slug') },
          { label: t('Suchbegriffe'), value: (s.keywords || []).join(', '), readonly: true },
        ]);
      } catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
      finally { B.done(); b.disabled = false; }
    });
  });

  $$('[data-kia-translate-form]', scope).forEach(b => { if (b._kia) return; b._kia = true; b.addEventListener('click', () => translateForm(b)); });
  $$('[data-kia-seo-overview]', scope).forEach(initOverview);
  initArea(scope);
  initSupport(scope);
  initSchema(scope);
}

/** SEO-Übersicht: Vorschläge für ausgewählte Zeilen erzeugen, prüfen, übernehmen */
function initOverview(sec) {
  if (sec._kia || !can('text')) return; sec._kia = true;
  const rows = () => $$('[data-kia-row]', sec), gen = $('[data-kia-seo-generate]', sec), take = $('[data-kia-seo-apply]', sec);
  if (!gen) return;
  $('[data-kia-all]', sec)?.addEventListener('change', e => rows().forEach(r => { const cb = $('[data-kia-sel]', r); cb.checked = e.target.checked && r.dataset.hasIssues === '1'; }));
  const upd = () => { const n = rows().filter(r => $('[data-kia-sel]', r).checked && r._sugg).length; take.disabled = !n; take.textContent = t('Ausgewählte übernehmen') + (n ? ` (${n})` : ''); };
  sec.addEventListener('change', upd);
  let B = null;
  gen.addEventListener('click', async () => {
    const todo = rows().filter(r => $('[data-kia-sel]', r).checked && !r._sugg);
    if (!todo.length) { alert(t('Bitte zuerst Zeilen auswählen.')); return; }
    gen.disabled = true;
    B = busy($('.kia-seo__head', sec));
    let i = 0;
    try {
      for (const r of todo) {
        B.set(t('Vorschlag {a} von {n} …', { a: ++i, n: todo.length }));
        const box = $('[data-kia-suggest]', r);
        try {
          const res = r.dataset.kind === 'page' ? await api('/seo', { page: +r.dataset.id }, B.signal)
            : await api('/summary', { entry: { table: r.dataset.table, id: +r.dataset.id }, max: 155 }, B.signal);
          const text = r.dataset.kind === 'page' ? res.suggest.description : res.text;
          const warns = r.dataset.kind === 'page' ? [...(res.suggest.warnings || [])] : (res.warnings || []);
          box.hidden = false;
          box.innerHTML = `<span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span>
            <textarea rows="3" aria-label="${esc(t('Vorschlag bearbeiten'))}">${esc(text)}</textarea>${warnList(warns)}`;
          $('.kia-seo__desc textarea', r).after(meter($('.kia-seo__desc textarea', r), 160));
          r._sugg = true;
          upd();
        } catch (err) {
          if (isAbort(err)) throw err;
          box.hidden = false; box.innerHTML = ''; errorBox(box, err.message);
          if (/Tageslimit|nicht verfügbar|erreichbar/.test(err.message)) throw err;
        }
      }
    } catch (err) { if (!isAbort(err)) errorBox($('.kia-seo__head', sec), err.message); }
    finally { B.done(); gen.disabled = false; }
  });
  take.addEventListener('click', async () => {
    const sel = rows().filter(r => $('[data-kia-sel]', r).checked && r._sugg);
    const items = sel.map(r => { const v = $('[data-kia-suggest] textarea', r).value.trim(); return r.dataset.kind === 'page' ? { page: +r.dataset.id, meta_description: v } : { table: r.dataset.table, id: +r.dataset.id, value: v }; }).filter(x => (x.meta_description ?? x.value));
    if (!items.length || !(await ask({ title: t('{n} Beschreibungen speichern?', { n: items.length }), ok: t('Speichern'), danger: false }))) return;
    take.disabled = true;
    try {
      const res = await api('/seo-apply', { items });
      sel.forEach(r => { const v = $('[data-kia-suggest] textarea', r)?.value.trim(); if (v) { $('[data-kia-current]', r).textContent = v; $('[data-kia-suggest]', r).hidden = true; r._sugg = false; $('[data-kia-sel]', r).checked = false; r.classList.add('is-saved'); } });
      $('.kia-seo__head', sec).insertAdjacentHTML('afterend', res.review ? reviewHtml(res.review) : `<p class="kia-note" role="status">${esc(t('{n} gespeichert.', { n: res.done }))}${res.errors?.length ? ' ' + esc(res.errors.join(' ')) : ''}</p>`);
    } catch (err) { errorBox($('.kia-seo__head', sec), err.message); }
    finally { upd(); }
  });
}

// ================================================================== 4. Medien
/** Bild im Browser verkleinern (max. 1024 px) → data:-URL (JPEG) für /alt */
async function downscale(file) {
  // SVG: createImageBitmap kann kein SVG – über <img> (Skripte laufen dort nicht) auf die Zeichenfläche
  const bmp = file.type === 'image/svg+xml' || /\.svg$/i.test(file.name)
    ? await new Promise((ok, no) => { const im = new Image(); im.onload = () => ok(im); im.onerror = no; im.src = URL.createObjectURL(file); })
    : await createImageBitmap(file);
  if (!bmp.width || !bmp.height) { bmp.width = 1024; bmp.height = 1024; }
  const f = Math.min(1, 1024 / Math.max(bmp.width, bmp.height));
  const c = d.createElement('canvas');
  c.width = Math.max(1, Math.round(bmp.width * f)); c.height = Math.max(1, Math.round(bmp.height * f));
  const ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
  ctx.drawImage(bmp, 0, 0, c.width, c.height);
  bmp.close?.();
  return c.toDataURL('image/jpeg', 0.85);
}

/** Uploader (_media.js): Knopf je Bild in der Warteschlange */
function uploadSlot(i) {
  if (!can('vision') || !i.isImage || i.error) return '';
  return `<span class="kia-mu">
    <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-alt${i.aiBusy ? ' disabled' : ''}>${SPARK} ${esc(i.aiBusy ? t('Die KI schaut sich das Bild an …') : t('Alt-Text vorschlagen'))}</button>
    ${i.aiPending ? `<span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span><button type="button" class="adm-btn adm-btn--small" data-kia-ok>${esc(t('Geprüft ✓'))}</button>` : ''}
    ${i.aiDeco ? `<span class="kia-mu__hint">${esc(t('Die KI hält das Bild für rein dekorativ – dann „dekorativ“ ankreuzen.'))}</span>` : ''}
    ${i.aiError ? `<span class="mu-err" role="alert">${esc(i.aiError)}</span>` : ''}</span>`;
}
async function suggestUpload(up, i) {
  i.aiBusy = true; i.aiError = ''; up.render();
  try {
    const res = await api('/alt', { image: await downscale(i.file), name: i.file.name, langs: [cfg().lang] });
    const alt = res.alt[cfg().lang] || '';
    if (alt) { i.alt = alt; i.aiPending = true; }
    i.aiDeco = !!res.decorative;
  } catch (e) { i.aiError = e.message; }
  i.aiBusy = false; up.render();
  if (i.aiPending) $(`[data-key="${i.key}"] [data-alt]`, up.root)?.focus();
}
function uploadBind(up, i, li) {
  if (!can('vision')) return;
  $('[data-kia-alt]', li)?.addEventListener('click', () => suggestUpload(up, i));
  $('[data-kia-ok]', li)?.addEventListener('click', () => { i.aiPending = false; applied('alt', 'upload', {}); up.render(); $(`[data-key="${i.key}"] [data-alt]`, up.root)?.focus(); });
  // Selbst bearbeitet = geprüft
  $('[data-alt]', li)?.addEventListener('input', () => { if (i.aiPending) { i.aiPending = false; $('.kia-mu .kia-badge', li)?.remove(); $('[data-kia-ok]', li)?.remove(); up.updateActions(); } });
  if (i.aiPending) $('[data-alt]', li)?.classList.add('is-ai');
  // „Für alle vorschlagen“, wenn mehrere Bilder warten
  const acts = $('.mu-actions', up.root);
  const open = up.items.filter(x => x.status === 'wait' && x.isImage && !x.error && !x.alt.trim() && !x.decorative);
  let all = $('[data-kia-all-alt]', acts);
  if (open.length > 1 && !all) {
    all = d.createElement('button'); all.type = 'button'; all.className = 'adm-btn adm-btn--small kia-btn'; all.dataset.kiaAllAlt = '';
    all.innerHTML = `${SPARK} ${esc(t('Alt-Texte für alle vorschlagen'))}`;
    all.addEventListener('click', async () => { all.disabled = true; for (const x of up.items.filter(x => x.status === 'wait' && x.isImage && !x.error && !x.alt.trim() && !x.decorative)) await suggestUpload(up, x); all.remove(); });
    acts.prepend(all);
  } else if (open.length <= 1 && all) all.remove();
}

/** Info-Spalte der Mediathek: Alt-Text (und Übersetzungen) vorschlagen – Übernehmen füllt die Felder (die dann speichern) */
function mediaPanel(finder, m) {
  if (!can('vision') || m.kind !== 'image' || finder.ro) return;
  const form = $('.fx-i-form', finder.$info), alt = form?.alt; if (!alt) return;
  const sec = alt.closest('.fx-i-sec');
  const b = d.createElement('button');
  b.type = 'button'; b.className = 'adm-btn adm-btn--small kia-btn kia-mediabtn';
  b.innerHTML = `${SPARK} ${esc(t('Alt-Text vorschlagen'))}`;
  const out = d.createElement('div'); out.className = 'kia-mediaout'; out.setAttribute('aria-live', 'polite');
  sec.append(b, out);
  b.addEventListener('click', async () => {
    b.disabled = true; out.innerHTML = '';
    const langs = Object.keys(cfg().langs);
    const B = busy(out, t('Die KI schaut sich das Bild an …'));
    try {
      const res = await api('/alt', { media: m.id, pool: finder.pool || '', langs }, B.signal);
      B.done();
      const L = cfg().langs;
      out.innerHTML = `<p class="kia-mediaout__head"><span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></p>
        ${Object.entries(res.alt).map(([l, v]) => `<label class="kia-mediaout__f">${esc(t('Alt-Text'))} (${esc(L[l] || l)})<textarea rows="2" maxlength="250" data-l="${esc(l)}">${esc(v)}</textarea></label>`).join('')}
        ${res.decorative ? `<p class="kia-mu__hint">${esc(t('Die KI hält das Bild für rein dekorativ – dann „dekorativ“ ankreuzen statt Alt-Text.'))}</p>` : ''}
        ${res.title ? `<p class="kia-mediaout__meta">${esc(t('Titel'))}: <b>${esc(res.title)}</b> <button type="button" class="adm-link" data-kia-title>${esc(t('übernehmen'))}</button></p>` : ''}
        ${res.tags?.length ? `<p class="kia-mediaout__meta">${esc(t('Tags'))}: ${res.tags.map(x => `<button type="button" class="kia-tagbtn" data-kia-tag="${esc(x)}">+ ${esc(x)}</button>`).join(' ')}</p>` : ''}
        ${warnList(res.warnings)}
        <div class="adm-row"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-kia-take>${esc(t('Alt-Text übernehmen'))}</button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-kia-drop>${esc(t('Verwerfen'))}</button></div>`;
      $$('textarea', out).forEach(x => x.after(meter(x, 125)));
      $('[data-kia-drop]', out).addEventListener('click', () => { out.innerHTML = ''; b.focus(); });
      $('[data-kia-title]', out)?.addEventListener('click', e => { setField(form.title, res.title); e.currentTarget.disabled = true; });
      $$('[data-kia-tag]', out).forEach(x => x.addEventListener('click', () => {
        const tin = $('[data-tagin]', finder.$info); if (!tin) return;
        tin.value = x.dataset.kiaTag; tin.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })); x.disabled = true;
      }));
      $('[data-kia-take]', out).addEventListener('click', () => {
        $$('textarea[data-l]', out).forEach(x => {
          const v = x.value.trim(); if (!v) return;
          if (x.dataset.l === cfg().lang) { if (form.decorative?.checked) { form.decorative.checked = false; form.decorative.dispatchEvent(new Event('change')); } setField(alt, v); }
          else { const f = form.querySelector(`[name="i18n.${x.dataset.l}.alt"]`); if (f) { f.closest('details')?.setAttribute('open', ''); setField(f, v); } }
        });
        out.innerHTML = `<p class="kia-note" role="status">${esc(t('Übernommen – wird automatisch gespeichert.'))}</p>`;
        applied('alt', 'panel', { media: m.id });
      });
      $('textarea', out)?.focus();
    } catch (err) { if (!isAbort(err)) errorBox(out, err.message); }
    finally { B.done(); b.disabled = false; }
  });
}

/** Leere Info-Spalte: Sammel-Vorschläge für Bilder ohne Alt-Text */
function mediaSummary(finder) {
  const c = finder.meta?.counts || {};
  if (!can('vision') || !c.noalt || finder.ro || finder.mode !== 'library') return;
  const b = d.createElement('button');
  b.type = 'button'; b.className = 'adm-btn adm-btn--small kia-btn';
  b.innerHTML = `${SPARK} ${esc(t('Alt-Texte vorschlagen ({n})', { n: c.noalt }))}`;
  b.addEventListener('click', () => bulkAlt(finder));
  const warn = $('.fx-i-warn', finder.$info);
  (warn || $('.fx-i-empty', finder.$info))?.after(b);
}

async function bulkAlt(finder) {
  const langs = Object.keys(cfg().langs), def = cfg().lang;
  const D = dialog({
    title: t('Alt-Texte für Bilder ohne Alt-Text'), wide: true,
    body: `<p class="adm-muted">${esc(t('Die KI beschreibt ein Bild nach dem anderen. Prüfen und ändern Sie jeden Vorschlag – gespeichert werden nur angehakte Bilder.'))}</p>
      <div data-kia-status></div><ul class="kia-bulk" data-kia-list></ul>`,
    foot: `<button type="button" class="adm-btn adm-btn--primary" data-kia-save disabled>${esc(t('Ausgewählte speichern'))}</button>`,
  });
  const status = $('[data-kia-status]', D.body), list = $('[data-kia-list]', D.body), save = $('[data-kia-save]', D.foot);
  let B = busy(status, t('Bilder werden geladen …'));
  let stop = false;
  D.onClose = () => { stop = true; B?.abort(); };
  const upd = () => { const n = $$('[data-pick]:checked', list).length; save.disabled = !n; save.textContent = t('Ausgewählte speichern') + (n ? ` (${n})` : ''); };
  list.addEventListener('change', upd);
  let items;
  try { items = (await window.CMSMedia.api.list({ noalt: '1', kind: 'image' })).items.filter(m => m.kind === 'image' && m.missing_alt !== false); }
  catch (e) { B.done(); errorBox(status, e.message); return; }
  B.done();
  if (!items.length) { status.innerHTML = `<p>${esc(t('Alle Bilder haben einen Alt-Text.'))}</p>`; return; }
  list.innerHTML = items.map(m => `<li class="kia-bulk__item" data-id="${m.id}">
      <img src="${esc(m.thumb)}" alt="" loading="lazy">
      <div class="kia-bulk__main"><strong>${esc(m.display || m.name)}</strong><div data-slot><span class="adm-muted">${esc(t('wartet …'))}</span></div></div>
      <label class="kia-bulk__pick"><input type="checkbox" data-pick disabled> ${esc(t('speichern'))}</label></li>`).join('');
  B = busy(status);
  let k = 0;
  for (const m of items) {
    if (stop) break;
    B.set(t('Bild {a} von {n} …', { a: ++k, n: items.length }));
    const li = $(`[data-id="${m.id}"]`, list), slot = $('[data-slot]', li);
    li.scrollIntoView({ block: 'nearest' });
    try {
      const res = await api('/alt', { media: m.id, pool: finder.pool || '', langs }, B.signal);
      slot.innerHTML = Object.entries(res.alt).map(([l, v]) => `<label class="kia-bulk__f"><span>${esc(t('Alt-Text'))} ${esc(l.toUpperCase())}</span><input data-l="${esc(l)}" maxlength="250" value="${esc(v)}"></label>`).join('')
        + (res.decorative ? `<p class="kia-mu__hint">${esc(t('Wohl dekorativ – dann nicht speichern, sondern in der Mediathek „dekorativ“ ankreuzen.'))}</p>` : '') + warnList(res.warnings);
      const cb = $('[data-pick]', li); cb.disabled = false; cb.checked = !res.decorative && !!res.alt[def];
      li.classList.add('is-done');
      upd();
    } catch (e) {
      if (isAbort(e)) break;
      slot.innerHTML = ''; errorBox(slot, e.message);
      if (/Tageslimit|nicht verfügbar|erreichbar/.test(e.message)) break;
    }
  }
  B.done();
  save.addEventListener('click', async () => {
    const sel = $$('.kia-bulk__item', list).filter(li => $('[data-pick]', li).checked);
    const payload = sel.map(li => ({ id: +li.dataset.id, alt: Object.fromEntries($$('input[data-l]', li).map(i => [i.dataset.l, i.value.trim()]).filter(([, v]) => v)) }));
    save.disabled = true;
    try {
      const res = await api('/alt-apply', { items: payload, pool: finder.pool || '' });
      res.saved.forEach(id => { const li = $(`[data-id="${id}"]`, list); li?.classList.add('is-saved'); const cb = li && $('[data-pick]', li); if (cb) { cb.checked = false; cb.disabled = true; } });
      status.innerHTML = (res.review ? reviewHtml(res.review) : '') + (res.saved.length || !res.review ? `<p class="kia-note" role="status">${esc(t('{n} Alt-Texte gespeichert.', { n: res.saved.length }))}${res.errors?.length ? ' ' + esc(res.errors.join(' ')) : ''}</p>` : '');
      finder.load?.();
    } catch (e) { errorBox(status, e.message); }
    finally { upd(); }
  });
}

/** Fehlende Alt-Text-Übersetzungen: vorhandene Alt-Texte der Standardsprache in Sprache `to` übersetzen (Text-KI), prüfen, speichern */
async function bulkAltLang(to, finder) {
  const from = cfg().lang;
  const D = dialog({
    title: t('Alt-Texte übersetzen ({lang})', { lang: to.toUpperCase() }), wide: true,
    body: `<p class="adm-muted">${esc(t('Die KI übersetzt die vorhandenen Alt-Texte. Prüfen und ändern Sie jeden Vorschlag – gespeichert werden nur angehakte Bilder.'))}</p>
      <div data-kia-status></div><ul class="kia-bulk" data-kia-list></ul>`,
    foot: `<button type="button" class="adm-btn adm-btn--primary" data-kia-save disabled>${esc(t('Ausgewählte speichern'))}</button>`,
  });
  const status = $('[data-kia-status]', D.body), list = $('[data-kia-list]', D.body), save = $('[data-kia-save]', D.foot);
  let B = busy(status, t('Bilder werden geladen …'));
  D.onClose = () => B?.abort();
  const upd = () => { const n = $$('[data-pick]:checked', list).length; save.disabled = !n; save.textContent = t('Ausgewählte speichern') + (n ? ` (${n})` : ''); };
  list.addEventListener('change', upd);
  let items;
  try { items = (await window.CMSMedia.api.list({ missing_lang: to, kind: 'image' })).items.filter(m => m.kind === 'image' && (m.alt || '').trim() && !m.decorative); }
  catch (e) { B.done(); errorBox(status, e.message); return; }
  B.done();
  if (!items.length) { status.innerHTML = `<p>${esc(t('Alle Alt-Texte sind übersetzt.'))}</p>`; return; }
  list.innerHTML = items.map(m => `<li class="kia-bulk__item" data-id="${m.id}">
      <img src="${esc(m.thumb)}" alt="" loading="lazy">
      <div class="kia-bulk__main"><strong>${esc(m.display || m.name)}</strong><p class="adm-muted kia-small">${esc(from.toUpperCase())}: ${esc(m.alt)}</p><div data-slot><span class="adm-muted">${esc(t('wartet …'))}</span></div></div>
      <label class="kia-bulk__pick"><input type="checkbox" data-pick disabled> ${esc(t('speichern'))}</label></li>`).join('');
  B = busy(status, t('Übersetze …'));
  try {
    // Stapel zu 25 – eine Anfrage je Stapel statt je Bild
    for (let i = 0; i < items.length; i += 25) {
      const chunk = items.slice(i, i + 25);
      B.set(t('Bild {a} von {n} …', { a: Math.min(i + 25, items.length), n: items.length }));
      const out = await api('/translate', { from, to, items: chunk.map(m => ({ key: String(m.id), text: m.alt, html: false })) }, B.signal);
      chunk.forEach(m => {
        const li = $(`[data-id="${m.id}"]`, list), slot = $('[data-slot]', li), r = out.items?.[String(m.id)];
        if (!r) { slot.innerHTML = `<span class="adm-muted">${esc(t('Kein Vorschlag.'))}</span>`; return; }
        slot.innerHTML = `<label class="kia-bulk__f"><span>${esc(t('Alt-Text'))} ${esc(to.toUpperCase())}</span><input data-l="${esc(to)}" maxlength="250" value="${esc(r.text)}"></label>` + warnList(r.warnings);
        const cb = $('[data-pick]', li); cb.disabled = false; cb.checked = !!r.text;
        li.classList.add('is-done');
      });
      upd();
    }
  } catch (e) { if (!isAbort(e)) errorBox(status, e.message); }
  B.done();
  save.addEventListener('click', async () => {
    const sel = $$('.kia-bulk__item', list).filter(li => $('[data-pick]', li).checked);
    const payload = sel.map(li => ({ id: +li.dataset.id, alt: Object.fromEntries($$('input[data-l]', li).map(i => [i.dataset.l, i.value.trim()]).filter(([, v]) => v)) }));
    save.disabled = true;
    try {
      const res = await api('/alt-apply', { items: payload, pool: finder.pool || '', mode: 'translate' });
      res.saved.forEach(id => { const li = $(`[data-id="${id}"]`, list); li?.classList.add('is-saved'); const cb = li && $('[data-pick]', li); if (cb) { cb.checked = false; cb.disabled = true; } });
      status.innerHTML = (res.review ? reviewHtml(res.review) : '') + (res.saved.length || !res.review ? `<p class="kia-note" role="status">${esc(t('{n} Alt-Texte gespeichert.', { n: res.saved.length }))}${res.errors?.length ? ' ' + esc(res.errors.join(' ')) : ''}</p>` : '');
      D.onClose = () => finder.load?.();
    } catch (e) { errorBox(status, e.message); }
    finally { upd(); }
  });
}

// ================================================================== 5. Support & Tabellen-Baukasten
function initSupport(scope) {
  if (!can('text')) return;
  // Antwortvorschlag (nur Support-Team: Auswahl „Interne Notiz“ ist sichtbar)
  const reply = $('form#antworten', scope);
  if (reply && !reply._kia && $('.sp-mode', reply)) {
    reply._kia = true;
    const id = (reply.getAttribute('action') || '').match(/meldung\/(\d+)/)?.[1];
    const ta = $('#sp-reply-body', reply);
    if (id && ta) {
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'adm-btn adm-btn--small kia-btn';
      b.innerHTML = `${SPARK} ${esc(t('Antwortvorschlag'))}`;
      const out = d.createElement('div'); out.setAttribute('aria-live', 'polite');
      ta.closest('.f').append(b, out);
      b.addEventListener('click', async () => {
        b.disabled = true; out.innerHTML = '';
        const B = busy(out);
        try {
          const r = await api('/support-reply', { issue: +id }, B.signal);
          out.innerHTML = `<div class="kia-box"><p><span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span>${r.sources?.length ? ` <small class="adm-muted">${esc(t('Grundlage: {list}', { list: r.sources.join(', ') }))}</small>` : ''}</p>
            ${warnList(r.warnings)}<textarea rows="8" aria-label="${esc(t('Antwortvorschlag bearbeiten'))}">${esc(r.text)}</textarea>
            <div class="adm-row"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-take>${esc(t('In die Nachricht übernehmen'))}</button>
            <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-drop>${esc(t('Verwerfen'))}</button></div></div>`;
          $('[data-take]', out).addEventListener('click', async () => {
            if (ta.value.trim() && !(await ask({ title: t('Die Nachricht enthält schon Text. Ersetzen?'), ok: t('Ersetzen'), danger: false }))) return;
            setField(ta, $('textarea', out).value); out.innerHTML = ''; ta.focus(); applied('support', '', { issue: +id });
          });
          $('[data-drop]', out).addEventListener('click', () => { out.innerHTML = ''; });
        } catch (e) { if (!isAbort(e)) errorBox(out, e.message); }
        finally { B.done(); b.disabled = false; }
      });
    }
  }
  // Wissensartikel überarbeiten
  const art = $('form.sp-article-form', scope);
  if (art && !art._kia) {
    art._kia = true;
    const title = $('#ae-title', art), body = $('#ae-body', art);
    const b = d.createElement('button');
    b.type = 'button'; b.className = 'adm-btn adm-btn--small kia-btn';
    b.innerHTML = `${SPARK} ${esc(t('Mit KI überarbeiten'))}`;
    const out = d.createElement('div'); out.setAttribute('aria-live', 'polite');
    body.closest('.f').append(b, out);
    b.addEventListener('click', async () => {
      b.disabled = true; out.innerHTML = '';
      const B = busy(out);
      try {
        const r = await api('/kb-polish', { title: title.value, body: body.value }, B.signal);
        out.innerHTML = `<div class="kia-box"><p><span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span> <small class="adm-muted">${esc(t('Namen, Adressen und Kundendaten wurden entfernt – bitte trotzdem prüfen.'))}</small></p>${warnList(r.warnings)}
          <label class="kia-box__f">${esc(t('Titel'))}<input value="${esc(r.title)}" data-t></label>
          <label class="kia-box__f">${esc(t('Text'))}<textarea rows="12" data-b>${esc(r.body)}</textarea></label>
          <div class="kia-preview kia-diff" tabindex="0" aria-label="${esc(t('Änderungen gegenüber dem Original'))}">${diffHtml(body.value, r.body)}</div>
          <div class="adm-row"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-take>${esc(t('Übernehmen'))}</button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-drop>${esc(t('Verwerfen'))}</button></div></div>`;
        $('[data-take]', out).addEventListener('click', () => { setField(title, $('[data-t]', out).value); setField(body, $('[data-b]', out).value); out.innerHTML = ''; applied('kb', '', {}); });
        $('[data-drop]', out).addEventListener('click', () => { out.innerHTML = ''; });
      } catch (e) { if (!isAbort(e)) errorBox(out, e.message); }
      finally { B.done(); b.disabled = false; }
    });
  }
}

function initSchema(scope) {
  const schema = $('[data-schema]', scope);
  if (!schema || schema._kia || !can('text')) return;
  schema._kia = true;
  const bar = $('.dt-addfield', schema); if (!bar) return;
  const b = d.createElement('button');
  b.type = 'button'; b.className = 'dt-typebtn kia-btn';
  b.innerHTML = `${SPARK} ${esc(t('Felder vorschlagen …'))}`;
  bar.append(b);
  b.addEventListener('click', () => {
    const types = $$('[data-add-field]', bar).map(x => x.dataset.addField);
    const name = $('#t-name', schema)?.value || '', desc = schema.querySelector('[name="description"]')?.value || '';
    const D = dialog({
      title: t('Felder vorschlagen'), wide: true,
      body: `<div class="f"><label for="kia-sd">${esc(t('Was soll die Tabelle enthalten?'))}</label>
        <textarea id="kia-sd" rows="3" maxlength="2000" placeholder="${esc(t('z. B. Veranstaltungen mit Datum, Ort, Kurzbeschreibung, Anmeldelink und Kategorie'))}">${esc([name, desc].filter(Boolean).join(': '))}</textarea></div>
        <button type="button" class="adm-btn kia-btn" data-go>${SPARK} ${esc(t('Vorschlagen'))}</button>
        <div data-kia-status></div><ul class="kia-fields" data-list></ul>`,
      foot: `<button type="button" class="adm-btn adm-btn--primary" data-take disabled>${esc(t('Ausgewählte Felder hinzufügen'))}</button>`,
    });
    const status = $('[data-kia-status]', D.body), list = $('[data-list]', D.body), take = $('[data-take]', D.foot);
    let fields = [];
    $('[data-go]', D.body).addEventListener('click', async e => {
      const go = e.currentTarget, text = $('#kia-sd', D.body).value.trim();
      if (!text) { $('#kia-sd', D.body).focus(); return; }
      go.disabled = true; status.innerHTML = ''; list.innerHTML = '';
      const B = busy(status);
      try {
        fields = (await api('/schema', { description: text, types }, B.signal)).fields;
        const have = new Set($$('[data-fields] [data-label]', schema).map(i => i.value.trim().toLowerCase()).filter(Boolean));
        fields.forEach(f => { f.exists = have.has(f.label.trim().toLowerCase()); });
        list.innerHTML = `<li class="kia-fields__head"><span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></li>` + fields.map((f, i) => `<li class="kia-fields__row" data-i="${i}">
          <input type="checkbox"${f.exists ? '' : ' checked'} data-pick aria-label="${esc(t('„{field}“ hinzufügen', { field: f.label }))}">
          <input value="${esc(f.label)}" data-label aria-label="${esc(t('Bezeichnung'))}"><span class="kia-fields__type">${esc(f.type_label)}</span>
          ${f.required ? `<span class="kia-badge kia-badge--muted">${esc(t('Pflicht'))}</span>` : ''}${f.exists ? `<span class="kia-badge kia-badge--warn">${esc(t('schon vorhanden'))}</span>` : ''}
          <small class="adm-muted">${esc([f.help, f.options.join(' · ')].filter(Boolean).join(' – '))}</small></li>`).join('');
        take.disabled = false; take.focus();
      } catch (err) { if (!isAbort(err)) errorBox(status, err.message); }
      finally { B.done(); go.disabled = false; }
    });
    take.addEventListener('click', () => {
      const sel = $$('.kia-fields__row', list).filter(r => $('[data-pick]', r).checked);
      sel.forEach(r => {
        const f = fields[+r.dataset.i], btn = $(`[data-add-field="${CSS.escape(f.type)}"]`, bar);
        if (!btn) return;
        btn.click();
        const li = $$('[data-fields] > [data-field]', schema).at(-1); if (!li) return;
        const lab = $('[data-label]', li); lab.value = $('[data-label]', r).value.trim() || f.label; lab.dispatchEvent(new Event('input', { bubbles: true }));
        const req = li.querySelector('input[type=checkbox][name$="[required]"]'); if (req) req.checked = !!f.required;
        const help = li.querySelector('[name$="[help]"]'); if (help) help.value = f.help || '';
        const opt = li.querySelector('textarea[name$="[options]"]'); if (opt && f.options.length) opt.value = f.options.join('\n');
      });
      D.close();
      applied('schema', '', { target: String(sel.length) });
      $$('[data-fields] > [data-field]', schema).at(-1)?.scrollIntoView({ block: 'center' });
    });
    $('#kia-sd', D.body).focus();
  });
}

// ================================================================== 6. Bereich „KLXM Ai“ (/admin/ai/*)
function initArea(scope) {
  initTableGen($('[data-kia-tablegen]', scope));
  initPageGen($('[data-kia-pagegen]', scope));
  // Alt-Texte: Sammel-Vorschläge ohne Mediathek
  $$('[data-kia-bulk-alt]', scope).forEach(b => { if (b._kia) return; b._kia = true; b.addEventListener('click', () => bulkAlt({ pool: '', load: () => location.reload() })); });
  $$('[data-kia-bulk-alt-lang]', scope).forEach(b => { if (b._kia) return; b._kia = true; b.addEventListener('click', () => bulkAltLang(b.dataset.kiaBulkAltLang, { pool: '', load: () => location.reload() })); });
  // Übersetzen: Seite anlegen (Entwurf) und übersetzen
  $$('[data-kia-tc-page]', scope).forEach(b => {
    if (b._kia) return; b._kia = true;
    b.addEventListener('click', async () => {
      const out = b.closest('.kia-tc').querySelector('[data-kia-out]');
      b.disabled = true; out.innerHTML = '';
      const B = busy(out, t('Übersetzung wird angelegt …'));
      try {
        const base = cfg().area.replace(/\/ai$/, '');
        const r0 = await fetch(`${base}/pages/${b.dataset.kiaTcPage}/translate`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ lang: b.dataset.lang }) }).then(x => x.json());
        if (!r0.ok) throw new Error(r0.error || t('Die Übersetzung konnte nicht angelegt werden.'));
        const r = await api('/page-translate/' + r0.id, null, B.signal, 'GET');
        B.done();
        b.closest('li')?.classList.add('is-created');
        translateReview({
          items: r.items, from: r.from, to: r.to, applyLabel: t('Als Entwurf übernehmen'),
          onApply: async acc => {
            const blocks = {}, fields = {};
            for (const [k, v] of Object.entries(acc)) { const it = r.items.find(x => x.key === k); if (it?.form) fields[it.form] = v; else blocks[k] = v; }
            const res = await api('/page-translate/' + r0.id, { items: blocks, fields });
            if (res.review) { out.innerHTML = reviewHtml(res.review); b.textContent = t('Eingereicht ✓'); return; }
            out.innerHTML =`<p class="kia-note" role="status">${esc(t('Entwurf angelegt: {a} Seitenangaben und {b} Inhalte übersetzt.', { a: res.fields, b: res.saved }))}
              <a href="${esc(res.url)}">${esc(t('Entwurf ansehen und veröffentlichen →'))}</a> · <a href="${esc(res.settings)}">${esc(t('Seiteneinstellungen'))}</a></p>`;
            b.textContent = t('Angelegt ✓');
          },
        }).onClose = () => { if (!out.innerHTML.trim()) out.innerHTML = `<p class="adm-muted">${esc(t('Die Übersetzung wurde als Entwurf angelegt (Kopie der Standardsprache).'))}</p>`; };
      } catch (e) { if (!isAbort(e)) errorBox(out, e.message); b.disabled = false; }
      finally { B.done(); }
    });
  });
  // Übersetzen: Alt-Texte
  $$('[data-kia-tc-media]', scope).forEach(b => {
    if (b._kia) return; b._kia = true;
    b.addEventListener('click', () => {
      const c = JSON.parse(b.dataset.kiaTcMedia), out = b.closest('.kia-tc').querySelector('[data-kia-out]');
      translateReview({
        items: c.items, from: c.from, to: c.to, applyLabel: t('Ausgewählte speichern'),
        onApply: async acc => {
          const res = await api('/alt-apply', { items: Object.entries(acc).map(([id, v]) => ({ id: +id, alt: { [c.to]: plain(v) } })) });
          out.innerHTML = res.review ? reviewHtml(res.review) : `<p class="kia-note" role="status">${esc(t('{n} Alt-Texte gespeichert.', { n: res.saved.length }))}${res.errors?.length ? ' ' + esc(res.errors.join(' ')) : ''}</p>`;
        },
      });
    });
  });
  // Texte: freier Schreib-Assistent
  const w = $('[data-kia-write]', scope);
  if (w && !w._kia) {
    w._kia = true;
    const out = $('[data-kia-w-out]', w), res = $('[data-kia-w-result]', w), status = $('[data-kia-w-status]', w);
    let fmt = 'rich';
    const ref = () => {
      const v = $('[data-kia-w-ctx]', w).value;
      if (v.startsWith('page:')) return { page: +v.slice(5) };
      if (v.startsWith('entry:')) { const [, table, id] = v.split(':'); return { entry: { table, id: +id } }; }
      return {};
    };
    $('[data-kia-w-open]', w).addEventListener('click', () => {
      fmt = $('input[name="kia-w-format"]:checked', w).value;
      const src = $('[data-kia-w-src]', w).value.trim();
      const value = fmt === 'rich' && src ? src.split(/\n{2,}/).map(p => `<p>${esc(p).replace(/\n/g, '<br>')}</p>`).join('') : src;
      openText({
        format: fmt, value, selection: false, ctx: ref(),
        apply: text => {
          if (fmt === 'rich') out.innerHTML = text; else out.textContent = text;
          out.classList.toggle('kia-preview--html', fmt === 'rich');
          res.hidden = false; $('[data-kia-w-empty]', w).hidden = true; status.innerHTML = ''; out.focus(); flash(out);
        },
      });
    });
    $('[data-kia-w-copy]', w).addEventListener('click', async e => {
      const b = e.currentTarget;
      try {
        if (fmt === 'rich' && window.ClipboardItem) await navigator.clipboard.write([new ClipboardItem({ 'text/html': new Blob([out.innerHTML], { type: 'text/html' }), 'text/plain': new Blob([plain(out.innerHTML)], { type: 'text/plain' }) })]);
        else await navigator.clipboard.writeText(fmt === 'rich' ? plain(out.innerHTML) : out.innerText);
        b.textContent = t('Kopiert ✓');
      } catch { b.textContent = t('Kopieren nicht möglich – bitte markieren und kopieren.'); }
    });
    const target = $('[data-kia-w-target]', w), page = $('[data-kia-w-page]', w), field = $('[data-kia-w-field]', w), save = $('[data-kia-w-save]', w);
    $('[data-kia-w-insert]', w)?.addEventListener('click', () => { target.hidden = !target.hidden; if (!target.hidden) page.focus(); });
    page?.addEventListener('change', async () => {
      field.innerHTML = ''; field.disabled = true; save.disabled = true;
      if (!page.value) return;
      try {
        const r = await api('/page-fields/' + page.value, null, undefined, 'GET');
        field.innerHTML = r.fields.map(f => `<option value="${esc(f.key)}" data-format="${esc(f.format)}">${esc(f.label)} – ${esc(f.excerpt)}</option>`).join('') || `<option value="">${esc(t('Keine Textfelder'))}</option>`;
        field.disabled = !r.fields.length; save.disabled = !r.fields.length;
        const rich = [...field.options].find(o => o.dataset.format === 'rich'); if (rich) field.value = rich.value;   // Fließtext bevorzugen
      } catch (e) { errorBox(status, e.message); }
    });
    save?.addEventListener('click', async () => {
      const text = fmt === 'rich' ? out.innerHTML : out.innerText;
      if (!plain(text)) return;
      save.disabled = true; status.innerHTML = '';
      try {
        const r = await api('/page-insert', { page: +page.value, key: field.value, text, mode: $('input[name="kia-w-mode"]:checked', w).value });
        status.innerHTML = r.review ? reviewHtml(r.review) : `<p class="kia-note" role="status">${esc(t('Als Entwurf eingefügt.'))} <a href="${esc(r.url)}">${esc(t('Seite ansehen und veröffentlichen →'))}</a></p>`;
      } catch (e) { errorBox(status, e.message); }
      finally { save.disabled = false; }
    });
  }
}

// ================================================================== 7. Generatoren (Tabellen, Seiten)
const MARK_RE = /\[\s*bitte erg(?:ä|ae)nzen[^\]]{0,160}\]/gi;

function initTableGen(box) {
  if (!box || box._kia) return; box._kia = true;
  const cfg0 = JSON.parse(box.dataset.kiaTablegen), status = $('[data-kia-tg-status]', box), pv = $('[data-kia-tg-preview]', box);
  let def = null;
  const typeSel = cur => `<select data-k="type" aria-label="${esc(t('Feldtyp'))}">${Object.entries(cfg0.types).map(([k, l]) => `<option value="${esc(k)}"${k === cur ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>`;
  const render = (errors = {}) => {
    const errList = Object.values(errors);
    pv.hidden = false;
    pv.innerHTML = `<h2 id="kia-tgp-h">${esc(t('Entwurf'))} <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></h2>
      ${errList.length ? `<ul class="kia-warn" role="alert">${errList.map(x => `<li>${esc(x)}</li>`).join('')}</ul>` : ''}
      <div class="kia-tg-head">
        <label>${esc(t('Name (Mehrzahl)'))}<input data-t="name" value="${esc(def.name)}"></label>
        <label>${esc(t('Einzahl'))}<input data-t="singular" value="${esc(def.singular)}"></label>
        <label>${esc(t('Symbol'))}<input data-t="icon" value="${esc(def.icon)}" maxlength="2"></label>
        <label>${esc(t('Adresse der Detailseiten'))}<input data-t="route" value="${esc(def.settings.route)}" placeholder="${esc(t('leer = keine Detailseiten'))}"></label>
      </div>
      <p class="adm-muted kia-small">${esc(def.description || '')}${def.settings.calendar?.enabled ? ' · ' + esc(t('Kalender: an (Beginn „{f}“)', { f: def.settings.calendar.start })) : ''}${def.settings.schema_type ? ' · schema.org: ' + esc(def.settings.schema_type) : ''}</p>
      <div class="kia-tablewrap"><table class="adm-table kia-tg-table"><thead><tr><th scope="col">${esc(t('Bezeichnung'))}</th><th scope="col">${esc(t('Feldtyp'))}</th><th scope="col">${esc(t('Pflicht'))}</th><th scope="col">${esc(t('In der Liste'))}</th><th scope="col">${esc(t('Auswahl / Hilfe'))}</th><th scope="col"><span class="adm-sr">${esc(t('Aktionen'))}</span></th></tr></thead>
      <tbody>${def.fields.map((f, i) => `<tr data-i="${i}">
        <td><input data-k="label" value="${esc(f.label)}" aria-label="${esc(t('Bezeichnung'))}"><small class="adm-muted">${esc(f.name)}${f.visible_if ? ' · ' + esc(t('mit Bedingung')) : ''}${f.target ? ' → ' + esc(cfg0.tables[f.target] || f.target) : ''}</small></td>
        <td>${typeSel(f.type)}</td>
        <td><input type="checkbox" data-k="required"${f.required ? ' checked' : ''} aria-label="${esc(t('Pflicht'))}"></td>
        <td><input type="checkbox" data-k="in_list"${f.in_list ? ' checked' : ''} aria-label="${esc(t('In der Liste'))}"></td>
        <td>${['select', 'multiselect'].includes(f.type) ? `<textarea data-k="options" rows="3" aria-label="${esc(t('Auswahlmöglichkeiten (eine pro Zeile)'))}">${esc(f.options)}</textarea>` : `<input data-k="help" value="${esc(f.help)}" aria-label="${esc(t('Hilfetext'))}">`}</td>
        <td><span class="kia-tg-tools"><button type="button" class="icon-btn" data-mv="-1" aria-label="${esc(t('Nach oben'))}">↑</button><button type="button" class="icon-btn" data-mv="1" aria-label="${esc(t('Nach unten'))}">↓</button><button type="button" class="icon-btn icon-btn--danger" data-rm aria-label="${esc(t('Feld „{field}“ entfernen', { field: f.label }))}">✕</button></span></td></tr>`).join('')}</tbody></table></div>
      <div class="kia-tg-foot">
        <label class="f-check"><input type="checkbox" data-t="examples"> <span>${esc(t('Beispieleinträge anlegen (als Entwurf, deutlich als Beispiel markiert)'))}</span></label>
        <select data-t="examples-n" aria-label="${esc(t('Anzahl Beispieleinträge'))}" disabled><option>1</option><option>2</option><option selected>3</option></select>
        <button type="button" class="adm-btn adm-btn--primary" data-kia-tg-create>${esc(t('Tabelle anlegen'))}</button>
      </div>
      <div data-kia-tg-out aria-live="polite"></div>`;
  };
  const sync = () => {
    ['name', 'singular', 'icon', 'route'].forEach(k => { const i = $(`[data-t="${k}"]`, pv); if (!i) return; if (k === 'route') def.settings.route = i.value.trim(); else def[k] = i.value.trim(); });
    $$('tbody tr', pv).forEach(tr => {
      const f = def.fields[+tr.dataset.i];
      $$('[data-k]', tr).forEach(el => { f[el.dataset.k] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value; });
    });
  };
  pv.addEventListener('change', e => {
    if (e.target.matches('[data-t="examples"]')) { $('[data-t="examples-n"]', pv).disabled = !e.target.checked; return; }
    if (e.target.matches('[data-k="type"]')) { sync(); render(); }
  });
  pv.addEventListener('click', async e => {
    const mv = e.target.closest('[data-mv]'), rm = e.target.closest('[data-rm]');
    if (mv || rm) {
      sync();
      const i = +e.target.closest('tr').dataset.i;
      if (rm) def.fields.splice(i, 1);
      else { const j = i + +mv.dataset.mv; if (j >= 0 && j < def.fields.length) [def.fields[i], def.fields[j]] = [def.fields[j], def.fields[i]]; }
      render(); return;
    }
    if (e.target.closest('[data-kia-tg-create]')) {
      sync();
      const b = e.target.closest('[data-kia-tg-create]'), out = $('[data-kia-tg-out]', pv);
      const ex = $('[data-t="examples"]', pv).checked ? +$('[data-t="examples-n"]', pv).value : 0;
      if (!(await ask({ title: ex ? t('Tabelle „{name}“ mit {n} Beispieleinträgen (Entwurf) anlegen?', { name: def.name, n: ex }) : t('Tabelle „{name}“ anlegen?', { name: def.name }), ok: t('Anlegen'), danger: false }))) return;
      b.disabled = true; out.innerHTML = '';
      try {
        const r = await api('/table-create', { def, examples: ex });
        pv.innerHTML = `<p class="kia-note" role="status">${esc(t('Tabelle „{name}“ angelegt.', { name: def.name }))}${r.examples ? ' ' + esc(t('{n} Beispieleinträge als Entwurf.', { n: r.examples })) : ''}
          <a href="${esc(r.url)}">${esc(t('Zur Tabelle →'))}</a> · <a href="${esc(r.schema)}">${esc(t('Felder & Einstellungen'))}</a></p>`;
      } catch (err) { b.disabled = false; errorBox(out, err.message); }
    }
  });
  $('[data-kia-tg-go]', box).addEventListener('click', async e => {
    const go = e.currentTarget, desc = $('#kia-tg-desc', box).value.trim();
    if (!desc) { $('#kia-tg-desc', box).focus(); return; }
    go.disabled = true; status.innerHTML = '';
    const B = busy(status, t('Die KI entwirft die Tabelle …'));
    try {
      const r = await api('/table-propose', { description: desc }, B.signal);
      def = r.def; render(r.errors || {});
      pv.scrollIntoView({ block: 'start', behavior: 'smooth' });
    } catch (err) { if (!isAbort(err)) errorBox(status, err.message); }
    finally { B.done(); go.disabled = false; }
  });
}

function initPageGen(box) {
  if (!box || box._kia) return; box._kia = true;
  const status = $('[data-kia-pg-status]', box), pv = $('[data-kia-pg-preview]', box);
  let draft = null;
  const setPath = (obj, path, v) => { const k = path.split('.'); let o = obj; k.slice(0, -1).forEach(x => { o = o[/^\d+$/.test(x) ? +x : x]; }); o[k.at(-1)] = v; };
  const fieldHtml = (f, v, path) => {
    const id = 'kia-pgf-' + Math.random().toString(36).slice(2, 8);
    if (f.type === 'repeater') {
      const items = Array.isArray(v) ? v : [];
      return `<fieldset class="kia-pg-rep"><legend>${esc(f.label)}</legend>${items.map((it, i) => `<div class="kia-pg-item">${f.fields.map(sf => fieldHtml(sf, it[sf.name], `${path}.${i}.${sf.name}`)).join('')}</div>`).join('') || `<p class="adm-muted">${esc(t('leer'))}</p>`}</fieldset>`;
    }
    if (v === undefined || v === null) return '';
    const label = `<label for="${id}">${esc(f.label)}</label>`;
    if (f.type === 'richtext' || f.type === 'inline') return `<div class="kia-pg-f">${label}<div id="${id}" class="kia-tr__edit" contenteditable="true" role="textbox" aria-multiline="true" data-p="${esc(path)}" data-html="1">${v}</div></div>`;
    if (f.type === 'select') return `<div class="kia-pg-f">${label}<select id="${id}" data-p="${esc(path)}">${(f.options || []).map(o => `<option${o === v ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select></div>`;
    if (f.type === 'textarea') return `<div class="kia-pg-f">${label}<textarea id="${id}" rows="3" data-p="${esc(path)}">${esc(v)}</textarea></div>`;
    return `<div class="kia-pg-f">${label}<input id="${id}" data-p="${esc(path)}" value="${esc(v)}"></div>`;
  };
  const markers = () => (JSON.stringify(draft).match(MARK_RE) || []);
  const updMarkers = () => {
    const m = [...new Set(markers())], el = $('[data-kia-pg-markers]', pv);
    if (el) el.innerHTML = m.length ? `<strong>${esc(t('{n} Platzhalter müssen vor dem Veröffentlichen ergänzt werden:', { n: m.length }))}</strong><ul>${m.slice(0, 12).map(x => `<li>${esc(x)}</li>`).join('')}</ul>` : `<span class="kia-chk kia-chk--ok">${esc(t('Keine offenen Platzhalter.'))}</span>`;
    if (el) el.className = m.length ? 'kia-warn kia-pg-markers' : 'kia-pg-markers';
  };
  const render = warnings => {
    pv.hidden = false;
    pv.innerHTML = `<h2 id="kia-pgp-h">${esc(t('Entwurf'))} <span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span></h2>${warnList(warnings)}
      <div class="kia-pg-meta">
        <label>${esc(t('Titel'))}<input data-m="title" value="${esc(draft.title)}" maxlength="120"></label>
        <label>${esc(t('Adresse'))}<input data-m="slug" value="${esc(draft.slug)}"></label>
        <label class="kia-pg-meta__wide">${esc(t('Beschreibung für Suchmaschinen'))}<textarea data-m="meta_description" rows="2">${esc(draft.meta_description)}</textarea></label>
      </div>
      <div data-kia-pg-markers aria-live="polite"></div>
      <ol class="kia-pg-sections">${draft.sections.map((s, i) => `<li class="kia-pg-sec" data-i="${i}">
        <div class="kia-pg-sec__head"><strong>${esc(s.label)}</strong><span class="kia-tg-tools">
          <button type="button" class="icon-btn" data-mv="-1" aria-label="${esc(t('Abschnitt nach oben'))}">↑</button><button type="button" class="icon-btn" data-mv="1" aria-label="${esc(t('Abschnitt nach unten'))}">↓</button>
          <button type="button" class="icon-btn icon-btn--danger" data-rm aria-label="${esc(t('Abschnitt „{name}“ entfernen', { name: s.label }))}">✕</button></span></div>
        ${s.fields.map(f => fieldHtml(f, s.data[f.name], `sections.${i}.data.${f.name}`)).join('')}</li>`).join('')}</ol>
      <div class="kia-tg-foot"><button type="button" class="adm-btn adm-btn--primary" data-kia-pg-create>${esc(t('Als Entwurf anlegen'))}</button>
        <span class="adm-muted kia-small">${esc(t('Die Seite ist danach unveröffentlicht und öffnet im Editor.'))}</span></div>
      <div data-kia-pg-out aria-live="polite"></div>`;
    updMarkers();
  };
  pv.addEventListener('input', e => {
    const el = e.target.closest('[data-p],[data-m]'); if (!el) return;
    if (el.dataset.m) draft[el.dataset.m] = el.value;
    else setPath(draft, el.dataset.p, el.dataset.html ? el.innerHTML : el.value);
    updMarkers();
  });
  pv.addEventListener('change', e => { const el = e.target.closest('select[data-p]'); if (el) setPath(draft, el.dataset.p, el.value); });
  pv.addEventListener('click', async e => {
    const mv = e.target.closest('[data-mv]'), rm = e.target.closest('[data-rm]');
    if (mv || rm) {
      const i = +e.target.closest('[data-i]').dataset.i;
      if (rm) draft.sections.splice(i, 1);
      else { const j = i + +mv.dataset.mv; if (j >= 0 && j < draft.sections.length) [draft.sections[i], draft.sections[j]] = [draft.sections[j], draft.sections[i]]; }
      render([]); return;
    }
    const b = e.target.closest('[data-kia-pg-create]'); if (!b) return;
    const out = $('[data-kia-pg-out]', pv);
    b.disabled = true; out.innerHTML = '';
    try {
      const r = await api('/page-create', { draft: { ...draft, sections: draft.sections.map(s => ({ type: s.type, data: s.data })), parent: $('#kia-pg-parent', box)?.value || '', language: $('#kia-pg-lang', box)?.value || '' } });
      pv.innerHTML = `<p class="kia-note" role="status">${esc(t('Seite „{title}“ als Entwurf angelegt.', { title: draft.title }))}
        ${r.markers?.length ? ' ' + esc(t('Noch {n} Platzhalter – veröffentlichen geht erst, wenn sie ergänzt sind.', { n: r.markers.length })) : ''}
        <a href="${esc(r.url)}">${esc(t('Im Editor öffnen →'))}</a> · <a href="${esc(r.settings)}">${esc(t('Seiteneinstellungen'))}</a></p>`;
    } catch (err) { b.disabled = false; errorBox(out, err.message); }
  });
  $('[data-kia-pg-go]', box).addEventListener('click', async e => {
    const go = e.currentTarget, topic = $('#kia-pg-topic', box).value.trim();
    if (!topic) { $('#kia-pg-topic', box).focus(); return; }
    go.disabled = true; status.innerHTML = '';
    const B = busy(status, t('Die KI entwirft die Seite … (das kann eine Minute dauern)'));
    try {
      draft = await api('/page-propose', { topic, facts: $('#kia-pg-facts', box)?.value || '', context: $('#kia-pg-ctx', box)?.value || 'basic', audience: $('#kia-pg-aud', box).value, tone: $('#kia-pg-tone', box).value, language: $('#kia-pg-lang', box)?.value || '',
        blocks: $$('.kia-pg-blocks input:checked', box).map(i => i.value) }, B.signal);
      render(draft.warnings || []);
      pv.scrollIntoView({ block: 'start', behavior: 'smooth' });
    } catch (err) { if (!isAbort(err)) errorBox(status, err.message); }
    finally { B.done(); go.disabled = false; }
  });
}

// ------------------------------------------------------------------ Einbindung
if (cfg()) {
  window.CMSAi = { cfg, aiExec, aiBarHtml, initAi, translateReview, uploadSlot, uploadBind, mediaPanel, mediaSummary };
}
