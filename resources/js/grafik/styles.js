/*
 * Grafik-Editor – Formatvorlagen für Text (Reiter „Vorlagen“ im Bereich Text), wie Absatz- und Zeichenformate in
 * Affinity/InDesign:
 *   Absatzvorlage  ganzer Text: Schrift, Größe, Farbe, OpenType, Zeilen-/Wortabstand, Ausrichtung, Absatz-Einstellungen
 *   Zeichenvorlage markierte Zeichen (bzw. ganzer Text): Schrift, Schnitt, Größe, Farbe, Laufweite, OpenType
 * Anwenden, „Aus Auswahl aktualisieren“ (ändert alle Texte/Läufe mit dieser Vorlage), Löschen.
 *
 * Gespeichert mit der Grafik: data-ge-styles am <svg> (Base64-JSON, nur Arbeitsfassung, Core\Svg prüft das Format),
 * verwendete Vorlage am Text (data-ge-ps) bzw. am Lauf (data-ge-cs). Vorlagen der Website (für alle Grafiken):
 * boot.textStyles, speichern über POST boot.urls.styles (Recht „Eigene Blöcke bauen“). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { getProp, setProp } from './doc.js';
import { readText, layoutText, mapRange, toChars } from './text.js';
import { paraOf } from './typo.js';
import { iconBtn, modal, menu } from './ui.js';

/** Eigenschaften einer Zeichenvorlage */
export const CHAR_PROPS = ['font-family', 'font-weight', 'font-style', 'font-size', 'fill', 'letter-spacing', 'text-decoration', 'font-feature-settings', 'font-variation-settings'];
/** zusätzlich bei Absatzvorlagen (am Text) */
const PARA_ATTRS = ['data-ge-para', 'data-ge-lh', 'text-anchor', 'word-spacing'];

const enc = o => btoa(unescape(encodeURIComponent(JSON.stringify(o))));
const dec = s => { try { const o = JSON.parse(decodeURIComponent(escape(atob(s)))); return o && typeof o === 'object' ? o : null; } catch { return null; } };

/** Vorlagen der Grafik { p: [{n, props, para}], c: [{n, props}] } */
export function docStyles(root) {
  const o = dec(root.getAttribute('data-ge-styles') || '') || {};
  return { p: Array.isArray(o.p) ? o.p : [], c: Array.isArray(o.c) ? o.c : [] };
}
function writeStyles(root, s) {
  if (!s.p.length && !s.c.length) root.removeAttribute('data-ge-styles');
  else root.setAttribute('data-ge-styles', enc(s));
}

export function buildStylesPanel(ed, panel) {
  const texts = () => ed.textEl ? [ed.textEl] : ed.sel.filter(el => el.localName === 'text');
  const site = () => ({ p: ed.boot.textStyles?.p || [], c: ed.boot.textStyles?.c || [] });

  /** Eigenschaften des Ziels: markierte Zeichen (erstes Zeichen) bzw. der Text */
  function capture(kind) {
    const el = texts()[0];
    if (!el) return null;
    const props = {};
    for (const k of CHAR_PROPS) { const v = getProp(el, k, k !== 'font-feature-settings' && k !== 'font-variation-settings' && k !== 'text-decoration' && k !== 'letter-spacing'); if (v && v !== 'none' && v !== 'normal') props[k] = v; }
    const te = ed.textEd;
    if (kind === 'c' && te && te.range[0] !== te.range[1]) {
      const ch = toChars(te.model)[te.range[0]];
      for (const k of CHAR_PROPS) if (ch?.s[k]) props[k] = ch.s[k];
    }
    if (props['font-size']) props['font-size'] = String(parseFloat(props['font-size']));
    const para = {};
    if (kind === 'p') for (const a of PARA_ATTRS) { const v = a === 'text-anchor' ? getProp(el, a, false) : el.getAttribute(a); if (v) para[a] = v; }
    return { props, para };
  }

  /** Absatzvorlage auf einen Text anwenden (Zeichen-Eigenschaften gelten für den ganzen Text, Läufe ohne Zeichenvorlage folgen) */
  function applyPara(el, st) {
    for (const k of CHAR_PROPS) setProp(el, k, st.props[k] ?? null);
    for (const r of el.querySelectorAll('tspan')) if (!r.getAttribute('data-ge-cs')) for (const k of CHAR_PROPS) { r.removeAttribute(k); r.style?.removeProperty(k); }
    for (const a of PARA_ATTRS) {
      const v = st.para?.[a];
      if (a === 'text-anchor' || a === 'word-spacing') setProp(el, a, v || null);
      else if (v) el.setAttribute(a, v); else el.removeAttribute(a);
    }
    el.setAttribute('data-ge-ps', st.n);
    layoutText(el, readText(el));
  }

  /** Zeichenvorlage: Läufe mit data-ge-cs bekommen die Eigenschaften (fehlende = wie der Text) */
  const charPatch = st => { const p = { 'data-ge-cs': st.n }; for (const k of CHAR_PROPS) p[k] = st.props[k] ?? null; return p; };
  function applyChar(st) {
    const te = ed.textEd;
    if (te && te.range[0] !== te.range[1]) {
      const patch = charPatch(st);
      te.model = mapRange(te.model, te.range[0], te.range[1], s => { const n = { ...s, ...patch }; for (const k of Object.keys(n)) if (n[k] === null) delete n[k]; return n; });
      te.dirty = true; te.relayout(); te.ta.setSelectionRange(...te.range); ed.draw(); ed.panels.refresh();
      return true;
    }
    for (const el of texts()) {
      const m = mapRange(readText(el), 0, Infinity, s => { const n = { ...s, ...charPatch(st) }; for (const k of Object.keys(n)) if (n[k] === null) delete n[k]; return n; });
      layoutText(el, m);
    }
    return false;
  }

  function apply(kind, st) {
    if (!texts().length) { toast(t('Bitte zuerst einen Text auswählen.'), 'info'); return; }
    if (kind === 'p') { for (const el of texts()) applyPara(el, st); }
    else if (applyChar(st)) return;
    if (ed.textEd) { ed.textEd.model = readText(ed.textEd.el); ed.textEd.relayout(); ed.textEd.dirty = true; ed.textEd.before = null; ed.draw(); ed.panels.refresh(); return; }
    ed.commit(t('Vorlage „{name}“ angewendet', { name: st.n }));
  }

  /** Alle Texte bzw. Läufe mit der Vorlage neu formatieren (nach dem Aktualisieren) */
  function updateAll(kind, st) {
    if (kind === 'p') for (const el of ed.root.querySelectorAll('text')) { if (el.getAttribute('data-ge-ps') === st.n) applyPara(el, st); }
    else for (const el of ed.root.querySelectorAll('text')) {
      const m = readText(el);
      if (!JSON.stringify(m.paras).includes('"data-ge-cs":' + JSON.stringify(st.n))) continue;
      const patch = charPatch(st);
      layoutText(el, mapRange(m, 0, Infinity, s => { if (s['data-ge-cs'] !== st.n) return s; const n = { ...s, ...patch }; for (const k of Object.keys(n)) if (n[k] === null) delete n[k]; return n; }));
    }
  }

  async function create(kind) {
    const cap = capture(kind);
    if (!cap) { toast(t('Bitte zuerst einen Text auswählen.'), 'info'); return; }
    const r = await modal(ed.el, { title: kind === 'p' ? t('Neue Absatzvorlage') : t('Neue Zeichenvorlage'), ok: t('Anlegen'),
      body: `<label class="f">${esc(t('Name'))}<input name="name" maxlength="60" required autofocus></label><p class="f-help">${esc(kind === 'p' ? t('Übernimmt Schrift, Größe, Farbe, OpenType und die Absatz-Einstellungen des ausgewählten Textes.') : t('Übernimmt Schrift, Schnitt, Größe, Farbe, Laufweite und OpenType der markierten Zeichen.'))}</p>` });
    if (!r) return;
    const n = String(r.name).trim().slice(0, 60);
    const s = docStyles(ed.root);
    const st = { n, props: cap.props, ...(kind === 'p' ? { para: cap.para } : {}) };
    s[kind] = [...s[kind].filter(x => x.n !== n), st];
    writeStyles(ed.root, s);
    apply(kind, st);
    if (!ed.textEd) render();
  }

  async function act(kind, st, from, btn) {
    menu(btn, [
      { label: t('Anwenden'), run: () => apply(kind, st) },
      { label: t('Aus Auswahl aktualisieren (alle Verwendungen)'), disabled: from !== 'doc' || !texts().length, run: () => {
        const cap = capture(kind);
        if (!cap) return;
        const s = docStyles(ed.root);
        const i = s[kind].findIndex(x => x.n === st.n);
        const nst = { ...st, props: cap.props, ...(kind === 'p' ? { para: cap.para } : {}) };
        if (i >= 0) s[kind][i] = nst;
        writeStyles(ed.root, s);
        ed.endText();
        updateAll(kind, nst);
        ed.commit(t('Vorlage „{name}“ aktualisiert', { name: st.n }));
      } },
      { label: t('In die Bibliothek der Website übernehmen'), disabled: from !== 'doc' || !ed.boot.canParts || !ed.boot.urls?.styles, run: () => saveSite(kind, st) },
      { label: t('In diese Grafik übernehmen'), disabled: from !== 'site', run: () => { const s = docStyles(ed.root); s[kind] = [...s[kind].filter(x => x.n !== st.n), st]; writeStyles(ed.root, s); ed.commit(t('Vorlage übernommen')); } },
      { sep: true },
      { label: t('Löschen'), disabled: from !== 'doc', run: () => {
        const s = docStyles(ed.root);
        s[kind] = s[kind].filter(x => x.n !== st.n);
        writeStyles(ed.root, s);
        // Verwendungen bleiben formatiert, nur die Verknüpfung entfällt
        for (const el of ed.root.querySelectorAll(kind === 'p' ? 'text[data-ge-ps]' : 'tspan[data-ge-cs]')) if (el.getAttribute(kind === 'p' ? 'data-ge-ps' : 'data-ge-cs') === st.n) el.removeAttribute(kind === 'p' ? 'data-ge-ps' : 'data-ge-cs');
        ed.commit(t('Vorlage gelöscht'));
      } },
    ]);
  }

  async function saveSite(kind, st) {
    const cur = site();
    cur[kind] = [...cur[kind].filter(x => x.n !== st.n), st];
    const res = await ed.api('POST', ed.boot.urls.styles, { styles: cur });
    if (!res) return;
    ed.boot.textStyles = res.styles;
    toast(t('Vorlage in der Bibliothek der Website gespeichert'));
    render();
  }

  let lastKey = '';
  function render() {
    const s = docStyles(ed.root), w = site();
    const el = texts()[0];
    const curP = el?.getAttribute('data-ge-ps') || '';
    const te = ed.textEd, ch = te ? toChars(te.model)[te.range[0]] : null;
    const curC = ch?.s['data-ge-cs'] || '';
    // unverändert: nicht neu aufbauen (offene Menüs und Fokus bleiben)
    const key = [ed.root.getAttribute('data-ge-styles') || '', JSON.stringify(w), curP, curC, !!el].join('|');
    if (key === lastKey && panel.childElementCount) return;
    lastKey = key;
    const row = (kind, st, from) => {
      const p = st.props || {};
      const on = (kind === 'p' ? curP : curC) === st.n;
      return `<li class="ge-sty${on ? ' is-on' : ''}"><button type="button" class="ge-sty__apply" data-apply="${kind}" data-from="${from}" data-n="${esc(st.n)}" aria-pressed="${on}"
        title="${esc(t('Anwenden'))}"><span class="ge-sty__name" style="font-family:${esc(p['font-family'] || 'inherit')};font-weight:${esc(p['font-weight'] || '400')};font-style:${esc(p['font-style'] || 'normal')}">${esc(st.n)}</span>
        <span class="ge-sty__meta">${esc([p['font-family']?.split(',')[0].replace(/['"]/g, ''), p['font-size'] ? p['font-size'] + ' px' : ''].filter(Boolean).join(' · '))}</span></button>
        ${iconBtn('dots-three', t('Aktionen für „{name}“', { name: st.n }), `data-more="${kind}" data-from="${from}" data-n="${esc(st.n)}" aria-haspopup="menu" aria-expanded="false"`)}</li>`;
    };
    const block = (kind, title) => {
      const own = s[kind], lib = w[kind].filter(x => !own.some(o => o.n === x.n));
      return `<div class="ge-sty__head"><h3 class="ge-h3">${esc(title)}</h3><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-new="${kind}"${el ? '' : ' disabled'}>${ico('plus')} ${esc(t('Aus Auswahl'))}</button></div>`
        + (own.length ? `<ul class="ge-stys">${own.map(x => row(kind, x, 'doc')).join('')}</ul>` : `<p class="ge-note">${esc(t('Noch keine in dieser Grafik.'))}</p>`)
        + (lib.length ? `<p class="ge-note">${esc(t('Bibliothek der Website'))}</p><ul class="ge-stys">${lib.map(x => row(kind, x, 'site')).join('')}</ul>` : '');
    };
    panel.innerHTML = `<div class="ge-form">${block('p', t('Absatzvorlagen'))}${block('c', t('Zeichenvorlagen'))}</div>`;
    const find = (kind, from, n) => (from === 'doc' ? s : w)[kind].find(x => x.n === n);
    panel.querySelectorAll('[data-new]').forEach(b => b.addEventListener('click', () => create(b.dataset.new)));
    panel.querySelectorAll('[data-apply]').forEach(b => {
      b.addEventListener('mousedown', e => e.preventDefault());   // Auswahl im Text bleibt
      b.addEventListener('click', () => { const st = find(b.dataset.apply, b.dataset.from, b.dataset.n); if (st) apply(b.dataset.apply, st); });
    });
    panel.querySelectorAll('[data-more]').forEach(b => {
      b.addEventListener('mousedown', e => e.preventDefault());
      b.addEventListener('click', () => { const st = find(b.dataset.more, b.dataset.from, b.dataset.n); if (st) act(b.dataset.more, st, b.dataset.from, b); });
    });
  }
  return { render };
}

export { paraOf };
