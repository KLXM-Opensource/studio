/*
 * Grafik-Editor – Felder einer Vorlage (Persona „Bild“, Ausbaustufe 6). Die Gestalterin gibt Ebenen frei, alles andere bleibt
 * gesperrt; die Redaktion füllt nur die Felder aus. Am Element: data-ge-field = Base64(JSON):
 *   k  Schlüssel (gleich in Kopien = dasselbe Feld, z. B. in mehreren Formaten)   n  Bezeichnung für die Redaktion
 *   t  image | text | color | toggle
 *   Text: ml (mehrzeilig), max (Zeichen), lines (Zeilen), min (Mindestgröße der Schrift in %), w (Breite des Rahmens), fs (gestaltete Größe)
 *   Ein/Aus: on (Vorgabe)
 * applyValues() setzt Werte in ein (eingehängtes) Dokument: Text passt sich an (Schrift schrumpft bis zur Mindestgröße, sonst
 * Hinweis), Bild = Foto der Mediathek (Ausschnitt nach Fokuspunkt), Farbe = Design-Farbe der Website, Ein/Aus blendet aus. MIT
 */
import { getProp, setProp } from './doc.js';
import { readText, layoutText, applyInput, plainOf } from './text.js';
import { getPhoto, setPhoto } from './photo.js';
import { t } from '../_i18n.js';

const b64 = s => btoa(String.fromCharCode(...new TextEncoder().encode(s)));
const unb64 = s => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)));

export function getField(el) {
  const v = el?.getAttribute?.('data-ge-field');
  if (!v) return null;
  try { const f = JSON.parse(unb64(v)); return f && f.k && f.t ? f : null; } catch { return null; }
}
export function setField(el, f) {
  if (!f) el.removeAttribute('data-ge-field'); else el.setAttribute('data-ge-field', b64(JSON.stringify(f)));
}

/** Felder eines Dokuments in Dokumentreihenfolge: [{f, els}] – je Schlüssel einmal, alle Elemente dazu */
export function fieldList(root) {
  const map = new Map();
  for (const el of root.querySelectorAll('[data-ge-field]')) {
    const f = getField(el);
    if (!f) continue;
    if (!map.has(f.k)) map.set(f.k, { f, els: [] });
    map.get(f.k).els.push(el);
  }
  return [...map.values()];
}

/** Welche Feldarten passen zu einem Element? */
export function kindsFor(el) {
  if (!el) return [];
  const out = [];
  if (el.hasAttribute('data-ge-photo')) out.push('image');
  if (el.localName === 'text') out.push('text');
  if (['rect', 'circle', 'ellipse', 'path', 'polygon', 'text'].includes(el.localName) && !el.hasAttribute('data-ge-photo')) out.push('color');
  out.push('toggle');
  return out;
}
export const KIND_LABEL = { image: () => t('Bild'), text: () => t('Text'), color: () => t('Farbe (Design-Farben)'), toggle: () => t('Ein/Aus') };

/** Neues Feld für ein Element (Vorgaben aus dem Element: Text = Breite/Größe, einzeilig) */
export function newField(el, kind, root) {
  const used = new Set(fieldList(root).map(x => x.f.k));
  let i = 1;
  while (used.has('f' + i)) i++;
  const f = { k: 'f' + i, n: kind === 'image' ? t('Foto') : (el.getAttribute('data-name') || KIND_LABEL[kind]()), t: kind };
  if (kind === 'text') {
    let w = parseFloat(el.getAttribute('data-ge-frame')) || 0;
    if (!w) { try { w = el.getBBox().width; } catch { w = 0; } }
    const lines = [...el.children].filter(c => c.localName === 'tspan' && (c.hasAttribute('x') || c.hasAttribute('dy'))).length || 1;
    Object.assign(f, { ml: lines > 1, max: lines > 1 ? 160 : 60, lines: Math.max(1, lines), min: 60, w: Math.round(w * 10) / 10, fs: parseFloat(getProp(el, 'font-size')) || 16 });
    f.n = el.getAttribute('data-name') || plainOf(readText(el)).slice(0, 30) || f.n;
  }
  if (kind === 'toggle') f.on = true;
  return f;
}

/** Aktueller Wert eines Felds im Dokument (Vorgabe fürs Formular) */
export function valueOf(item, tokens = []) {
  const { f, els } = item, el = els[0];
  if (f.t === 'text') return plainOf(readText(el));
  if (f.t === 'image') return getPhoto(el)?.m || 0;
  if (f.t === 'color') return el.getAttribute('data-ge-ftoken') || tokens.find(x => x.color.toLowerCase() === String(getProp(el, 'fill')).toLowerCase())?.token || '';
  if (f.t === 'toggle') return el.getAttribute('display') !== 'none';
  return null;
}

/** Zeilen eines gesetzten Texts */
const linesOf = el => Math.max(1, [...el.children].filter(c => c.localName === 'tspan' && (c.hasAttribute('x') || c.hasAttribute('dy'))).length);

/**
 * Text in ein Feld setzen und einpassen: Schrift ab der gestalteten Größe in 4-%-Schritten kleiner, bis Breite (Rahmen bzw. w) und
 * Zeilenzahl passen – höchstens bis zur Mindestgröße. Rückgabe: { size, fits }.
 */
export function fitText(el, f, value) {
  const max = f.max || 200;
  let s = String(value ?? '').slice(0, max);
  if (!f.ml) s = s.replace(/\s*\n\s*/g, ' ');
  layoutText(el, applyInput(readText(el), s));
  const fs0 = f.fs || parseFloat(getProp(el, 'font-size')) || 16, min = fs0 * (f.min || 60) / 100;
  const frame = parseFloat(el.getAttribute('data-ge-frame')) || 0;
  const W = frame || f.w || 0;
  let size = fs0;
  const fits = () => {
    let b; try { b = el.getBBox(); } catch { return true; }
    return linesOf(el) <= (f.lines || 1) && (!W || b.width <= W * 1.01 + 0.5);
  };
  for (;;) {
    setProp(el, 'font-size', String(Math.round(size * 100) / 100));
    if (frame) layoutText(el, readText(el));
    if (fits()) return { size, fits: true };
    if (size <= min + 1e-6) return { size, fits: false };
    size = Math.max(min, size * 0.96);
  }
}

/**
 * Werte setzen. vals: {k: Wert}; tokens: Design-Farben [{token, color}]. Rückgabe: Hinweise [{k, msg}] (Text zu lang).
 * Bilder: nur Mediathek-ID; das Zeichnen übernimmt photo.js.
 */
export function applyValues(root, vals, tokens = []) {
  const notes = [];
  for (const { f, els } of fieldList(root)) {
    if (!(f.k in vals)) continue;
    const v = vals[f.k];
    for (const el of els) {
      if (f.t === 'text') {
        const r = fitText(el, f, v);
        if (!r.fits && !notes.some(n => n.k === f.k)) notes.push({ k: f.k, msg: t('Der Text ist zu lang für den Rahmen – bitte kürzen (die Schrift ist schon auf der kleinsten Größe).') });
      } else if (f.t === 'image' && +v > 0) {
        const d = getPhoto(el);
        if (d && d.m !== +v) setPhoto(el, { ...d, m: +v, fx: null, fy: null, z: 1 });
      } else if (f.t === 'color' && v) {
        const tk = tokens.find(x => x.token === v);
        if (tk) { setProp(el, 'fill', tk.color); el.setAttribute('data-ge-ftoken', tk.token); }
      } else if (f.t === 'toggle') {
        if (v) el.removeAttribute('display'); else el.setAttribute('display', 'none');
      }
    }
  }
  return notes;
}
